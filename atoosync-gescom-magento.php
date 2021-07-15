<?php
/**
* 2007-2018 Atoo Next
*
* --------------------------------------------------------------------------------
*  /!\ Ne peut être utilisé en dehors du programme Atoo-Sync /!\
* --------------------------------------------------------------------------------
*
*  Ce fichier fait partie du logiciel Atoo-Sync .
*  Vous n'êtes pas autorisé à le modifier, à le recopier, à le vendre ou le redistribuer.
*  Cet en-tête ne doit pas être retiré.
*
*  @author    Atoo Next SARL (contact@atoo-next.net)
*  @copyright 2009-2018 Atoo Next SARL
*  @license   Commercial
*  @script    atoosync-gescom-magento.php
*
* --------------------------------------------------------------------------------
*  /!\ Ne peut être utilisé en dehors du programme Atoo-Sync /!\
* --------------------------------------------------------------------------------
*/

define('_ATOOSYNCSCRIPTVERSION_', '231231');
define('_ECOMMERCESHOP_', 'Magento');

header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

/*
@ini_set('display_errors', 'on');
@error_reporting(E_ALL | E_STRICT);
*/
// Script requis
require_once 'atoosync-gescom-webservice-configuration.php';
require_once 'atoosync-gescom-webservice-products.php';
require_once 'atoosync-gescom-webservice-prices.php';
require_once 'atoosync-gescom-webservice-orders.php';
require_once 'atoosync-gescom-webservice-categories.php';
require_once 'atoosync-gescom-webservice-images.php';
require_once 'atoosync-gescom-webservice-customers.php';
require_once 'atoosync-gescom-webservice-customer-groups.php';
require_once 'atoosync-gescom-webservice-documents.php';
require_once 'atoosync-gescom-webservice-tools.php';
require_once 'atoosync-gescom-webservice-customizables-functions.php';

$directories = array();
//$directories[] ='classes';
$directories[] ='Classes/Commons';
$directories[] ='Classes/Cms';
$directories[] ='Classes/Cms/Configuration';
$directories[] ='Classes/Cms/Order';
$directories[] ='Classes/Erp';
$directories[] ='Classes/Erp/Customer';
$directories[] ='Classes/Erp/Product';
$directories[] ='Classes/Erp/Order';
foreach ($directories as $directory) {
    foreach (glob($directory."/*.php") as $filename) {
        if (strtolower(basename($filename)) !='index.php') {
            require_once $filename;
        }
    }
}
// Initilialise Magento
use Magento\Framework\App\Bootstrap;

require '../../app/bootstrap.php';
$bootstrap = Bootstrap::create(BP, $_SERVER);

$objectManager = $bootstrap->getObjectManager();
$url = \Magento\Framework\App\ObjectManager::getInstance();
$storeManager = $url->get('\Magento\Store\Model\StoreManagerInterface');
$state = $objectManager->get('\Magento\Framework\App\State');
$state->setAreaCode('adminhtml');
$scopeConfig = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');
$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
$registry = $objectManager->get('Magento\Framework\Registry');

// si aucun argument
if (!AtooSyncGesComTools::getIsset('cmd')) {
    exit;
}

// vérifie si le module Atoo-Sync est installé
if (!AtooSyncGesComTools::moduleIsInstalled()) {
    echo "ERROR";
    echo "<br />The Atoo-Sync GesCom module is not installed on this PrestaShop!";
    exit;
}
// Si il y a une restriction par Adresse IP
if (AtooSyncGesComTools::getConfig('atoosync_access','general','ipaddress') !='') {
    if (!in_array($_SERVER['REMOTE_ADDR'], explode(',', AtooSyncGesComTools::getConfig('atoosync_access','general','ipaddress')))) {
        echo "ERROR";
        echo "<br />IP address '".$_SERVER["REMOTE_ADDR"]."' is not allowed";
        exit;
    }
}
// Si il y a une restriction par Hôte.
if (AtooSyncGesComTools::getConfig('atoosync_access','general','hostname') !='') {
    $ips =array();
    foreach (explode(',', AtooSyncGesComTools::getConfig('atoosync_access','general','hostname')) as $host) {
        array_push($ips, gethostbyname($host));
    }

    if (!in_array($_SERVER['REMOTE_ADDR'], $ips)) {
        echo "ERROR";
        echo "<br />IP address '".$_SERVER["REMOTE_ADDR"]."' is not allowed";
        exit;
    }
}

// Si le mot de passe est vide ou non renseigné
// ou si le mot de passe ne correspond pas à la configuration dans PrestaShop
if (!AtooSyncGesComTools::getIsset('pass')
    or !AtooSyncGesComTools::getValue('pass')
    or (sha1(AtooSyncGesComTools::getConfig('atoosync_access','general','password')) != AtooSyncGesComTools::getValue('pass'))) {
    echo "ERROR";
    echo "<br />The password does not match.";
    exit;
}


$result = Dispatcher();
if ($result == false) {
    echo 'ERROR';
} else {
    echo 'OK-OK';
}
exit;

/*
 *  Dispatche au dispatcher de chaque script
 */
function Dispatcher()
{
    $result= true;

    switch (AtooSyncGesComTools::getValue('cmd')) {
        case 'test':
            $result=AtooSyncGesComTools::TestAtooSync();
            break;
    }

    if ($result) {
        $result = AtooSyncConfiguration::dispatcher();
    }

    if ($result) {
        $result = AtooSyncOrders::dispatcher();
    }

    if ($result) {
        $result = AtooSyncProducts::dispatcher();
    }

    if ($result) {
        $result = AtooSyncPrices::dispatcher();
    }

    if ($result) {
        $result = AtooSyncCategories::dispatcher();
    }

    if ($result) {
        $result = AtooSyncImages::dispatcher();
    }

    if ($result) {
        $result = AtooSyncCustomers::dispatcher();
    }

    if ($result) {
        $result = AtooSyncCustomerGroups::dispatcher();
    }
    
    if ($result) {
        $result = AtooSyncDocuments::dispatcher();
    }

    return $result;
}
