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
*  @script    atoosync-gescom-webservice-customers.php
*
* --------------------------------------------------------------------------------
*  /!\ Ne peut être utilisé en dehors du programme Atoo-Sync /!\
* --------------------------------------------------------------------------------
*/

use AtooNext\AtooSync\Erp\Customer\ErpCustomer;
use AtooNext\AtooSync\Erp\Customer\ErpCustomerAddress;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;

class AtooSyncCustomers
{
    public static function dispatcher()
    {
        $result = true;

        switch (AtooSyncGesComTools::getValue('cmd')) {
            case 'createcustomers':
                $result = self::createCustomers(AtooSyncGesComTools::getValue('xml'));
                break;

            case 'setcustomeraccountnumber':
                $result = self::setCustomerAccountNumber(AtooSyncGesComTools::getValue('id'), AtooSyncGesComTools::getValue('account'));
                break;

            case 'disablecustomer':
                $result = self::disableCustomer(AtooSyncGesComTools::getValue('account'));
                break;
        }
        return $result;
    }
    /*
     * Enregistre le code client du client
     */
    private static function setCustomerAccountNumber($id_customer, $accountnumber)
    {
        $success = 0;
        return $success;
    }

    /*
     * Désactive le client à partir du code client de la gestion Commerciale
     */
    private static function disableCustomer($accountnumber)
    {
        return 1; // pas de gestion d'erreur sur cette fonction
    }

