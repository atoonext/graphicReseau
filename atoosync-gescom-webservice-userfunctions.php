<?php

// customisation pour le compte de graphic réseau
use AtooNext\AtooSync\Cms\Order\CmsOrder;
use AtooNext\AtooSync\Erp\Customer\ErpCustomer;
use AtooNext\AtooSync\Erp\Product\ErpProduct;
use AtooNext\AtooSync\Erp\Product\ErpProductPrice;
use Magento\Framework\App\ObjectManager;

/**
 * @param ErpProduct $erpProduct
 */
function _customizeProduct($erpProduct) {
    /** @var ObjectManager $objectManager */
    $objectManager = ObjectManager::getInstance();
    /** @var ResourceConnection $resource */
    $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
    /** @var StoreManagerInterface $storeManager */
    $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
    /** @var ScopeConfigInterface $scopeConfig */
    $scopeConfig = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');
    
    $newProduct = false;
    /** @var StoreRepository $StoreRepository */
    $StoreRepository = $objectManager->create('Magento\Store\Model\StoreRepository');
    $websites = (explode(",",AtooSyncGesComTools::getConfig("atoosync_products", "create", "websites")));
    
    if((int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "websites") == 0){
        $stores = $StoreRepository->getList();
        $websiteIds = array();
        foreach ($stores as $storeRow) {
            $websiteId = $storeRow["website_id"];
            array_push($websiteIds, $websiteId);
        }
        $websites = $websiteIds;
    }
    $websites = array_unique($websites);
    $store = $storeManager->getStore();  // Get Store ID
    $success = false;
    
    $connection= $resource->getConnection();
    $tableName = $resource->getTableName('catalog_product_entity');
    $tableNameCat = $resource->getTableName('catalog_category_entity');
    
    $sql = "SELECT `entity_id` FROM " . $tableName." WHERE `sku` = '".(string)$erpProduct->reference."';";
    $product_id = (int)$connection->fetchOne($sql);
    if($product_id > 0){
       
        /** @var ProductRepository $productRepo */
        $productRepo = $objectManager->create('Magento\Catalog\Model\ProductRepository');
        /** @var Attribute $eavModel */
        $eavModel = $objectManager->create('Magento\Catalog\Model\ResourceModel\Eav\Attribute');
        $product = $productRepo->getById($product_id);
        $product->setWebsiteIds($websites);
        $stores = $StoreRepository->getList();
        $sqlSubstitute = "SELECT `entity_id` FROM " . $tableName." WHERE `sku` = '".(string)$erpProduct->substitute_product_key."';";
        $substitute_id = (int)$connection->fetchOne($sqlSubstitute);
        if($substitute_id > 0){
            $product->setData('substitute_product_sku',(string)$erpProduct->substitute_product_key);
        }
        
        $product->setData('supply_delays',$erpProduct->delivery_delay);
        $brandKey = strtolower($erpProduct->description);
        $tableNameBrand = $resource->getTableName('mageplaza_brand');
        $sqlBrand = "SELECT `option_id` FROM " . $tableNameBrand." WHERE `url_key` = '".(string)$brandKey."';";
        $BrandOptionId = (int)$connection->fetchOne($sqlBrand);
        $product->setData('brands',$BrandOptionId);
        $product->setData('brand_atoo',$erpProduct->description);
        $product->setVisibility(4);
        $product->save();
        foreach ($stores as $storeRow) {
            //test
            
            if($substitute_sku != ""){
                //ajout de code sku de substitution
                $product->setData('substitute_product_sku',(string)$substitute_sku);
                $product->save();
            }
            
        }

        $product->setPrice((float)$erpProduct->regular_price_tax_exclude);
        $product->setSpecialPrice((float)$erpProduct->price_tax_exclude);
        $product->setData('tax_class_id',(int)$erpProduct->tax_key);
        $product->setData('cost',$erpProduct->wholesale_price);
        $product->setData('ecotaxe',$erpProduct->ecotax);
        $product->save();
    }
}

/**
 * @param ErpCustomer $erpCustomer
 */
function _customizeErpCustomer($erpCustomer) {
    //inscription de tout les client dans le groupe B2B
    $erpCustomer->customer_group_key = "B2B";
}

