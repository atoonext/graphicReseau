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
*  @script    atoosync-gescom-webservice-configuration.php
*
* --------------------------------------------------------------------------------
*  /!\ Ne peut être utilisé en dehors du programme Atoo-Sync /!\
* --------------------------------------------------------------------------------
*/
use AtooNext\AtooSync\Cms\Configuration\CmsConfiguration;
use AtooNext\AtooSync\Cms\Configuration\CmsConfigurationCarrier;
use AtooNext\AtooSync\Cms\Configuration\CmsConfigurationCountry;
use AtooNext\AtooSync\Cms\Configuration\CmsConfigurationCurrency;
use AtooNext\AtooSync\Cms\Configuration\CmsConfigurationLanguage;
use AtooNext\AtooSync\Cms\Configuration\CmsConfigurationManufacturer;
use AtooNext\AtooSync\Cms\Configuration\CmsConfigurationOrderStatus;
use AtooNext\AtooSync\Cms\Configuration\CmsConfigurationPayment;
use AtooNext\AtooSync\Cms\Configuration\CmsConfigurationProductFeature;
use AtooNext\AtooSync\Cms\Configuration\CmsConfigurationShop;
use AtooNext\AtooSync\Cms\Configuration\CmsConfigurationSupplier;
use AtooNext\AtooSync\Cms\Configuration\CmsConfigurationTax;
use AtooNext\AtooSync\Cms\Configuration\CmsConfigurationTaxRule;
use AtooNext\AtooSync\Cms\Configuration\CmsConfigurationZone;

class AtooSyncConfiguration
{
    public static function dispatcher()
    {
        $result= true;
        switch (AtooSyncGesComTools::getValue('cmd')) {
            case 'getconfig':
                echo self::getConfig();
                break;
            case 'getversion':
                $result = self::getVersion();
                break;
        }
        return $result;
    }

    public static function getConfig()
    {
        $ATSCConfiguration = new CmsConfiguration();
        $ATSCConfiguration->languages = self::getLanguages();
        $ATSCConfiguration->payments = self::getPayments();
        $ATSCConfiguration->orderStatuses = self::getOrderStatuses();
        $ATSCConfiguration->taxRules = self::getTaxRules();
        $ATSCConfiguration->taxes = self::getTaxes();
        $ATSCConfiguration->zones = self::getZones();
        $ATSCConfiguration->manufacturers = self::getManufacturers();
        $ATSCConfiguration->suppliers = self::getSuppliers();
        $ATSCConfiguration->carriers = self::getCarriers();
        $ATSCConfiguration->currencies = self::getCurrencies();
        $ATSCConfiguration->countries = self::getCountries();
        $ATSCConfiguration->shops = self::getShops();
        $ATSCConfiguration->productFeatures = self::getProductFeatures();
        return AtooSyncGesComTools::formatXml($ATSCConfiguration->getXML());
    }
    /*
   * Retourne la version de PrestaShop
   */
    public static function getVersion()
    {
    }
    /*
   * Retourne le id_lang de la langue par défaut
   * depuis la table configuration
   */
    public static function idLangDefault()
    {
    }
    /**
     * retourne la liste des différentes langues
     *
     * @return CmsConfigurationLanguage[]
     */
    public static function getLanguages()
    {
        global $objectManager;
        global $storeManager;

        //$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $scopeConfig = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');
        $stores = $storeManager->getStores($withDefault = false);
        //new empty array to store locale codes
        $localeForAllStores = [];
        $optionInteface = $objectManager->get('\Magento\Framework\Locale\OptionInterface');
        $optionLocale = $optionInteface->getOptionLocales();
        $langues = [];
        foreach ($optionLocale as $locale) {
            
            $langues[$locale['value']] = $locale['label'];
        }
        $localeForAllStores[0]['code'] = $scopeConfig->getValue('general/locale/code', 'default', 0);
        // par défaut le label = le code
        $localeForAllStores[0]['label'] = $scopeConfig->getValue('general/locale/code', 'default', 0);
        // si le code dela langue existe dans le tableau alors le label est repris.
        if (array_key_exists($localeForAllStores[0]['code'], $langues)) {
            $localeForAllStores[0]['label'] = $langues[$localeForAllStores[0]['code']];
        }
        //To get list of locale for all stores;
        foreach ($stores as $store) {
            $localeForAllStores[$store->getStoreId()]['code'] = $scopeConfig->getValue('general/locale/code', 'stores', $store->getStoreId());
            $localeForAllStores[$store->getStoreId()]['label'] = $scopeConfig->getValue('general/locale/code', 'stores', $store->getStoreId());
            $label = $scopeConfig->getValue('general/locale/code', 'stores', $store->getStoreId());
            if (isset($langues[$label])) {
                $localeForAllStores[$store->getStoreId()]['label'] = $langues[$label];
            }
        }

        $ATSCConfigurationLanguages = [];
        foreach ($localeForAllStores as $id_shop => $usedLang) {
            $deflang = 0;
            if ($id_shop == 0) {
                $deflang = 1;
            }
            $language = new CmsConfigurationLanguage();
            $language->code = (string)$id_shop;
            $language->isocode = (string)$usedLang['code'];
            $language->name = (string)$usedLang['label'];
            $language->default = $deflang;

            $ATSCConfigurationLanguages[] = $language;
        }
        return $ATSCConfigurationLanguages;
    }

