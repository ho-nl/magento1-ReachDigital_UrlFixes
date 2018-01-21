<?php
/**
 * Copyright (c) Reach Digital (http://reachdigital.nl/)
 * See LICENSE.txt for license details.
 */

require_once 'ho_adminhtml.php';

class ReachDigital_UrlFixes_Shell extends Ho_Adminhtml_Shell
{
    /*
     * List product url_key conflicts
     */
    public function productConflictsAction()
    {
        $helper = Mage::helper('reachdigital_urlfixes/url');

        // TODO
    }

    public function testAction()
    {
//        $product = Mage::getModel('catalog/product')->load(1916);
//        $observer = new Varien_Event_Observer();
//        $observer->setData('product', $product);
//
//        Mage::getSingleton('reachdigital_urlfixes/observer')->checkProductUrlConflicts($observer);
    }
}

$shell = new ReachDigital_UrlFixes_Shell();
$shell->run();
