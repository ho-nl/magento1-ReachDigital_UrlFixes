<?php
/**
 * Copyright (c) Reach Digital (http://reachdigital.nl/)
 * See LICENSE.txt for license details.
 */

/* @var Mage_Core_Model_Resource_Setup $this */
$this->startSetup();

$fallbackTable = $this->getConnection()->getTableName('reachdigital_urlfixes_fallback');

$this->run("CREATE TABLE `$fallbackTable` (
  `url_rewrite_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Rewrite Id',
  `store_id` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT 'Store Id',
  `id_path` varchar(255) DEFAULT NULL COMMENT 'Id Path',
  `request_path` varchar(255) DEFAULT NULL COMMENT 'Request Path',
  `target_path` varchar(255) DEFAULT NULL COMMENT 'Target Path',
  `is_system` smallint(5) unsigned DEFAULT '1' COMMENT 'Defines is Rewrite System',
  `options` varchar(255) DEFAULT NULL COMMENT 'Options',
  `description` varchar(255) DEFAULT NULL COMMENT 'Description',
  `category_id` int(10) unsigned DEFAULT NULL COMMENT 'Category Id',
  `product_id` int(10) unsigned DEFAULT NULL COMMENT 'Product Id',
  `hits` int(10) unsigned DEFAULT 0 COMMENT 'Number of times this URL was looked up',
  PRIMARY KEY (`url_rewrite_id`),
  UNIQUE KEY `UNQ_REACH_URLFIXES_FB_REQUEST_PATH_STORE_ID` (`request_path`,`store_id`),
  UNIQUE KEY `UNQ_REACH_URLFIXES_FB_ID_PATH_IS_SYSTEM_STORE_ID` (`id_path`,`is_system`,`store_id`),
  KEY `IDX_REACH_URLFIXES_FB_TARGET_PATH_STORE_ID` (`target_path`,`store_id`),
  KEY `IDX_REACH_URLFIXES_FB_ID_PATH` (`id_path`),
  KEY `IDX_REACH_URLFIXES_FB_STORE_ID` (`store_id`),
  KEY `FK_REACH_URLFIXES_FB_CTGR_ID_CAT_CTGR_ENTT_ENTT_ID` (`category_id`),
  KEY `FK_REACH_URLFIXES_FB_PRODUCT_ID_CATALOG_CATEGORY_ETY_ETY_ID` (`product_id`),
  KEY `IDX_REACH_URLFIXES_FB_CTGR_ID_IS_SYSTEM_PRD_ID_STORE_ID_ID_PATH` (`category_id`,`is_system`,`product_id`,`store_id`,`id_path`),
  CONSTRAINT `FK_REACH_URLFIXES_FB_CTGR_ID_CAT_CTGR_ENTT_ENTT_ID` FOREIGN KEY (`category_id`) REFERENCES `catalog_category_entity` (`entity_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_REACH_URLFIXES_FB_PRODUCT_ID_CATALOG_CATEGORY_ETY_ETY_ID` FOREIGN KEY (`product_id`) REFERENCES `catalog_product_entity` (`entity_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_REACH_URLFIXES_FB_STORE_ID_CORE_STORE_STORE_ID` FOREIGN KEY (`store_id`) REFERENCES `core_store` (`store_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8 COMMENT='Holds a copy old core_url_rewrite table, used to catch 404s while migrating to sanitized rewrites';");

$this->endSetup();