/**
 * @param ErpCustomer $erpCustomer
 */
function _customizeCreateCustomer($erpCustomer) {
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
                AtooSyncCustomers::CreateAddress($customer, $address);
                if($address->address_type == "invoicing") {
                    $customer->setData('taxvat',$address->vat_number);
                }
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
        $id_systempay = $erpCustomer->atoosync_key;
        foreach($erpCustomer->customFields as $customField) {
            if($customField->name == "id_systempay") {
                $id_systempay =  $customField->value;
            }
        }
        
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
                    AtooSyncCustomers::CreateAddress($customer, $address);
                    if($address->address_type == "invoicing") {
                        $customer->setData('taxvat',$address->vat_number);
                    }
                }
            }
        }
        $CustomerRepository->save($customer);
        
        // force l'enregistrement du code client dans la base
        $sql = "Update " . $resource->getTableName('customer_entity') . " Set `atoosync_account` = '" . AtooSyncGesComTools::pSQL((string)$erpCustomer->atoosync_key) . "' where `email`= '" . AtooSyncGesComTools::pSQL((string)trim($erpCustomer->email)) . "';";
        $connection->query($sql);
        
        // Enregistre les contacts du client si présent
        if (!empty($erpCustomer->contacts)) {
            $success = createContacts($erpCustomer);
        }
        if($id_systempay != ""){
            //ajout de code sage dans l'attribut demandé
            $customer->setCustomAttribute('systempay_identifier',(string)$id_systempay);
            $CustomerRepository->save($customer);
        }
        
        $success = true;
    }
    
    return $success;
}

/**
 * @param CmsOrder $cmsOrder
 * @param $order_key
 */
function _customizeCmsOrder($cmsOrder, $order_key) {
    /** @var ObjectManager $objectManager */
    $objectManager = ObjectManager::getInstance();
    /** @var ResourceConnection $resource */
    $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
    
    $connection= $resource->getConnection();
    $tableNameOrder = $resource->getTableName('sales_order');
    
    // Trouve le ps_orders
    $query = 'SELECT `entity_id` FROM `' . $tableNameOrder . '` WHERE `increment_id` = "' . $cmsOrder->order_key . '"';
    
    $order_id = $connection->fetchOne($query);
    
    if ($order_id == $order_key) {
        
        /** @var \Magento\Sales\Model\Order $order */
        $order = $objectManager->create('\Magento\Sales\Model\Order')->load($order_key);
        $orderData = $order->getData();
        $query_relais = 'SELECT `relais_id` FROM `' . $tableNameOrder . '` WHERE `increment_id` = "' . $cmsOrder->order_key . '"';
        $relais_id = $connection->fetchOne($query_relais);
        // ajout du relais id dans le champs complement de l'adress de faturation
        
        if($relais_id != "") {
            $cmsOrder->invoice_address->address2 = 'Relais Pickup : '.$relais_id;
        }
        // gestion des frais de port et relais pickup dans champs libre
        $cmsOrder->addCustomField('No_Expedition',$relais_id);
        $cmsOrder->sage_analytic_code = 'WEB';
        
        $tableName = $resource->getTableName('customer_entity');
        $VatNumberSql = 'SELECT `taxvat` FROM `' . $tableName . '` WHERE `entity_id` = "' . (int)$orderData['customer_id'] . '"';
        $vatNumber = $connection->fetchOne($VatNumberSql);
        $cmsOrder->invoice_address->vat_number =$vatNumber;
        $cmsOrder->delivery_address->vat_number =$vatNumber;
        $cmsOrder->customer->vat_number =$vatNumber;
    }
}

/**
 * @param ErpProductPrice $erpProductPrice
 */
