<?php

/**
 * Class ReachDigital_UrlFixes_Helper_Product
 *
 * Convenience methods to assist with sanitizing of product URL keys and names.
 */
class ReachDigital_UrlFixes_Helper_Product extends Mage_Core_Helper_Abstract
{
    const CLEAR_MODE_STORES = 1;
    const CLEAR_MODE_STORE = 2;
    const CLEAR_MODE_ALL = 3;

    const UPDATE_MODE_SET_STORE = 1;
    const UPDATE_MODE_FORCE_DEFAULT = 2;

    /**
     * Lookup product IDs for given SKU(s).
     *
     * @param $skus string|array
     * @return array
     */
    public function getProductIdsFromSkus($skus) : array
    {
        $skus = is_array($skus) ? $skus : [$skus];

        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $select = $db->select()
            ->from(Mage::getResourceModel('catalog/product')->getTable('catalog/product'), ['entity_id'])
            ->where('sku IN (?)', $skus);

        return array_column($db->fetchAll($select), 'entity_id');
    }

    /**
     * Get SKU by product ID.
     *
     * @param $sku
     * @return int|false
     */
    public function getProductIdFromSku($sku)
    {
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $select = $db->select()
            ->from(Mage::getResourceModel('catalog/product')->getTable('catalog/product'), ['entity_id'])
            ->where('sku = ?', $sku);
        $ids = array_column($db->fetchAll($select), 'entity_id');

        if (count($ids) == 1) {
            return $ids[0];
        }

        return false;
    }

    /**
     * Clear attribute values for one or more products. Can delete default as well as store level values (mode = all),
     * delete only store-level values (mode = stores), or for a specific store given by $storeId (mode store). Uses
     * 'all' mode by default.
     *
     * @param $productIds int|array with product IDs
     * @param $code string attribute code
     * @param $mode int deletion mode, must be one of the MODE_* constants
     * @param $storeId int|array|bool store ID(s) to delete values for when $mode is 'store'
     * @return int number of affected rows
     */
    public function clearAttributeValues($productIds, $code, $mode = self::CLEAR_MODE_ALL, $storeId = false) : int
    {
        $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, $code);
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');

        if (!is_array($productIds)) {
            $productIds = [$productIds];
        }

        $storeCondition = [];
        if ($mode == self::CLEAR_MODE_STORE && is_numeric($storeId)) {
            $storeCondition = ['store_id = ?' => (int)$storeId];
        } elseif ($mode == self::CLEAR_MODE_STORE && is_array($storeId)) {
            $storeCondition = ['store_id in(?)' => $storeId];
        } elseif ($mode == self::CLEAR_MODE_STORES) {
            $storeCondition = [ 'store_id != ?' => (int) 0 ];
        }

