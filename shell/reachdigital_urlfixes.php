<?php

require_once 'abstract.php';

class Reachdigital_UrlFixes_Shell extends Mage_Shell_Abstract
{
    public function fillFallbackTableAction()
    {
        $conn = Mage::getSingleton('core/resource')->getConnection('core_write');
        $fallbackTable = $conn->getTableName('reachdigital_urlfixes_fallback');
        $rewriteTable = Mage::getSingleton('core/resource')->getTableName('core/url_rewrite');

        $columns = "`url_rewrite_id`,`store_id`,`id_path`,`request_path`,`target_path`,`is_system`,`options`,`description`,`category_id`,`product_id`";

        echo "Truncating fallback table ($fallbackTable)\n";
        $conn->query("TRUNCATE TABLE `$fallbackTable`");

        echo "Copying values from $rewriteTable\n";
        $conn->query("INSERT INTO `$fallbackTable` ($columns) SELECT $columns FROM `$rewriteTable`");
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