    /**
     * Retourne la liste des modes de paiements
     *
     * @return CmsConfigurationPayment[]
     */
    public static function getPayments()
    {
        global $objectManager;
        $paymentHelper = $objectManager->get('Magento\Payment\Helper\Data');
        $activePaymentMethods = $paymentHelper->getPaymentMethodList();

        foreach ($activePaymentMethods as $key => $payment) {
            $payment = new CmsConfigurationPayment();
            $payment->name = (string)$key;
            $ATSCConfigurationPayments[] = $payment;
        }
        return $ATSCConfigurationPayments;
    }
    /**
     * Retourne la liste des status des commandes
     *
     * @return CmsConfigurationOrderStatus[]
   */
    public static function getOrderStatuses()
    {
        global $resource;
        $ATSCConfigurationOrderStatuses = [];
 
        $connection= $resource->getConnection();
        $tableSOS = $resource->getTableName('sales_order_status');
        $sql = "SELECT sos.status, sos.label FROM `" . $tableSOS . "` as sos ";
        $rows = $connection->fetchAll($sql);
       
        foreach ($rows as $row) {
            $orderStatus = new CmsConfigurationOrderStatus();
            $orderStatus->code = $row['status'];
            $orderStatus->name =$row['label'];
            $ATSCConfigurationOrderStatuses[] = $orderStatus;
        }
        
        /*
        $tableSOSS = $resource->getTableName('sales_order_status_state');

        // SELECT DATA
        // je fait un tour pour les statut par défaut
        //$sql = "SELECT soss.status, soss.state, sos.label FROM `" . $tableSOSS . "` as soss inner join `" . $tableSOS . "` as sos on soss.status = sos.status ";
        $sql = "SELECT soss.status, soss.state, sos.label FROM `sales_order_status_state` as soss left join `sales_order_status` as sos on soss.status = sos.status WHERE soss.is_default =1  ";
        //$sql = "SELECT sos.status, sos.label FROM `" . $tableSOS . "` as sos ";
        $result = $connection->fetchAll($sql);
        $ATSCConfigurationOrderStatuses = [];
        foreach ($result as $id=>$row) {
            $orderStatus = new CmsConfigurationOrderStatus();
            $orderStatus->code = $row['state'] . '-' . $row['status'];
            $orderStatus->name = $row['state'] . ' [' . $row['label'] . ']';
            $ATSCConfigurationOrderStatuses[] = $orderStatus;
        }
        // j'ajoute lles statut personalisé
        $sql = "SELECT soss.status, '' as state, sos.label FROM `" . $tableSOSS . "` as soss inner join `" . $tableSOS . "` as sos on soss.status = sos.status  WHERE soss.is_default =0  ";
        $result = $connection->fetchAll($sql);
        foreach ($result as $id=>$row) {
            $orderStatus = new CmsConfigurationOrderStatus();
            $orderStatus->code = $row['state'] . '-' . $row['status'];
            $orderStatus->name = $row['state'] . ' [' . $row['label'] . ']';
            $ATSCConfigurationOrderStatuses[] = $orderStatus;
        }
        */
        return $ATSCConfigurationOrderStatuses;
    }
    /**
     * Retourne la liste des taux de taxes pour les articles
     *
     * @return CmsConfigurationTaxRule[]
     */
    public static function getTaxRules()
    {
        global $objectManager;
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $taxClassObj = $objectManager->create('Magento\Tax\Model\TaxClass\Source\Product');
        $ATSCConfigurationTaxRules = [];
        foreach ($taxClassObj->getAllOptions() as $row) {
            if (!is_object($row['label'])) {
                $taxRule = new CmsConfigurationTaxRule();
                $taxRule->code =  $row['value'];
                $taxRule->name = $row['label'];
                $ATSCConfigurationTaxRules[] = $taxRule;
            }
        }
        return $ATSCConfigurationTaxRules;
    }