        return $db->delete($attribute->getBackendTable(), array_merge($storeCondition, [
            'entity_id in(?)'    => $productIds,
            'entity_type_id = ?' => (int) $attribute->getEntityTypeId(),
            'attribute_id = ?'   => (int) $attribute->getId(),
        ]));
    }

    /**
     * Directly set product attribute value. Sets default (store 0) value by default, or for the given $storeId.
     *
     * @param int $productId
     * @param string $code
     * @param string $value
     * @param int $storeId
     * @return int affected rows
     */
    public function setAttributeValue($productId, $code, $value, $storeId = 0)
    {
        $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, $code);
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');

        $affected = $db->insertOnDuplicate(
            $attribute->getBackendTable(),
            [
                'store_id' => $storeId,
                'entity_id' => $productId,
                'attribute_id' => $attribute->getAttributeId(),
                'value' => $value
            ],
            [ 'value' ]
        );
        return $affected ? 1 : 0; // Due to how insert-on-duplicate returns affected rows (1 for insert, 2 for update)
    }

    /**
     * Get product attribute value
     *
     * @param $productId
     * @param $code
     * @param int $storeId
     * @return string
     */
    public function getAttributeValue($productId, $code, $storeId = 0) : string
    {
        $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, $code);
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');

        $select = $db->select()
            ->from($attribute->getBackendTable(), 'value')
            ->where('store_id = ?', $storeId)
            ->where('entity_id = ?', $productId)
            ->where('attribute_id = ?', $attribute->getAttributeId());

        return $db->fetchOne($select);
    }

    /**
     * Change product name and URL key. If $key is not given, the url-formatted product name will be used as key.
     * By default all store-level values are cleared, and name/key are set as default values.
     *
     * @param int $productId
     * @param string $name
     * @param bool|string $key
     * @param int $mode one of the UPDATE_MODE constants
     * @param int $storeId
     * @return int 1 if any records for the product were affected, else 0
     */
    public function setProductNameAndKey($productId, $name, $key = false, $mode = self::UPDATE_MODE_FORCE_DEFAULT, $storeId = 0) : int
    {
        $affected = 0;

        if (!is_numeric($productId)) {
            return 0;
        }
        if ($key === false) {
            $key = $this->formatProductUrlKey($name);
        }

        if ($mode == self::UPDATE_MODE_FORCE_DEFAULT) {
            $affected += $this->clearAttributeValues($productId, 'name');
            $affected += $this->clearAttributeValues($productId, 'url_key');
            $storeId = 0;
        }
        $affected += $this->setAttributeValue($productId, 'name', $name, $storeId);
        $affected += $this->setAttributeValue($productId, 'url_key', $key, $storeId);

        return $affected ? 1 : 0;
    }

    /**
     * Return parent product IDs for given product ID(s), or if not given, return all parent IDs.
     *
     * @param array|bool $childIds
     * @return array
     */
    public function getParentIds($childIds = false) : array
    {
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $select = $db->select()
            ->distinct(true)
            ->from(Mage::getResourceModel('catalog/product_type_configurable')->getMainTable(), 'parent_id');

        if (is_array($childIds)) {
            $select->where('product_id in(?)', $childIds);
        } elseif (is_numeric($childIds)) {
            $select->where('product_id = ?', $childIds);
        }
        return array_column($db->fetchAll($select), 'parent_id');
    }

    /**
     * Return array with child product IDs and SKUs for given parent product ID(s). If $parentIds is not given,
     * return all child IDs. The returned associative array has SKUs as keys and IDs as values.
     *
     * @param array|bool $parentIds
     * @return array
     */
    public function getChildProducts($parentIds = false) : array
    {
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $linkTable = Mage::getResourceModel('catalog/product_type_configurable')->getMainTable();
        $productTable = $db->getTableName('catalog_product_entity');
        $select = $db->select()
            ->distinct(true)
            ->from(['link' => $linkTable ], [ 'child_id' => 'link.product_id' ])
            ->joinLeft([ 'p' => $productTable ], 'p.entity_id = link.product_id', [ 'child_sku' => 'p.sku' ]);

        if (is_array($parentIds)) {
            $select->where('link.parent_id in(?)', $parentIds);
        } elseif ($parentIds) {
            $select->where('link.parent_id = ?', $parentIds);
        }
        $ids = $db->fetchAll($select);
        return array_column($ids, 'child_id', 'child_sku');
    }

    /**
     * Get product IDs and SKUs if their SKUs start with the given $skuPrefix.
     *
     * @param $skuPrefix
     * @return array
     */
    public function getChildProductsByPrefix($skuPrefix)
    {
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $productTable = $db->getTableName('catalog_product_entity');
        $select = $db->select()
            ->distinct(true)
            ->from(['p' => $productTable ], [
                'child_id' => 'p.entity_id',
                'child_sku' => 'p.sku'
            ])->where('p.sku like ?', $skuPrefix.'%');
        $ids = $db->fetchAll($select);
        return array_column($ids, 'child_id', 'child_sku');
    }

    /**
     * Format $key to be used as URL key.
     *
     * @param $key
     * @return string
     */
    public function formatProductUrlKey($key) : string
    {
        return Mage::getSingleton('catalog/product')->formatUrlKey($key);
    }
}
