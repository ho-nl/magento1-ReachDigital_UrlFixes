<?php

class ReachDigital_UrlFixes_Helper_Url extends Mage_Core_Helper_Abstract
{
    public function getProductUrlKeys(Mage_Catalog_Model_Product $product)
    {
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');

        $select = $this->getProductUrlKeysSelect($db);
        $select->where('product.entity_id = :product_id');

        return $db->fetchAll($select, [ ':product_id' => $product->getId() ]);
    }

    public function getProductUrlKeysSelect(Varien_Db_Adapter_Interface $db)
    {
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');

        $productTable = Mage::getSingleton('core/resource')->getTableName('catalog/product');
        $storeTable = Mage::getSingleton('core/resource')->getTableName('core/store');

        $urlKeyAttribute = Mage::getModel('eav/entity_attribute')->loadByCode(Mage_Catalog_Model_Product::ENTITY, 'url_key');

        return $db->select()
            ->from(
                [ 'product' => $productTable ],
                [
                    'sku' => 'product.sku',
                    'product_id' => 'product.entity_id',
                    'store_effective_value' => 'if(urlkey.value IS NULL, urlkey_default.value, urlkey.value)'
                ])
            ->joinLeft(
                [ 'store' => $storeTable ], 'store.store_id > 0',
                [ 'store_code' => 'store.code', 'store_id' => 'store.store_id' ])
            ->joinLeft(
                [ 'urlkey' => $urlKeyAttribute->getBackendTable()],
                'urlkey.store_id = store.store_id AND urlkey.entity_id = product.entity_id AND urlkey.attribute_id = '.$urlKeyAttribute->getId(),
                [ 'store_value' => 'urlkey.value' ])
            ->joinLeft(
                [ 'urlkey_default' => $urlKeyAttribute->getBackendTable()],
                'urlkey_default.store_id = 0 AND urlkey_default.entity_id = product.entity_id AND urlkey_default.attribute_id = '.$urlKeyAttribute->getId(),
                [ 'default_value' => 'urlkey_default.value' ])
            ->order('product.sku ASC, store.store_id');
    }

    /**
     * Fetch URL key values that conflict with given product's url keys.
     *
     * @param $productId int product ID
     * @param $urlKeys array of existing url keys for a product
     * @return array
     */
    public function getProductUrlKeyConflicts($productId, $urlKeys)
    {
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');

        $select = $this->getProductUrlKeysSelect($db);

        $select->where('product.entity_id != :product_id');

        $conditions = [];
        $binds = [ ':product_id' => $productId ];

        foreach ($urlKeys as $urlKey) {
            $value = $urlKey['store_effective_value'];
            $storeId = (int) $urlKey['store_id'];
            $conditions[] = "(if(urlkey.value IS NULL, urlkey_default.value, urlkey.value) = :urlkey_$storeId AND store.store_id = :storeId_$storeId)";
            $binds[":storeId_$storeId"] = $storeId;
            $binds[":urlkey_$storeId"] = $value;
        }

        $select->where(implode(' OR ', $conditions));

        return $db->fetchAll($select, $binds);
    }
}