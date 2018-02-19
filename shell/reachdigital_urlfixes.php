<?php

require_once 'abstract.php';

class Reachdigital_UrlFixes_Shell extends Mage_Shell_Abstract
{
    public function dumpFallbackUrlMappingAction()
    {
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $fallbackTable = $db->getTableName('reachdigital_urlfixes_fallback');
        $storeTable    = $db->getTableName('core_store');
        $rewriteTable  = $db->getTableName('core_url_rewrite');
        $productTable  = $db->getTableName('catalog_product_entity');

        $attrStatus       = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'status');
        $attrVisibility   = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'visibility');
        $attrName         = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'name');

        $tableStatus      = $attrStatus->getBackendTable();
        $tableVisibility  = $attrVisibility->getBackendTable();
        $tableName        = $attrName->getBackendTable();

        $attrIdStatus     = $attrStatus->getAttributeId();
        $attrIdVisibility = $attrVisibility->getAttributeId();
        $attrIdName       = $attrName->getAttributeId();

        $storeCondition       = $this->getArg('store')   ? " and fb.store_id in ".$this->getArg('store')." " : "" ;
        $systemOnlyCondition  = $this->getArg('system')  ? " and fb.is_system = 1 " : "";
        $visibleOnlyCondition = $this->getArg('visible') ? " and if (pvis.value_id    is not null, pvis.value,    pdvis.value)    != 1 " : "";
        $enabledOnlyCondition = $this->getArg('enabled') ? " and if (pstatus.value_id is not null, pstatus.value, pdstatus.value) != 2 " : "";

        if ($this->getArg('csv')) {
            $separator = "\t";
        } else {
            $separator = ",";
        }
        // Get all rewrites in fallback table that are not also in the current rewrite table, join with product info
        // TODO: rewrite using Zend_Db api
        $query = $db->query("
            select
                fb.store_id,
                store.name as store_name,
                p.sku as product,
                p.type_id as product_type,
                if(pname.value_id is not null, pname.value, pdname.value) as product_name,
                if(p.entity_id is not null, if (if (pstatus.value_id is not null, pstatus.value, pdstatus.value) = 1, 'enabled', 'disabled'), null) as product_status,
                if(p.entity_id is not null, if (if (pvis.value_id is not null, pvis.value, pdvis.value) != 1, 'visible', 'not visible'), null) as product_visibility,
                fb.request_path as old_url
            from `$fallbackTable` as fb
            left join `$storeTable` as store on store.store_id = fb.store_id
            left join `$rewriteTable` as cur on cur.store_id = fb.store_id and cur.request_path = fb.request_path
            left join `$productTable` as p on p.entity_id = fb.product_id
            
            left join `$tableName`       as pdname   on pdname.attribute_id   = $attrIdName       and pdname.entity_id   = fb.product_id and pdname.store_id   = 0
            left join `$tableStatus`     as pdstatus on pdstatus.attribute_id = $attrIdStatus     and pdstatus.entity_id = fb.product_id and pdstatus.store_id = 0
            left join `$tableVisibility` as pdvis    on pdvis.attribute_id    = $attrIdVisibility and pdvis.entity_id    = fb.product_id and pdvis.store_id    = 0
            left join `$tableName`       as pname    on pname.attribute_id    = $attrIdName       and pname.entity_id    = fb.product_id and pname.store_id    = fb.store_id
            left join `$tableStatus`     as pstatus  on pstatus.attribute_id  = $attrIdStatus     and pstatus.entity_id  = fb.product_id and pstatus.store_id  = fb.store_id
            left join `$tableVisibility` as pvis     on pvis.attribute_id     = $attrIdVisibility and pvis.entity_id     = fb.product_id and pvis.store_id     = fb.store_id
            where
                cur.url_rewrite_id is null $storeCondition $systemOnlyCondition $visibleOnlyCondition $enabledOnlyCondition
            order by fb.store_id, p.sku, fb.request_path
        ");

        while ($row = $query->fetch()) {

            $newUrl = Mage::helper('reachdigital_urlfixes/url')->lookupFallbackRewrite($row['old_url'], $row['store_id']);
            $row['new_url'] = $newUrl ? $newUrl : "";
            echo implode($separator, $row)."\n";
        }
    }

    public function dumpFallbackUrlMappingActionHelp()
    {
        return [
            "Dumps a list of fallback URLs that no longer exist and the new URL it would redirect to, along with ",
            "product name, store status, store visibility information. Options for filtering:",
            "",
            "  -store    (only show mapping for the specified store ID(s))",
            "  -system   (only show mapping for old is_system URLs)",
            "  -enabled  (only show mapping if related product is enabled for the URLs' store)",
            "  -visible  (only show mapping if related product is visible in the URLs' store)",
        ];
    }

    public function fillFallbackTableAction()
    {
        $this->confirmOrExit(
            "WARNING: This will truncate the fallback table and copy the current core_url_rewrite table into it.");

        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $fallbackTable = $db->getTableName('reachdigital_urlfixes_fallback');
        $rewriteTable = Mage::getSingleton('core/resource')->getTableName('core/url_rewrite');

        $columns = "`url_rewrite_id`,`store_id`,`id_path`,`request_path`,`target_path`,`is_system`,`options`,`description`,`category_id`,`product_id`";

        echo "Truncating fallback table ($fallbackTable)\n";
        $db->query("TRUNCATE TABLE `$fallbackTable`");

        echo "Copying values from $rewriteTable\n";
        $db->query("INSERT INTO `$fallbackTable` ($columns) SELECT $columns FROM `$rewriteTable`");

        echo "Done!\n";
    }

    public function fillFallbackTableActionHelp()
    {
        return ["Truncate current fallback table, and copy the current core_url_rewrite table into it."];
    }

    public function regenerateRewriteTableAction()
    {
        $this->confirmOrExit(
            "WARNING: This will remove all product and category rewrites, and performs a full catalog URL reindex. ".
            "Ensure that you have a full backup of your database, and have ran the 'fillFallbackTable' action first.");

        // Delete all catalog rewrites
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $rewriteTable = $db->getTableName('core_url_rewrite');

        $affected = $db->delete($rewriteTable, 'product_id IS NOT NULL OR category_id IS NOT NULL');
        echo "Removed $affected existing rewrites.\n";

        echo "Doing full reindex of catalog URL rewrites.\n";
        Mage::getModel('catalog/indexer_url')->reindexAll();

        echo "Done! You should now fully reindex flat catalog tables (if used) and possibly other indexes added by\n";
        echo "third party modules.\n";
    }

    public function regenerateRewriteTableActionHelp()
    {
        return [
            "Remove all catalog URL rewrites (those which refer to a product and/or category) from the rewrite ",
            "table and perform a full reindex."
        ];
    }

    /*
     * Copy fallback rewrites back to current rewrite table. Can be used to quickly restore all URLs if for some reason
     * the cleaned up rewrite table is causing problems (404, changed content)
     */
    public function restoreFallbackTableAction()
    {
        // TODO
    }

    public function restoreFallbackTableActionHelp()
    {
        return [
            "Restore old URL rewrites by copying them from the fallback table to the core_url_rewrite table."
        ];
    }

    /*
     * Dump tab-separated overview of all conflicting URL keys.
     */
    public function dumpProductConflictsAction()
    {
        $conflicts = Mage::helper('reachdigital_urlfixes/url')->getAllProductUrlKeyConflicts();

        foreach ($conflicts as $storeId => $keys) {
            foreach ($keys as $key => $products) {
                foreach ($products as $productId => $product) {
                    $sku         = $product['sku'];
                    $type        = $product['type'];
                    $status      = $product['status'];
                    $name        = $product['name'];
                    $defaultName = $product['default_name'];
                    echo "$storeId\t$key\t$productId\t$sku\t$type\t$status\t$name\t$defaultName\n";
                }
            }
        }
    }

    public function dumpProductConflictsActionHelp()
    {
        return ["Dump all conflicting URL keys as tab-separated data (you might want to redirect this to a file)"];
    }

    protected function confirmOrExit($message)
    {
        echo "$message\n\n";
        if ('y' !== readline("Continue? ")) {
            exit;
        }
    }

    /**
     * Run script
     *
     * @return void
     */
    public function run() {
        $action = $this->getArg('action');
        if (empty($action)) {
            echo $this->usageHelp();
        } else {
            $actionMethodName = $action.'Action';
            if (method_exists($this, $actionMethodName)) {
                $this->$actionMethodName();
            } else {
                echo "Action $action not found!\n";
                echo $this->usageHelp();
                exit(1);
            }
        }
    }

    /**
     * Retrieve Usage Help Message
     *
     * @return string
     */
    public function usageHelp() {
        $help = "Available actions:\n\n";
        $methods = get_class_methods($this);
        foreach ($methods as $method) {
            if (substr($method, -6) != 'Action') {
                continue;
            }
            $help .= '    -action ' . substr($method, 0, -6);
            $helpMethod = $method.'Help';
            if (method_exists($this, $helpMethod)) {
                $extraHelp = $this->$helpMethod();
                if (is_array($extraHelp)) {
                    $help .= "\n";
                    foreach ($extraHelp as $line) {
                        $help .= "      $line\n";
                    }
                } else {
                    $help .= "    $extraHelp";
                }
            }
            $help .= "\n";
        }
        return $help;
    }
}

(new Reachdigital_UrlFixes_Shell())->run();
