<?php

class ReachDigital_UrlFixes_Model_Observer extends Mage_Core_Model_Abstract
{
    /**
     * Check that default store is selected when duplicating. While technically it's not a problem to do this,
     * store-specific attribute values of the product for the selected store also and up as the default (store 0)
     * values, which can cause issues with URL key conflicts, so just don't allow it.
     *
     * @event catalog_model_product_duplicate
     * @throws Exception if a store was selected
     * @param Varien_Event_Observer $observer
     */
    public function checkStore(Varien_Event_Observer $observer)
    {
        /** @var Mage_Catalog_Model_Product $product */
        $product = $observer->getCurrentProduct();

        $storeId = $product->getStoreId();
        if ($product->getData('_edit_mode') && $storeId != 0) {
            throw new Exception("Unable to duplicate product at store-level. Select 'Default Values' as store view and try again.");
        }
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

        if (!$product->getIsDuplicate()) {
            return;
        }

        $product->unsetData('name');
        $product->unsetData('url_key');
    }

    /**
     * Check if we can save product without getting URL rewrite conflicts. If not, revert URL key value and show
     * warning.
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
                $urlKey['store_effective_urlkey'] = $newUrlKey;
            } elseif ($product->getStoreId() == 0 && is_null($urlKey['store_urlkey'])) {
                // Else, if default url key value was changed, update it whereever there was no store-specific value
                $urlKey['store_effective_urlkey'] = $newUrlKey;
            }
            $updatedUrlKeys[] = $urlKey;
        }

        // Check conflicts for new URL key value
        $conflicts = $helper->getProductUrlKeyConflicts($product->getId(), $updatedUrlKeys);

        if (count($conflicts)) {

            $product->setData('url_key', $product->getOrigData('url_key'));
            $conflicts = $this->_getConflictingProductsText($conflicts);
            Mage::getSingleton('adminhtml/session')->addWarning(nl2br(
                "New URL key value was reverted as it would conflicts with the following products:\n\n$conflicts"));
        }
    }

    /**
     * Check if product being modified has exiting URL key conflicts, and warn if so.
     *
     * @event catalog_product_edit_action
     * @param Varien_Event_Observer $observer
     * @throws Exception if new url_key conflicts with existing url keys.
     */
    public function checkProductUrlExistingConflicts(Varien_Event_Observer $observer)
    {
        /** @var Mage_Catalog_Model_Product $product */
        $product = $observer->getProduct();
        $helper = Mage::helper('reachdigital_urlfixes/url');

        $urlKeys = $helper->getProductUrlKeys($product);
        $existingConflicts = $helper->getProductUrlKeyConflicts($product->getId(), $urlKeys);

        if ($existingConflicts = $this->_getConflictingProductsText($existingConflicts)) {
            Mage::getSingleton('adminhtml/session')->addWarning(nl2br(
                "This product currently has conflicting URL keys with the following products:\n\n$existingConflicts"));
        }
    }

    protected function _getConflictingProductsText($conflicts)
    {
         if (!count($conflicts)) {
             return false;
         }

        $skus = [];
        foreach ($conflicts as $conflict) {
            $sku = $conflict['product_sku'];
            $store = $conflict['store_code'];
            if (!isset($skus[$sku])) {
                $skus[$sku] = [];
            }
            if (!is_null($conflict['store_urlkey'])) {
                $skus[$sku][] = "in store $store";
            } else {
                $skus[$sku][] = "in store $store, using default value";
            }
        }

        $text = "";
        foreach ($skus as $sku => $stores) {
            $text .= "$sku:\n";
            foreach ($stores as $store) {
                $text .= "&nbsp;&nbsp;$store\n";
            }
        }

        return $text;
    }

    /**
     * 404 catcher based MageHost_RewriteFix. Redirects old URLs that resulted in 404 to the correct direct URL using
     * fallback table. Does not redirect if URL is present in current rewrite table, as this means the URL is valid and
     * caused a 404 due to product/category visibility.
     *
     * @param Varien_Event_Observer $observer
     */
    public function controllerActionPredispatchCmsIndexNoRoute($observer)
    {
        /** @var $controllerAction Mage_Cms_IndexController */
        $controllerAction = $observer->getControllerAction();
        $request =  Mage::app()->getRequest();
        $response = Mage::app()->getResponse();
        $originalPath = $request->getOriginalPathInfo();
        $baseUrl = rtrim( Mage::getBaseUrl(), '/' );

        // Lookup in fallback table
        $urlPath = trim($originalPath, '/');
        $storeId = Mage::app()->getStore()->getId();
        $redirectUrl = Mage::helper('reachdigital_urlfixes/url')->lookupFallbackRewrite($urlPath, $storeId);

        if ($redirectUrl && $redirectUrl != $urlPath) {
            $response->setRedirect($baseUrl . '/' . $redirectUrl, 301);
            $response->sendHeaders();
            $controllerAction->setFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH, true);
        }
    }
}
