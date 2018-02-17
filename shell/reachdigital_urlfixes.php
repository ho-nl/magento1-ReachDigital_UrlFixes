<?php

require_once 'abstract.php';

class Reachdigital_UrlFixes_Shell extends Mage_Shell_Abstract
{
    public function dumpFallbackUrlMappingAction()
    {
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $fallbackTable = $db->getTableName('reachdigital_urlfixes_fallback');

        // Get all rewrites in fallback table that are not also in the current rewrite table
        $query = $db->query(
            "select fb.store_id, fb.request_path from `$fallbackTable` as fb
            left join core_url_rewrite as cur on cur.store_id = fb.store_id and cur.request_path = fb.request_path
            where cur.url_rewrite_id is null
            order by fb.store_id, fb.request_path");

        while ($url = $query->fetch()) {
            $oldUrl = $url['request_path'];
            $storeId = $url['store_id'];
            $newUrl = Mage::helper('reachdigital_urlfixes/url')->lookupFallbackRewrite($oldUrl, $storeId);

            if ($newUrl) {
                echo "store $storeId, fallback $oldUrl, target $newUrl\n";
            } else {
                echo "store $storeId, fallback $oldUrl, target not found\n";
            }
        }
    }

    public function dumpFallbackUrlMappingActionHelp()
    {
        return ["Dump list of fallback URLs that no longer exist and the target URL (if any) they would be mapped to."];
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

        echo "Done!\n";
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
