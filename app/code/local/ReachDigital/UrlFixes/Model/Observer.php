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
    public function clearUrlAttributes(Varien_Event_Observer $observer)
    {
        /** @var Mage_Catalog_Model_Product $product */
        $product = $observer->getProduct();

        if (!$product->getData('reachdigital_urlfixer_is_duplicate')) {
            return;
        }

        $product->unsetData('name');
        $product->unsetData('url_key');
    }
}