    /**
     * Retourne la liste des taux de taxes
     *
     * @return CmsConfigurationTax[]
     */
    public static function getTaxes()
    {
        global $resource;
        $ATSCConfigurationTaxes = [];
        $connection= $resource->getConnection();
        $tableTax = $resource->getTableName('tax_calculation_rate');
        $sql = "SELECT * FROM `" . $tableTax . "`";
        $result = $connection->fetchAll($sql);
        foreach ($result as $row) {
            $ATSCtax = new CmsConfigurationTax();
            $ATSCtax->code = $row['tax_calculation_rate_id'];
            $ATSCtax->name = $row['code'] . ' (' . $row['rate'] . ')';
            $ATSCtax->rate = $row['rate'];
            $ATSCtax->active = 1;

            $ATSCConfigurationTaxes[] = $ATSCtax;
        }

        return $ATSCConfigurationTaxes;
    }

    /*
   * Retourne la liste des Zones
   */
    public static function getZones()
    {
        $ATSCConfigurationZones = [];

        $ATSCZone = new CmsConfigurationZone();
        $ATSCZone->code = 0;
        $ATSCZone->name = "All";

        $ATSCConfigurationZones[] = $ATSCZone;

        return $ATSCConfigurationZones;
    }

    /**
     * Retourne la liste des fabricants
     *
     * @return CmsConfigurationManufacturer[]
     */
    private static function getManufacturers()
    {
        $ATSCConfigurationManufacturers = [];
        //$manufacturers = Manufacturer::getManufacturers();
        //ici tu listes lles marques
        /*foreach ($manufacturers as $manufacturer) {
            $ATSCManufacturer = new CmsConfigurationManufacturer();
            $ATSCManufacturer->code = (string)$manufacturer['id_manufacturer'];
            $ATSCManufacturer->name = (string)$manufacturer['name'];
            $ATSCConfigurationManufacturers[] = $ATSCManufacturer;
        }*/
        return $ATSCConfigurationManufacturers;
    }

    /**
     * Retourne la liste des fournisseurs
     *
     * @return CmsConfigurationSupplier[]
     */
    private static function getSuppliers()
    {
        $ATSCConfigurationSuppliers = [];

        //ici tu listes lles marques
        /*foreach ($suppliers as $supplier) {
            $ATSCSupplier = new CmsConfigurationSupplier();
            $ATSCSupplier->code = (string)$supplier['id_supplier'];
            $ATSCSupplier->name = (string)$supplier['name'];

            $ATSCConfigurationSuppliers[] = $ATSCSupplier;
        }*/
        return $ATSCConfigurationSuppliers;
    }

    /**
     * Retourne la liste des transporteurs
     *
     * @return CmsConfigurationCarrier[]
     */
    public static function getCarriers()
    {
        //
        global $objectManager;
        $ATSCConfigurationCarriers = [];
        // les devises dans la boutique
        $shipping = $objectManager->create('\Magento\Shipping\Model\Config\Source\Allmethods');
        $shippings = $shipping->toOptionArray();

        foreach ($shippings as $shipping) {
            if (is_array($shipping['value'])) {
                foreach ($shipping['value'] as $carrier_row) {
                    $carrier = new CmsConfigurationCarrier();
                    $carrier->code =$carrier_row['value'];
                    $carrier->name = $carrier_row['label'];
                    $ATSCConfigurationCarriers[] = $carrier;
                }
            }
        }
        return $ATSCConfigurationCarriers;
    }
    /*
   * Retourne les unités
   */
    public static function getUnits()
    {
        return 1;
    }

