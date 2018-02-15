<?php

require_once 'abstract.php';

class Reachdigital_UrlFixes_Shell extends Mage_Shell_Abstract
{
    public function testAction()
    {
        $conn = Mage::getSingleton('core/resource')->getConnection('core_read');
        $fallbackTable = $conn->getTableName('reachdigital_urlfixes_fallback');
        $query = $conn->query(
            "select fb.store_id, fb.request_path from `$fallbackTable` as fb
            left join core_url_rewrite as cur on cur.store_id = fb.store_id and cur.request_path = fb.request_path
            where cur.url_rewrite_id is null");

        while ($url = $query->fetch()) {
            $oldUrl = $url['request_path'];
            $storeId = $url['store_id'];
            $newUrl = Mage::helper('reachdigital_urlfixes/url')->lookupFallbackRewrite($oldUrl, $storeId);

            if ($newUrl) {
                echo "Mapping $oldUrl to $newUrl for store $storeId\n";
            } else {
                echo "Not found: $oldUrl for store $storeId\n";
            }
        }
    }

    public function fillFallbackTableAction()
    {
        $conn = Mage::getSingleton('core/resource')->getConnection('core_write');
        $fallbackTable = $conn->getTableName('reachdigital_urlfixes_fallback');
        $rewriteTable = Mage::getSingleton('core/resource')->getTableName('core/url_rewrite');

        $columns = "`url_rewrite_id`,`store_id`,`id_path`,`request_path`,`target_path`,`is_system`,`options`,`description`,`category_id`,`product_id`";

        if ('y' !== readline("This will truncate the fallback table and fill it with the current rewrites, and can not be undone. Continue? ")) {
            exit;
        }

        echo "Truncating fallback table ($fallbackTable)\n";
        $conn->query("TRUNCATE TABLE `$fallbackTable`");

        echo "Copying values from $rewriteTable\n";
        $conn->query("INSERT INTO `$fallbackTable` ($columns) SELECT $columns FROM `$rewriteTable`");
    }

    /*
     * Copy fallback rewrites back to current rewrite table. Can be used to quickly restore all URLs if for some reason
     * the cleaned up rewrite table is causing problems (404, changed content)
     */
    public function restoreFallbackTableAction()
    {
        // TODO
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
        $help = 'Available actions: ' . "\n";
        $methods = get_class_methods($this);
        foreach ($methods as $method) {
            if (substr($method, -6) == 'Action') {
                $help .= '    -action ' . substr($method, 0, -6);
                $helpMethod = $method.'Help';
                if (method_exists($this, $helpMethod)) {
                    $help .= '    ' . $this->$helpMethod();
                }
                $help .= "\n";
            }
        }
        return $help;
    }
}

(new Reachdigital_UrlFixes_Shell())->run();
