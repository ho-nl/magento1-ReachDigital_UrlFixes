<?php

class ReachDigital_UrlFixes_Model_Catalog_Resource_Product extends Mage_Catalog_Model_Resource_Product
{
    public function duplicate($oldId, $newId)
    {
        parent::duplicate($oldId, $newId);

        // Clean up eav store url_key and product name values
        $nameAttribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'name');
        $urlKeyAttribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'url_key');

        $db = $this->getWriteConnection();

        $db->delete($urlKeyAttribute->getBackendTable(), [
            'entity_id=?'      => (int) $newId,
            'entity_type_id=?' => (int) $urlKeyAttribute->getEntityTypeId(),
            'attribute_id=?'   => (int) $urlKeyAttribute->getId(),
        ]);

        $db->delete($nameAttribute->getBackendTable(), [
            'entity_id=?'      => (int) $newId,
            'entity_type_id=?' => (int) $nameAttribute->getEntityTypeId(),
            'attribute_id=?'   => (int) $nameAttribute->getId(),
        ]);

        // Clean rewrites that where created on earlier save
        $db->delete(Mage::getResourceModel('core/url_rewrite')->getMainTable(), [
            'product_id=?' => $newId,
        ]);
    }
}