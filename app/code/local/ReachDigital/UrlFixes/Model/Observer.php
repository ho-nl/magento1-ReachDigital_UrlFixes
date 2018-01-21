<?php

class ReachDigital_UrlFixes_Model_Observer extends Mage_Core_Model_Abstract
{
    /**
     * Set flag on duplicate product, so we can set a few things straight
     *
     * @event catalog_model_product_duplicate
     * @param Varien_Event_Observer $observer
     */
    public function setDuplicationFlag(Varien_Event_Observer $observer)
    {
        /** @var Mage_Catalog_Model_Product $newProduct */
        $newProduct = $observer->getNewProduct();
        $newProduct->setData('reachdigital_urlfixer_is_duplicate', 1);
    }

    /**
     * Check if we're saving a duplicated product, and if so, clear url_key and product name.
     *
     * @event catalog_product_save_before
     * @param Varien_Event_Observer $observer
     */
    public function clearProductUrlAttributes(Varien_Event_Observer $observer)
    {
        /** @var Mage_Catalog_Model_Product $product */
        $product = $observer->getProduct();

        if (!$product->getData('reachdigital_urlfixer_is_duplicate')) {
            return;
        }

        $product->unsetData('name');
        $product->unsetData('url_key');
    }

    /**
     * Check if we can save product without getting URL rewrite conflicts
     * - Check for other products with conflicting url_key values (for any store)
     * - TODO: Check existing rewrites in rewrite table? Not important if existing rewrites are already sanitized
     *
     * @event catalog_product_save_before
     * @param Varien_Event_Observer $observer
     * @throws Exception if new url_key conflicts with existing url keys.
     */
    public function checkProductUrlConflicts(Varien_Event_Observer $observer)
    {
        /** @var Mage_Catalog_Model_Product $product */
        $product = $observer->getProduct();
        $helper = Mage::helper('reachdigital_urlfixes/url');

        if (!$product->dataHasChangedFor('url_key')) {
            return;
        }

        $newUrlKey = $product->getData('url_key');

        // Match indexer logic which autogenerates URL from product name if url_key is empty
        if ($newUrlKey == '') {
            $newUrlKey = $product->formatUrlKey($product->getName());
        }

        // Get existing url_keys for all stores, update with new value
        $urlKeys = $helper->getProductUrlKeys($product);
        $updatedUrlKeys = [];

        foreach ($urlKeys as $urlKey) {
            if ($product->getStoreId() == $urlKey['store_id']) {
                // If url key was changed for a specific store, update it with new value
                $urlKey['store_effective_value'] = $newUrlKey;
            } elseif ($product->getStoreId() == 0 && is_null($urlKey['store_value'])) {
                // Else, if default url key value was changed, update it whereever there was no store-specific value
                $urlKey['store_effective_value'] = $newUrlKey;
            }
            $updatedUrlKeys[] = $urlKey;
        }

        $conflicts = $helper->getProductUrlKeyConflicts($product->getId(), $updatedUrlKeys);

        if (count($conflicts)) {
            $skus = [];
            foreach ($conflicts as $conflict) {
                $skus[] = "${conflict['sku']} in store ${conflict['store_code']}";
            }
            // TODO: We could also just revert url_key value, add a session warning and continue saving?
            throw new Exception("Unable to save product; URL key conflicts with existing product(s): " . implode(", ", $skus));
        }
    }
}
