<?php
/**
 * Copyright (c) Reach Digital (http://reachdigital.nl/)
 * See LICENSE.txt for license details.
 */

/* @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;

$installer->startSetup();

$fallbackTable = $this->getConnection()->getTableName('reachdigital_urlfixes_fallback');

$installer->getConnection()->addColumn(
    $fallbackTable, 'last_requested_at', 'datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'
);

$installer->endSetup();