function _customizeProductPrice($erpProductPrice) {
    /** @var ObjectManager $objectManager */
    $objectManager = ObjectManager::getInstance();
    /** @var ResourceConnection $resource */
    $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
    /** @var StoreManagerInterface $storeManager */
    $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
    /** @var Attribute $eavModel */
    $eavModel = $objectManager->create('Magento\Catalog\Model\ResourceModel\Eav\Attribute');
    /** @var ProductRepository $productRepo */
    $productRepo = $objectManager->create('Magento\Catalog\Model\ProductRepository');
    $connection= $resource->getConnection();
    
    $succes = true;
    
    // Essaye de trouver le ou les articles avec la référence
    $tableName = $resource->getTableName('catalog_product_entity');
    $sql = "SELECT `entity_id` FROM " . $tableName . " WHERE `atoosync_key` = '" . (string)$erpProductPrice->reference . "'  AND `atoosync_gamme_key` ='' ;";
    $products_id = (array)$connection->fetchall($sql);
    if ($products_id) {
        foreach ($products_id as $row) {
            $StoreRepository = $objectManager->create('Magento\Store\Model\StoreRepository');
            $websites = (explode(",", AtooSyncGesComTools::getConfig("atoosync_products", "create", "websites")));
            
            if ((int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "websites") == 0) {
                $stores = $StoreRepository->getList();
                $websiteIds = [];
                foreach ($stores as $storeRow) {
                    $websiteId = $storeRow["website_id"];
                    array_push($websiteIds, $websiteId);
                }
                $websites = $websiteIds;
            }
            $websites = array_unique($websites);
            $store = $storeManager->getStore();  // Get Store ID
            $sql = 'DELETE FROM `catalog_product_entity_tier_price`
                WHERE `entity_id` = ' . $row['entity_id'];
            
            $connection->query($sql);
            $product = $productRepo->getById($row['entity_id']);
            
            $product->setWebsiteIds($websites);
            $stores = $StoreRepository->getList();
            foreach ($stores as $storeRow) {
                if (in_array($storeRow["website_id"], $websites)) {
                    $product->setStoreId($storeRow["store_id"]);
                    
                    if (AtooSyncGesComTools::getConfig("atoosync_products", "update", "price")==1) {
                        $product->setPrice((float)$erpProductPrice->regular_price_tax_exclude);
                        $product->setSpecialPrice((float)$erpProductPrice->price_tax_exclude);
                        $product->setData('tax_class_id',(int)$erpProductPrice->tax_key);
                        $product->setData('cost',$erpProductPrice->wholesale_price);
                        $product->setData('ecotaxe',$erpProductPrice->ecotax);
                        if ((int)AtooSyncGesComTools::getConfig("tax", "weee", "enable")==1) {
                            if ((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "ecotax", "ecotax") != 0) {
                                $attribute = $eavModel->load((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "ecotax", "ecotax"));
                                $product->setData('ecotaxe',$erpProductPrice->ecotax);
                            }
                        }
                    }
                    $productRepo->save($product);
                    
                    //je modifie mes prix spécifique par groupe
                    $tierPrices=[];
                    if (!empty($erpProductPrice->specificPrices)) {
                        foreach ($erpProductPrice->specificPrices as $specific_price) {
                            // je retrouve le groupe du prix spécifique ==> en version de base je ne gere pas le prix par client
                            if (!empty((string)$specific_price->erp_customer_group_key)) {
                                $createPrice = true;
                                
                                $tableNameGroup = $resource->getTableName('customer_group');
                                $sql = "SELECT `customer_group_id` FROM " . $tableNameGroup . " WHERE `atoosync_key` = '" . (string)$specific_price->erp_customer_group_key . "';";
                                $customer_group_id = (int)$connection->fetchOne($sql);
                                if ((int)$customer_group_id == 0) {
                                    $createPrice = false;
                                }
                                if ($createPrice) {
                                    // soit c'est une réduction du montant, soit c'est un prix fixe
                                    if ($specific_price->reduction_type =="percentage") {
                                        if ((int)$specific_price->price ==  0) {
                                            $tierPrices[] = [
                                                'website_id'       => $storeRow["website_id"],
                                                'cust_group'       => $customer_group_id,
                                                'price_qty'        => (int)$specific_price->from_quantity,
                                                'price_type'       => 'discount',
                                                'price' => '',
                                                'percentage_value' => (float)$specific_price->reduction
                                            ];
                                        } else {
                                            $reduction = 0;
                                            $reduction = (float)$specific_price->price-((float)$specific_price->price * ((float)$specific_price->reduction/100));
                                            $tierPrices[] = [
                                                'website_id'       => $storeRow["website_id"],
                                                'cust_group'       => $customer_group_id,
                                                'price_qty'        => (int)$specific_price->from_quantity,
                                                'price_type'       => 'fixed',
                                                'price' =>  (float)$reduction,
                                            ];
                                        }
                                    } else {
                                        $tierPrices[] = [
                                            'website_id'       => $storeRow["website_id"],
                                            'cust_group'       => $customer_group_id,
                                            'price_qty'        => (int)$specific_price->from_quantity,
                                            'price_type'       => 'fixed',
                                            'price' => (float)$specific_price->price
                                        ];
                                    }
                                }
                            }
                        }
                        
                        $product->setTierPrice((array)$tierPrices);
                        $product->save();
                    }
                }
            }
        }
        if (!empty($erpProductPrice->variations)) {
            foreach ($erpProductPrice->variations as $variation) {
                $sql = "SELECT `entity_id` FROM " . $tableName . " WHERE `atoosync_key` = '" . (string)$erpProductPrice->reference . "'  AND `atoosync_gamme_key` = '" . (string)$variation->atoosync_key . "' ;";
                
                $products_id = (array)$connection->fetchall($sql);
                if ($products_id) {
                    foreach ($products_id as $row) {
                        if ((float)$variation->price != 0) {
                            $price= (float)$erpProductPrice->regular_price_tax_exclude + (float)$variation->regular_price_tax_exclude;
                            $specialPrice= (float)$erpProductPrice->price_tax_exclude + (float)$variation->price_tax_exclude;
                        } else {
                            $price= (float)$erpProductPrice->price;
                        }
                        $product = $productRepo->getById($row['entity_id']);
                        if (AtooSyncGesComTools::getConfig("atoosync_products", "update", "price")==1) {
                            $product->setPrice((float)$price);
                            $product->setSpecialPrice((float)$specialPrice);
                            $product->setData('tax_class_id',(int)$erpProductPrice->tax_key);
                            $product->setData('cost',$erpProductPrice->wholesale_price);
                            $product->setData('ecotaxe',$erpProductPrice->ecotax);
                            if ((int)AtooSyncGesComTools::getConfig("tax", "weee", "enable")==1) {
                                if ((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "ecotax", "ecotax") != 0) {
                                    $attribute = $eavModel->load((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "ecotax", "ecotax"));
                                    $product->setData('ecotaxe',$erpProductPrice->ecotax);
                                }
                            }
                        }
                        $product->save();
                    }
                }
            }
        }
    }
    return $succes;
}

