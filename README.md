# ReachDigital_UrlFixes

Introduces some fixes and checks to avoid URL rewrite conflicts and the resulting problems. Also adds some handy shell
commands for detecting url_key conflicts.


## Fixes

- Fixes incorrect URLs after duplicating products by clearing url_key for the new product
- TODO: Checks on product / category save to block products with duplicate url_keys


## Shell commands

- TODO: Command to show product and category url_key conflicts


## Technical notes:

Methods called to actually create/update rewrites on product save:

- Mage_Catalog_Model_Url::refreshProductRewrite
- Mage_Catalog_Model_Url::_refreshProductRewrite

### Using formatted product name as url_key if empty

This seems to happen in several places:

- Mage_Catalog_Model_Url::_refreshProductRewrite
- Mage_Catalog_Model_Attribute_Backend_Urlkey_Abstract::beforeSave
- Mage_Catalog_Model_Resource_Product_Attribute_Backend_Urlkey::beforeSave

### Relevant models for interacting with or updating URL rewrites:

- Mage_Catalog_Model_Url
- Mage_Catalog_Model_Resource_Url
- Mage_Catalog_Model_Indexer_Url