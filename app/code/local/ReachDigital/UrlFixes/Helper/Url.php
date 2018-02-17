<?php

class ReachDigital_UrlFixes_Helper_Url extends Mage_Core_Helper_Abstract
{
    /**
     * Get effective url keys for given product, or all products if no ID is given.
     *
     * @param int|Mage_Catalog_Model_Product $product
     * @return array
     */
    public function getProductUrlKeys($product = 0)
    {
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');

        if (is_numeric($product)) {
            $productId = $product;
        } else {
            $productId = $product->getId();
        }

        $select = $this->getProductUrlKeysSelect($db);

        if ($productId == 0) {
            return $db->fetchAll($select);
        } else {
            $select->where('product.entity_id = :product_id');
            return $db->fetchAll($select, [':product_id' => $productId]);
        }
    }

    /**
     * Base select statement for getting all effective product URL key values
     *
     * @param Varien_Db_Adapter_Interface $db
     * @return Varien_Db_Select
     */
    public function getProductUrlKeysSelect(Varien_Db_Adapter_Interface $db): Varien_Db_Select
    {
        $productTable = Mage::getSingleton('core/resource')->getTableName('catalog/product');
        $storeTable   = Mage::getSingleton('core/resource')->getTableName('core/store');

        $urlKeyAttribute = Mage::getModel('eav/entity_attribute')->loadByCode(Mage_Catalog_Model_Product::ENTITY, 'url_key');
        $statusAttribute = Mage::getModel('eav/entity_attribute')->loadByCode(Mage_Catalog_Model_Product::ENTITY, 'status');
        $nameAttribute   = Mage::getModel('eav/entity_attribute')->loadByCode(Mage_Catalog_Model_Product::ENTITY, 'name');

        return $db->select()
            ->from(
                [ 'product' => $productTable ],
                [
                    'product_sku'  => 'product.sku',
                    'product_type' => 'product.type_id',
                    'product_id'   => 'product.entity_id',
                    'store_effective_urlkey' => 'if(urlkey.value IS NULL, urlkey_default.value, urlkey.value)',
                    'store_effective_status' => 'if(status.value IS NULL, status_default.value, status.value)',
                    'store_effective_name'   => 'if(  name.value IS NULL,   name_default.value,   name.value)',
                ])
            ->joinLeft(
                [ 'store' => $storeTable ], 'store.store_id > 0',
                [ 'store_code' => 'store.code', 'store_id' => 'store.store_id' ])
            ->joinLeft(
                [ 'urlkey' => $urlKeyAttribute->getBackendTable()],
                'urlkey.store_id = store.store_id AND urlkey.entity_id = product.entity_id AND urlkey.attribute_id = '.$urlKeyAttribute->getId(),
                [ 'store_urlkey' => 'urlkey.value' ])
            ->joinLeft(
                [ 'urlkey_default' => $urlKeyAttribute->getBackendTable()],
                'urlkey_default.store_id = 0 AND urlkey_default.entity_id = product.entity_id AND urlkey_default.attribute_id = '.$urlKeyAttribute->getId(),
                [ 'default_urlkey' => 'urlkey_default.value' ])
            ->joinLeft(
                [ 'status' => $statusAttribute->getBackendTable() ],
                'status.store_id = store.store_id AND status.entity_id = product.entity_id AND status.attribute_id = '.$statusAttribute->getId(),
                [ 'store_status' => 'status.value' ])
            ->joinLeft(
                [ 'status_default' => $statusAttribute->getBackendTable() ],
                'status_default.store_id = 0 AND status_default.entity_id = product.entity_id AND status_default.attribute_id = '.$statusAttribute->getId(),
                [ 'default_status' => 'status_default.value' ])
            ->joinLeft(
                [ 'name' => $nameAttribute->getBackendTable() ],
                'name.store_id = store.store_id AND name.entity_id = product.entity_id AND name.attribute_id = '.$nameAttribute->getId(),
                [ 'store_name' => 'name.value' ])
            ->joinLeft(
                [ 'name_default' => $nameAttribute->getBackendTable() ],
                'name_default.store_id = 0 AND name_default.entity_id = product.entity_id AND name_default.attribute_id = '.$nameAttribute->getId(),
                [ 'default_name' => 'name_default.value' ])
            ->order('product.sku ASC, store.store_id');
    }