    /**
     * Retourne la liste des devises
     *
     * @return CmsConfigurationCurrency[]
     */
    public static function getCurrencies()
    {
        global $objectManager;
        global $storeManager;

        // la devise par défaut de la boutique
        $currentCurrency =  $storeManager->getStore()->getCurrentCurrency()->getCode();

        // les devises dans la boutique
        $CurrencySymbol = $objectManager->get('\Magento\CurrencySymbol\Model\System\Currencysymbol');
        $currencies = $CurrencySymbol->getCurrencySymbolsData();

        foreach ($currencies as $code=>$currency) {
            $default = 0;
            if ($code == $currentCurrency) {
                $default = '1';
            } /* default */

            $ATSCCurrency = new CmsConfigurationCurrency();
            $ATSCCurrency->code = $code;
            $ATSCCurrency->name = $currency['displayName'];
            $ATSCCurrency->default =$default;

            $ATSCConfigurationCurrencies[] = $ATSCCurrency;
        }
        return $ATSCConfigurationCurrencies;
    }

    /**
     * Retourne la liste des pays
     *
     * @return CmsConfigurationCountry[]
     */
    public static function getCountries()
    {
        global $objectManager;
        $ATSCConfigurationCountries = [];
        //$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $AllowedCountries = $objectManager->get('\Magento\Directory\Model\AllowedCountries');
        $countries = $objectManager->get('\Magento\Directory\Model\Country');
        foreach ($AllowedCountries->getAllowedCountries() as $code) {
            $country = $countries->loadByCode($code);
            $country_name = $country->getName();
            $ATSCCountry = new CmsConfigurationCountry();
            $ATSCCountry->code = $code;
            $ATSCCountry->name = $country_name;

            $ATSCConfigurationCountries[] = $ATSCCountry;
        }

        return $ATSCConfigurationCountries;
    }

    /**
     * Retourne la liste des boutiques
     *
     * @return CmsConfigurationShop[]
     */
    public static function getShops()
    {
        global $resource;
        $ATSCConfigurationShops = [];
        $connection= $resource->getConnection();
        $tableStoreWebSite = $resource->getTableName('store_website');
        $tableStoreGroup = $resource->getTableName('store_group');
        $tableStore = $resource->getTableName('store');
        $sql = "SELECT
                    ms.`store_id`,
                    ms.`website_id`,
                    ms.`group_id`,
                    msw.`name` as 'website',
                    msg.`name` as 'group',
                    ms.`name` as 'store',
                    ms.`is_active`,
                    msg.`root_category_id`
                FROM
                    `" . $tableStore . "` as ms
                INNER JOIN `" . $tableStoreWebSite . "` as msw on msw.`website_id` = ms.`website_id`
                INNER JOIN `" . $tableStoreGroup . "` as msg on msg.`group_id` = ms.`group_id`

                WHERE ms.`store_id` <> 0";
        $result = $connection->fetchAll($sql);
        foreach ($result as $row) {
            $ATSCShop = new CmsConfigurationShop();
            $ATSCShop->code = $row["store_id"];
            $ATSCShop->name = $row["store"];

            $ATSCConfigurationShops[] = $ATSCShop;
        }
        return $ATSCConfigurationShops;
    }

    /**
     * Retourne la liste des caractéristiques des articles
     *
     * @return CmsConfigurationProductFeature[]
     */
    public static function getProductFeatures()
    {
        global $objectManager;
        global $resource;
        $ATSCConfigurationProductFeatures = [];
        $connection= $resource->getConnection();
        $tableTax = $resource->getTableName('eav_attribute');

        // les attribut dans la boutique
        $attribute = $objectManager->get('\Magento\Catalog\Model\ResourceModel\Eav\Attribute');
        $attributeInfo = $attribute->getCollection();
        $attributeInfo->addFieldToFilter('entity_type_id', 4);
        $attributeInfo->addFieldToFilter('is_user_defined', 1);
        $attributeInfo->addFieldToFilter('backend_type', ['varchar']);
        $attributeInfo->setOrder('attribute_code');

        foreach ($attributeInfo as $attributes) {
            //je ne séléctionne que les attribut affectés aux produits
            $sql = 'SELECT `attribute_code` FROM `' . $tableTax . '` WHERE `entity_type_id`="4" AND `is_user_defined` = 1 AND `attribute_code` ="' . (string)$attributes->getAttributeCode() . '"';
            $result = $connection->fetchAll($sql);
            //si j'ai un resultat affecté aux produit, et si j'ai un label
            if (!empty($result) && !empty($attributes->getData()['frontend_label'])) {
                $productFeature = new CmsConfigurationProductFeature();
                $productFeature->code = $attributes->getData()['attribute_id'];
                $productFeature->name = $attributes->getData()['frontend_label'];

                $ATSCConfigurationProductFeatures[] = $productFeature;
            }

            return $ATSCConfigurationProductFeatures;
        }
    }
}