    /**
     * Créé ou met à jour les clients.
     *
     * @param string $xml
     * @return bool
     */
    private static function createCustomers($xml)
    {
        
        if (empty($xml)) {
            return 0;
        }

        $result = true;
        $xml = AtooSyncGesComTools::stripslashes($xml);
        $CustomersXML = AtooSyncGesComTools::loadXML($xml);

        foreach ($CustomersXML->customer as $CustomerXML) {
            if (!empty($CustomerXML)) {
                $erpCustomer = ErpCustomer::createFromXML($CustomerXML);
                customizeErpCustomer($erpCustomer);
                if (customizeCreateCustomer($erpCustomer) == false) {
                    if (self::createCustomer($erpCustomer) == false) {
                        $result = false;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Ajoute ou modifie un client dans le CMS
     * @param ErpCustomer $erpCustomer
     * @return bool
     */
    private static function createCustomer($erpCustomer)
    {
        if (!AtooSyncGesComTools::isEmail($erpCustomer->email)) {
            echo 'email '.$erpCustomer->email.' not valid for '.$erpCustomer->atoosync_key.' creation aborted';
            return false;
        }
        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();
        /** @var ResourceConnection $resource */
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        /** @var StoreManagerInterface $storeManager */
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        /** @var ScopeConfigInterface $scopeConfig */
        $scopeConfig = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');

        $store = $storeManager->getStore();  // Get Store ID
        $storeId =(int)AtooSyncGesComTools::getConfig("atoosync_customers", "creation", "mailfrom");
        $websiteId = 1;
        $account_share = (int)$scopeConfig->getValue('customer/account_share/scope', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if ($account_share == 1) {
            if ((int) AtooSyncGesComTools::getConfig("atoosync_customers", "creation", "website") > 0) {
                $websiteId = (int)AtooSyncGesComTools::getConfig("atoosync_customers", "creation", "website");
            }
        }

        $success = false;
        //ma variable de connexion à la bdd
        $connection= $resource->getConnection();
        $tableName = $resource->getTableName('customer_entity');

        // trouve le groupe par défaut correspondant à groupe du client
        $tableNameGroup = $resource->getTableName('customer_group');
        $sql = "SELECT `customer_group_id` FROM " . $tableNameGroup . " WHERE `atoosync_key` = '" . AtooSyncGesComTools::pSQL((string)$erpCustomer->customer_group_key) . "' OR `customer_group_code` = '" . AtooSyncGesComTools::pSQL((string)$erpCustomer->customer_group_key) . "';";
        $customer_group_id = (int)$connection->fetchOne($sql);
        // si le groupe client n'est pas créé dans magento
        if (!$customer_group_id) {
            $customer_group_id = 1;
        }
        // si le groupe client par défaut est demandé dans la config
        if (AtooSyncGesComTools::getConfig("atoosync_customers", "update", "defaultgroup") == 1) {
            $customer_group_id = 1;
        }

        /*
        je recherche mon client par sa clef attosync
        */
        /** @var Magento\Customer\Api\CustomerRepositoryInterface $CustomerRepository */
        $CustomerRepository = $objectManager->get('\Magento\Customer\Api\CustomerRepositoryInterface');

        //$customer = @$CustomerRepository->get(trim((string)$CustomerXML->email));
        $sql = "SELECT `entity_id` FROM " . $tableName . " WHERE `website_id` = " . $websiteId . "  and (`email` = '" . AtooSyncGesComTools::pSQL((string)$erpCustomer->email) . "' OR `atoosync_account` = '" . AtooSyncGesComTools::pSQL((string)$erpCustomer->atoosync_key) . "');";
        $customer_id = (int)$connection->fetchOne($sql);
        //mon client n'est pas trouver dans la bdd, je créé la base
        if ($customer_id == 0) {
            // je met en place mon mot de passe
            if (AtooSyncGesComTools::getConfig("atoosync_customers", "creation", "password") == 'AccountNumber') {
                if (strlen((string)$erpCustomer->atoosync_key)<=6) {
                    $passwd =str_pad((string)$erpCustomer->atoosync_key, 6, "_", STR_PAD_RIGHT);
                } else {
                    $passwd = (string)$erpCustomer->atoosync_key;
                }
            } else {
                $passwd = AtooSyncGesComTools::passwdGen(10);
            }
            $customerFactory = $objectManager->get('\Magento\Customer\Model\CustomerFactory');
            $customer = $customerFactory->create();
            $customer->setGroupId((int)$customer_group_id);
            $customer->setStoreId($storeId);
            $customer->setEmail(trim((string)$erpCustomer->email));
            $customer->setFirstname(trim((string)$erpCustomer->firstname));
            $customer->setLastname(trim((string)$erpCustomer->lastname));
            //$customer->setDob((string)$erpCustomer->birthday);
            //$customer->setPassword($passwd);
            if ((int)$websiteId > 0) {
                $customer->setWebsiteId($websiteId);
                $customer->setStoreId($storeId);
            }
            $customer->save();
            $customer_id = $customer->getId();
            // Enregistre les adresses
            if ($erpCustomer->addresses) {
                foreach ($erpCustomer->addresses as $address) {
                    self::CreateAddress($customer, $address);
                }
            }

            // Inscription à la newsletter
            if (AtooSyncGesComTools::getConfig("atoosync_customers", "creation", "newsletter") == 1) {
                $subscriberFactory = $objectManager->get('\Magento\Newsletter\Model\SubscriberFactory');
                $subscriber = $subscriberFactory->create();
                $subscriber->subscribeCustomerById($customer->getId());
                $subscriber->save();
            }

            if (AtooSyncGesComTools::getConfig("atoosync_customers", "creation", "sendmail") == 1) {
                $customer->sendNewAccountEmail('registered', '', $storeId);
                $customer->save();
            }

            //connexion à la bdd pour insérer ma clef atoosync
            $sql = "Update " . $resource->getTableName('customer_entity') . " Set `atoosync_account` = '" . AtooSyncGesComTools::pSQL((string)$erpCustomer->atoosync_key) . "' where `email`= '" . AtooSyncGesComTools::pSQL((string)trim($erpCustomer->email)) . "';";
            $connection->query($sql);

            $success = true;
            // $success = true;
        }
        //je viens de créer les eléméent essentiel de mon client, je met à jour ce qui n'est pas la base
        if ($customer_id > 0) {
            $customer = @$CustomerRepository->getById($customer_id);

            if ($customer->getId()) {
                $CustomerRepository->save($customer);
                $customer->setStoreId($storeId);
                $customer->setEmail(trim((string)$erpCustomer->email));
                //$customer->setDob((string)$erpCustomer->birthday);
            }

            if (AtooSyncGesComTools::getConfig("atoosync_customers", "update", "email") == 1) {
                $customer->setEmail(trim((string)$erpCustomer->email));
            }

            if (AtooSyncGesComTools::getConfig("atoosync_customers", "update", "name") == 1) {
                $customer->setLastname(trim((string)$erpCustomer->lastname));
                $customer->setFirstname(trim((string)$erpCustomer->firstname));
            }

            if (AtooSyncGesComTools::getConfig("atoosync_customers", "update", "defaultgroup") == 1) {
                $customer->setGroupId((int)$customer_group_id);
            }

            if (AtooSyncGesComTools::getConfig("atoosync_customers", "update", "address") == 1) {
                if ($erpCustomer->addresses) {
                    foreach ($erpCustomer->addresses as $address) {
                        self::CreateAddress($customer, $address);
                    }
                }
            }
            $CustomerRepository->save($customer);

            // force l'enregistrement du code client dans la base
            $sql = "Update " . $resource->getTableName('customer_entity') . " Set `atoosync_account` = '" . AtooSyncGesComTools::pSQL((string)$erpCustomer->atoosync_key) . "' where `email`= '" . AtooSyncGesComTools::pSQL((string)trim($erpCustomer->email)) . "';";
            $connection->query($sql);
    
            // Enregistre les contacts du client si présent
            if (!empty($erpCustomer->contacts)) {
                $success = self::createContacts($erpCustomer);
            }
            
            $success = true;
        }

        return $success;
    }
    
    /**
     * Ajoute ou modifie les contacts du client dans le CMS.
     *
     * @param ErpCustomer $erpCustomer
     * @return bool
     */
    private static function createContacts($erpCustomer)
    {
        foreach ($erpCustomer->contacts as $erpCustomerContact) {
            if (!AtooSyncGesComTools::isEmail($erpCustomerContact->email)) {
                echo 'email '.$erpCustomerContact->email.' not valid for '.$erpCustomerContact->atoosync_key.' creation aborted';
                return false;
            }
            /** @var ObjectManager $objectManager */
            $objectManager = ObjectManager::getInstance();
            /** @var ResourceConnection $resource */
            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            /** @var StoreManagerInterface $storeManager */
            $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
            /** @var ScopeConfigInterface $scopeConfig */
            $scopeConfig = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');
    
            $store = $storeManager->getStore();  // Get Store ID
            $storeId =(int)AtooSyncGesComTools::getConfig("atoosync_customers", "creation", "mailfrom");
            $websiteId = 1;
            $account_share = (int)$scopeConfig->getValue('customer/account_share/scope', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            if ($account_share == 1) {
                if ((int) AtooSyncGesComTools::getConfig("atoosync_customers", "creation", "website") > 0) {
                    $websiteId = (int)AtooSyncGesComTools::getConfig("atoosync_customers", "creation", "website");
                }
            }
    
            $success = false;
            //ma variable de connexion à la bdd
            $connection= $resource->getConnection();
            $tableName = $resource->getTableName('customer_entity');
    
            // trouve le groupe par défaut correspondant à groupe du client
            $tableNameGroup = $resource->getTableName('customer_group');
            $sql = "SELECT `customer_group_id` FROM " . $tableNameGroup . " WHERE `atoosync_key` = '" . AtooSyncGesComTools::pSQL((string)$erpCustomer->customer_group_key) . "' OR `customer_group_code` = '" . AtooSyncGesComTools::pSQL((string)$erpCustomer->customer_group_key) . "';";
            $customer_group_id = (int)$connection->fetchOne($sql);
            // si le groupe client n'est pas créé dans magento
            if (!$customer_group_id) {
                $customer_group_id = 1;
            }
            // si le groupe client par défaut est demandé dans la config
            if (AtooSyncGesComTools::getConfig("atoosync_customers", "update", "defaultgroup") == 1) {
                $customer_group_id = 1;
            }
    
            /*
            je recherche mon client par sa clef attosync
            */
            /** @var Magento\Customer\Api\CustomerRepositoryInterface $CustomerRepository */
            $CustomerRepository = $objectManager->get('\Magento\Customer\Api\CustomerRepositoryInterface');
    
            //$customer = @$CustomerRepository->get(trim((string)$CustomerXML->email));
            $sql = "SELECT `entity_id` FROM " . $tableName . " WHERE `website_id` = " . $websiteId . "  and (`email` = '" . AtooSyncGesComTools::pSQL((string)$erpCustomerContact->email) . "' OR `atoosync_account` = '" . AtooSyncGesComTools::pSQL((string)$erpCustomerContact->atoosync_key) . "');";
            $customer_id = (int)$connection->fetchOne($sql);
            //mon client n'est pas trouver dans la bdd, je créé la base
            if ($customer_id == 0) {
                // je met en place mon mot de passe
                if (AtooSyncGesComTools::getConfig("atoosync_customers", "creation", "password") == 'AccountNumber') {
                    if (strlen((string)$erpCustomerContact->atoosync_key)<=6) {
                        $passwd =str_pad((string)$erpCustomerContact->atoosync_key, 6, "_", STR_PAD_RIGHT);
                    } else {
                        $passwd = (string)$erpCustomerContact->atoosync_key;
                    }
                } else {
                    $passwd = AtooSyncGesComTools::passwdGen(10);
                }
                $customerFactory = $objectManager->get('\Magento\Customer\Model\CustomerFactory');
                $customer = $customerFactory->create();
                $customer->setGroupId((int)$customer_group_id);
                $customer->setStoreId($storeId);
                $customer->setEmail(trim((string)$erpCustomerContact->email));
                $customer->setFirstname(trim((string)$erpCustomerContact->firstname));
                $customer->setLastname(trim((string)$erpCustomerContact->lastname));
                //$customer->setDob((string)$erpCustomerContact->birthday);
                //$customer->setPassword($passwd);
                if ((int)$websiteId > 0) {
                    $customer->setWebsiteId($websiteId);
                    $customer->setStoreId($storeId);
                }
                $customer->save();
                $customer_id = $customer->getId();
                // Enregistre les adresses
                if ($erpCustomerContact->addresses) {
                    foreach ($erpCustomerContact->addresses as $address) {
                        self::CreateAddress($customer, $address);
                    }
                }
        
                // Inscription à la newsletter
                if (AtooSyncGesComTools::getConfig("atoosync_customers", "creation", "newsletter") == 1) {
                    $subscriberFactory = $objectManager->get('\Magento\Newsletter\Model\SubscriberFactory');
                    $subscriber = $subscriberFactory->create();
                    $subscriber->subscribeCustomerById($customer->getId());
                    $subscriber->save();
                }
        
                if (AtooSyncGesComTools::getConfig("atoosync_customers", "creation", "sendmail") == 1) {
                    $customer->sendNewAccountEmail('registered', '', $storeId);
                    $customer->save();
                }
        
                //connexion à la bdd pour insérer ma clef atoosync
                $sql = "Update " . $resource->getTableName('customer_entity') . " Set `atoosync_account` = '" . AtooSyncGesComTools::pSQL((string)$erpCustomerContact->atoosync_key) . "' where `email`= '" . AtooSyncGesComTools::pSQL((string)trim($erpCustomerContact->email)) . "';";
                $connection->query($sql);
        
                $success = true;
                // $success = true;
            }
            //je viens de créer les eléméent essentiel de mon client, je met à jour ce qui n'est pas la base
            if ($customer_id > 0) {
                $customer = @$CustomerRepository->getById($customer_id);
        
                if ($customer->getId()) {
                    $CustomerRepository->save($customer);
                    $customer->setStoreId($storeId);
                    $customer->setEmail(trim((string)$erpCustomerContact->email));
                    //$customer->setDob((string)$erpCustomerContact->birthday);
                }
        
                if (AtooSyncGesComTools::getConfig("atoosync_customers", "update", "email") == 1) {
                    $customer->setEmail(trim((string)$erpCustomerContact->email));
                }
        
                if (AtooSyncGesComTools::getConfig("atoosync_customers", "update", "name") == 1) {
                    $customer->setLastname(trim((string)$erpCustomerContact->lastname));
                    $customer->setFirstname(trim((string)$erpCustomerContact->firstname));
                }
        
                if (AtooSyncGesComTools::getConfig("atoosync_customers", "update", "defaultgroup") == 1) {
                    $customer->setGroupId((int)$customer_group_id);
                }
                $CustomerRepository->save($customer);
                // force l'enregistrement du code client dans la base
                $sql = "Update " . $resource->getTableName('customer_entity') . " Set `atoosync_account` = '" . AtooSyncGesComTools::pSQL((string)$erpCustomerContact->atoosync_key) . "' where `email`= '" . AtooSyncGesComTools::pSQL((string)trim($erpCustomerContact->email)) . "';";
                $connection->query($sql);
                
                // créé ou modifie les adresses du contact du client
                // doit être fait apres le customer->save();
                if (AtooSyncGesComTools::getConfig("atoosync_customers", "update", "address") == 1) {
                    if ($erpCustomerContact->addresses) {
                        foreach ($erpCustomerContact->addresses as $address) {
                            self::CreateAddress($customer, $address);
                        }
                    }
                }
                $success = true;
            }
        }
    }
    
    /**
     * Ajoute ou modifie une adresse sur un client
     *
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     * @param ErpCustomerAddress $erpCustomerAddress
     */
    public static function createAddress($customer, $ErpCustomerAddress)
    {
        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();
        /** @var ResourceConnection $resource */
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        
        if (self::isAddressValid($ErpCustomerAddress)) {
            //je test si mon address exiset déjà
            $connection= $resource->getConnection();
            $tableName = $resource->getTableName('customer_address_entity');
            $sql = "SELECT `entity_id` FROM " . $tableName . " WHERE `atoosync_key` = '" . AtooSyncGesComTools::pSQL((string)$ErpCustomerAddress->atoosync_key) . "';";
            $address_id = (int)$connection->fetchOne($sql);

            //si mon adresse n'existe pas, je la crée
            if ((int)$address_id == 0) {
                /** @var  \Magento\Customer\Model\AddressFactory $address */
                $address = $objectManager->get('\Magento\Customer\Model\AddressFactory');
                $address = $address->create();
                
                
                $address->setCustomerId($customer->getId());
                $address->setFirstname(trim(substr((string)($ErpCustomerAddress->firstname), 0, 255)));
                $address->setLastname(trim(substr((string)($ErpCustomerAddress->lastname), 0, 255)));

                $address->setCompany(trim(substr((string)($ErpCustomerAddress->company), 0, 255)));
                $address->setIsDefaultBilling($customer->getId());
                $address->setCountryId(self::getIdCountry((string)$ErpCustomerAddress->country));

                // je remplit mes champ obligatoire
                $street = [];
                $street[] = (string)$ErpCustomerAddress->address1;
                if (!empty((string)($ErpCustomerAddress->address2))) {
                    $street[] .= (string)($ErpCustomerAddress->address2);
                }
                $address->setStreet($street);
                $address->setPostcode(trim(substr((string)($ErpCustomerAddress->postcode), 0, 255)));
                $address->setCity(trim(substr((string)($ErpCustomerAddress->city), 0, 255)));
                $address->setTelephone($ErpCustomerAddress->phone);
                $address->setCountryId(self::getIdCountry((string)$ErpCustomerAddress->country));
                $address->setVatId((string)$ErpCustomerAddress->vat_number);
                $address->save();
                if ($address->getId() > 0) {
                    //je vérifie que mon addresse est de type billing ou shipping puis je l'applique au customer
                    if ($ErpCustomerAddress->address_type == 'Delivery') {
                        $address->setIsDefaultShipping($customer->getId());
                    }
                    if ($ErpCustomerAddress->address_type == 'Invoicing') {
                        $address->setIsDefaultBilling($customer->getId());
                    }
                    $sql = "Update ".$tableName." Set `atoosync_key` = '".AtooSyncGesComTools::pSQL((string)$ErpCustomerAddress->atoosync_key)."' where `entity_id`= '".AtooSyncGesComTools::pSQL(trim((string)$address->getId()))."';";
                    $connection->query($sql);
                }
            } else {
                $addressRepository = $objectManager->get('\Magento\Customer\Api\AddressRepositoryInterface');
                $address = @$addressRepository->getById($address_id);
                $address->setCustomerId($customer->getId());

                if (AtooSyncGesComTools::getConfig("atoosync_customers", "update", "address") == 1) {
                    $address->setFirstname(trim(substr((string)($ErpCustomerAddress->firstname), 0, 255)));
                    $address->setLastname(trim(substr((string)($ErpCustomerAddress->lastname), 0, 255)));
                    $address->setCompany(trim(substr((string)($ErpCustomerAddress->company), 0, 255)));
                    $address->setIsDefaultBilling($customer->getId());
                    $address->setCountryId(trim(substr((string)($ErpCustomerAddress->company), 0, 32)));

                    // je remplit mes champ obligatoire
                    $street = [];
                    $street[] = (string)$ErpCustomerAddress->address1;
                    if (!empty((string)($ErpCustomerAddress->address2))) {
                        $street[] .= (string)($ErpCustomerAddress->address2);
                    }
                    $address->setStreet($street);
                    $address->setPostcode(trim(substr((string)($ErpCustomerAddress->postcode), 0, 255)));
                    $address->setCity(trim(substr((string)($ErpCustomerAddress->city), 0, 255)));
                    $address->setTelephone((string)$ErpCustomerAddress->phone);
                    $address->setCountryId(self::getIdCountry((string)$ErpCustomerAddress->country));
                    $address->setVatId((string)$ErpCustomerAddress->vat_number);
                }
                $addressRepository->save($address);
            }
            // sinon je test la variable de config pour la mise a jour
        }
        $success = true;
    }

    /*
   * function qui test si l'adresse est valide
   */
    private static function isAddressValid($ErpCustomerAddress)
    {
        $text = trim((string)($ErpCustomerAddress->address1));
        if (empty($text)) {
            return false;
        }

        $text = trim((string)($ErpCustomerAddress->postcode));
        if (empty($text)) {
            return false;
        }

        $text = trim((string)($ErpCustomerAddress->city));
        if (empty($text)) {
            return false;
        }
        $text = trim((string)($ErpCustomerAddress->phone));
        if (empty($text)) {
            return false;
        }
        //
        /*  if(self::getIdCountry((string)$xml->country) ==false)
          {
              return false;
          }*/
        return true;
    }

    private static function getIdCountry($pays)
    {
        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();

        $countryHelper = $objectManager->get('Magento\Directory\Model\Config\Source\Country');
        $countryFactory = $objectManager->get('Magento\Directory\Model\CountryFactory');

        $countries = $countryHelper->toOptionArray(); //Load an array of countries
        foreach ($countries as $countryKey => $country) {
            if ($country['value'] != '') { //Ignore the first (empty) value
                if (strtoupper($country['label']) == strtoupper($pays)) {
                    return $country['value'];
                }
            }
        }
        return false;
    }
}