    /**
     * For the given $productId and $urlKeys, return all conflicting URL keys
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
            $value = $urlKey['store_effective_urlkey'];
            $storeId = (int) $urlKey['store_id'];
            $conditions[] = "(if(urlkey.value IS NULL, urlkey_default.value, urlkey.value) = :urlkey_$storeId AND store.store_id = :storeId_$storeId)";
            $binds[":storeId_$storeId"] = $storeId;
            $binds[":urlkey_$storeId"] = $value;
        }

        $select->where(implode(' OR ', $conditions));

        return $db->fetchAll($select, $binds);
    }

    /**
     * Returns all conflicting URL keys, per store, in a multidimensional array:
     *
     * [
     *   // storeId => array of products indexed by the url_key they share
     *   1 => [
     *     'some-url-key' => [
     *       // productId => relevant product data
     *       1919 => [
     *         'sku'    => 'OSUI-0001',
     *         'type'   => 'configurable',
     *         'status' => '2',
     *         'name'   => 'Shiny Suit',
     *       ]
     *     ]
     *   ],
     *   2 => ...
     * ]
     *
     * @return array
     */
    public function getAllProductUrlKeyConflicts(): array
    {
        $urlKeysByStore = [];

        $urlKeys = $this->getProductUrlKeys();

        foreach ($urlKeys as $urlKey) {

            $storeId     = $urlKey['store_id'];
            $productId   = $urlKey['product_id'];
            $productSku  = $urlKey['product_sku'];
            $productType = $urlKey['product_type'];
            $name        = $urlKey['store_effective_name'];
            $defaultName = $urlKey['default_name'];
            $status      = $urlKey['store_effective_status'];
            $value       = $urlKey['store_effective_urlkey'];

            if (!isset($urlKeysByStore[$storeId])) {
                $urlKeysByStore[$storeId] = [];
            }
            if (!isset($urlKeysByStore[$storeId][$value])) {
                $urlKeysByStore[$storeId][$value] = [];
            }
            $urlKeysByStore[$storeId][$value][$productId] = [
                'sku'          => $productSku,
                'type'         => $productType,
                'status'       => $status,
                'name'         => $name,
                'default_name' => $defaultName,
            ];
        }

        // Filter out non-conflicting url_keys (with only one product using it)
        foreach ($urlKeysByStore as $storeId => $urlKeys) {
            foreach ($urlKeys as $urlKey => $products) {
                if (count($products) < 2) {
                    unset($urlKeysByStore[$storeId][$urlKey]);
                }
            }
            if (!count($urlKeysByStore[$storeId])) {
                unset($urlKeysByStore[$storeId]);
            }
        }

        return $urlKeysByStore;
    }

    /**
     * Lookup current URL in fallback table, and get the current URL by matching on id_path or product/category IDs for
     * system URLs.
     *
     * @param string $requestPath
     * @param int $storeId
     * @return Varien_Object|false
     */
    public function lookupFallbackRewrite($requestPath, $storeId)
    {
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $fallbackTable = $db->getTableName('reachdigital_urlfixes_fallback');
        $rewriteTable = Mage::getResourceModel('catalog/url')->getMainTable();

        $bind = [
            'request_path'  => $requestPath,
            'store_id'      => (int) $storeId
        ];

        $select = $db->select()
            ->from([ 'fb' => $fallbackTable], ['fallback_id' => 'fb.url_rewrite_id'])
            ->where('fb.store_id = :store_id')
            ->where('fb.request_path = :request_path')
            // Ensure best match (on both product and category) is first row
            ->order('cr.category_id desc');

        // Join current URL rewrites which are:
        // - Direct URLs (is_system=1)
        // - In the same store
        // - For the same product and category, or only the same product
        // - Not for the same request_path (this means URL is valid and caused 404 due to visibility
        $select->joinLeft(
            [ 'cr' => $rewriteTable ],
            'cr.request_path != fb.request_path and cr.store_id = fb.store_id and cr.is_system = 1
            and
            (
              (
                (cr.category_id = fb.category_id or (fb.category_id is null and cr.category_id is null))
                and
                (cr.product_id = fb.product_id or (fb.product_id is null and cr.product_id is null))
              )
              or (fb.category_id is not null and cr.category_id is null and fb.product_id = cr.product_id)
            )',
            [ 'current_url' => 'cr.request_path' ]
        );

        $row = $db->fetchRow($select, $bind);

        if (!$row) {
            return false;
        }

        $rewrite = new Varien_Object($row);

        $db->update($fallbackTable,
            [ 'hits' => new Zend_Db_Expr('hits + 1') ],
            [ 'url_rewrite_id = ?' => $row['fallback_id'] ]
        );

        return $rewrite->getData('current_url');
    }
}