function _customizeSetOrderDeliveries($erpOrderDeliveries){
    $changeCarrier = false;
    
    /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();
        /** @var ResourceConnection $resource */
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection= $resource->getConnection();
        $succes = 0;

        // Load the order increment ID
        $order  = $objectManager->create('\Magento\Sales\Model\Order')->loadByIncrementId($erpOrderDeliveries->order_key);
        $convertOrder = $objectManager->create('Magento\Sales\Model\Convert\Order');
        $trackfactory = $objectManager->create('Magento\Sales\Model\Order\Shipment\TrackFactory');

        // Check if order can be shipped or has already shipped
        
        $generalCarrier = "";
        $generalTrackingNumber = "";
        $generalDeliveryDate = "";
        $generalDeliveryMethod = "";
        $generalCarrierKey = "";
        $deliveriesarray = [];
        
        
        
        
        foreach ($erpOrderDeliveries->details as $id => $detail) {
            $carrierCode = "";
            $carrierName = $detail->carrier_key;
            $shippings = AtooSyncConfiguration::getCarriers();
            foreach($shippings as $shipping){
                if($shipping->name == $carrierName){
                    $carrierCode = $shipping->code;
                }
            }
        
            $sql = "Update " . $resource->getTableName('sales_order') . " Set `shipping_description` = '" . $carrierName . "' ,
            `shipping_method` = '" . $carrierCode . "' 
             where `increment_id`= '" . $erpOrderDeliveries->order_key . "';";
            $connection->query($sql);
            
            /*$deliveriesarray[$detail->tracking_number]['delivery_number'] = $detail->delivery_number;
            $deliveriesarray[$detail->tracking_number]['delivery_date'] = $detail->delivery_date;
            $deliveriesarray[$detail->tracking_number]['delivery_method'] = $detail->delivery_method;
            $deliveriesarray[$detail->tracking_number]['tracking_number'] = $detail->tracking_number;
            $deliveriesarray[$detail->tracking_number]['carrier_key'] = $detail->carrier_key;
            $deliveriesarray[$detail->tracking_number]['carrier_code'] = $carrierCode;
            $deliveriesarray[$detail->tracking_number]['products'] = [];
            foreach ($detail->products as $productDelivery) {
                if($productDelivery->reference == "ZPORT" || $productDelivery->reference == "ZREMISE" || $productDelivery->reference == "ZDIVERS"){
                    //ne fait rien pour les produit divers
                }
                else{
                   if($detail->tracking_number == $productDelivery->tracking_number || $productDelivery->tracking_number==""){
                   $deliveriesarray[$detail->tracking_number]['products'][$productDelivery->reference]['qty'] = $productDelivery->quantity;
                   $deliveriesarray[$detail->tracking_number]['products'][$productDelivery->reference]['variation_key'] = $productDelivery->variation_key;
                   }
                   else {
                        $deliveriesarray[$productDelivery->tracking_number]['delivery_number'] = $detail->delivery_number;
                        $deliveriesarray[$productDelivery->tracking_number]['delivery_date'] = $productDelivery->delivery_date;
                        $deliveriesarray[$productDelivery->tracking_number]['delivery_method'] = $detail->delivery_method;
                        $deliveriesarray[$productDelivery->tracking_number]['tracking_number'] = $productDelivery->tracking_number;
                        $deliveriesarray[$productDelivery->tracking_number]['carrier_key'] = $detail->carrier_key;
                        $deliveriesarray[$productDelivery->tracking_number]['carrier_code'] = $carrierCode;
                        $deliveriesarray[$productDelivery->tracking_number]['products'][$productDelivery->reference]['qty'] = $productDelivery->quantity;
                        $deliveriesarray[$productDelivery->tracking_number]['products'][$productDelivery->reference]['variation_key'] = $productDelivery->variation_key;
                    }
                }
            }
           
        }
        if ($order->canShip()) {
            foreach($deliveriesarray as $id=>$delivery){
                //$shipment = $convertOrder->toShipment($order);
                // Loop through order items
                foreach ($order->getAllItems() as $orderItem) {
                    $itemData = $orderItem->getData();
                    if(array_key_exists($itemData['sku'],$delivery['products'])){
                        print_r("***");
                        print_r($itemData['sku']);
                        print_r("||");
                        print_r($id);
                        print_r("||");
                        print_r($delivery['products'][$itemData['sku']]['qty']);
                        print_r("***");
                        // Check if order item has qty to ship or is virtual
                        if (! $orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                            continue;
                        }
                        $qtyShipped = $orderItem->getQtyToShip();
                        // Create shipment item with qty
                        $shipmentItem = $convertOrder->itemToShipmentItem($orderItem)->setQty($delivery['products'][$itemData['sku']]['qty']);
                        // Add shipment item to shipment
                        $shipment->addItem($shipmentItem);
                    }
                   
                }

                // Register shipment
                $shipment->register();
                $data = [
                    'carrier_code' => $delivery['carrier_code'],
                    'title' => $delivery['carrier_key'],
                    'number' => $delivery['tracking_number'], // Replace with your tracking number
                ];

                $track = $trackfactory->create()->addData($data);
                $shipment->addTrack($track)->save();
                //$shipment->getOrder()->setIsInProcess(true);
                // Save created shipment and order
                $shipment->save();
                $shipment->getOrder()->save();
                // Send email
                $objectManager->create('Magento\Shipping\Model\ShipmentNotifier')->notify($shipment);
                $shipment->save();
                print_r($delivery);
            }
            print_r($order->getShippingDescription());
            print_r($order->getShippingMethod());
            
            $shipping = AtooSyncConfiguration::getCarriers();
            print_r($shipping);
        */   
        }
        $succes = 1;

        return $succes;
}
