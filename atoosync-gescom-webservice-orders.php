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
*  @script    atoosync-gescom-webservice-orders.php
*
* --------------------------------------------------------------------------------
*  /!\ Ne peut être utilisé en dehors du programme Atoo-Sync /!\
* --------------------------------------------------------------------------------
*/

use AtooNext\AtooSync\Cms\Order\CmsOrder;
use AtooNext\AtooSync\Cms\Order\CmsOrderAddress;
use AtooNext\AtooSync\Cms\Order\CmsOrderCustomer;
use AtooNext\AtooSync\Cms\Order\CmsOrderDiscount;
use AtooNext\AtooSync\Cms\Order\CmsOrderPayment;
use AtooNext\AtooSync\Cms\Order\CmsOrderProduct;
use AtooNext\AtooSync\Erp\Order\ErpOrderDeliveries;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Shipping\Model\Config\Source\Allmethods;

class AtooSyncOrders
{
    public static $arrayStatus;
    /** @var bool */
    private static $productVATDiscountError = false;

    public static function dispatcher()
    {
        $result= true;

        switch (AtooSyncGesComTools::getValue('cmd')) {
            case 'getorders':
                $start = AtooSyncGesComTools::getValue('start');
                $end = AtooSyncGesComTools::getValue('end');
                $status = AtooSyncGesComTools::getValue('status');
                $shops = AtooSyncGesComTools::getValue('shops');
                $reload = 'no';
                $all = 'no';
                if (AtooSyncGesComTools::getIsset('reload')) {
                    $reload = AtooSyncGesComTools::getValue('reload');
                }
                if (AtooSyncGesComTools::getIsset('all')) {
                    $all = AtooSyncGesComTools::getValue('all');
                }
                $result=self::getOrders($start, $end, $status, $shops, $reload, $all);
                break;

            case 'setordercreated':
                $result=self::markOrderAsTransfered(AtooSyncGesComTools::getValue('id'));
                break;

            case 'setorderstatus':
                $result=self::setOrderStatus(AtooSyncGesComTools::getValue('id'), AtooSyncGesComTools::getValue('status'));
                break;

            case 'setorderdocumentnumber':
                $result=self::setOrderDocumentNumber(AtooSyncGesComTools::getValue('id'), AtooSyncGesComTools::getValue('number'));
                break;

            case 'setorderdeliverydate':
                $result=self::setOrderDeliveryDate(AtooSyncGesComTools::getValue('id'), AtooSyncGesComTools::getValue('date'));
                break;

            case 'setordershippingnumber':
                $result=self::setOrderShippingNumber(AtooSyncGesComTools::getValue('id'), AtooSyncGesComTools::getValue('number'));
                break;

            case 'setorderdeliveries':
                $result=self::setOrderDeliveries(AtooSyncGesComTools::getValue('xml'));
                break;
        }
        return $result;
    }
    /*
     * Construit le XML de la liste des commandes
     * from   = date de début de la période
     * to         = date de fin de la période a générer
     * status     = les id_order_state des commandes séparés par des |
     * shops  = les id_shop des commandes séparés par des ,
     * reload     = ignore les commandes déjà transférées
     * all        = transfert toutes les commandes
     */
    private static function getOrders($from, $to, $status, $shops, $reload = 'no', $all = 'no')
    {
        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();
        /** @var ResourceConnection $resource */
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        //$connection= $resource->getConnection();
        $tableNameOrder = $resource->getTableName('sales_order');
        $connection= $resource->getConnection();

        /** @var CmsOrder[] $cmsOrders */
        $cmsOrders = customizeGetCmsOrders($from, $to, $status, $shops, $reload, $all);
        if (empty($cmsOrders)) {
            $cmsOrders = array();
            /* la periode */
            if ((string)$all == "yes") {
                $startdate= '1970-01-01 00:00:00';
                $enddate= '2099-12-13 23:59:59';
            } else {
                $startdate= $from;
                $enddate= $to;
            }

            /* Les statuts des commandes à transférer */
            if (!empty($status)) {
                $statuslist = explode("|", $status);
            } else {
                $statuslist = [];
            }

            //AtooSyncOrders::$arrayStatus = [];
            AtooSyncOrders::$arrayStatus = $statuslist;

            // Requête d'interrogation des commandes
            //$query = 'SELECT `entity_id`, `state`, `status` FROM `' . $tableNameOrder . '` WHERE `created_at` BETWEEN "' . AtooSyncGesComTools::pSQL($startdate) . '" AND "' . AtooSyncGesComTools::pSQL($enddate) . '"';
            $query = 'SELECT `entity_id`, `status` FROM `' . $tableNameOrder . '` WHERE `created_at` BETWEEN "' . AtooSyncGesComTools::pSQL($startdate) . '" AND "' . AtooSyncGesComTools::pSQL($enddate) . '"';

            if ((string)($reload) != 'yes') {
                $query .= " AND (`atoosync_transfered`='0' or `atoosync_transfered` IS NULL)";
            }

            //$query .= " AND (`entity_id` = 39)";

            /* tri */
            $query .= " ORDER BY `created_at`, `entity_id`";

            $orders = $connection->fetchAll($query);

            // modifie l'entete selon la configuration.
            if (AtooSyncGesComTools::getConfig("atoosync_products", "create", "websites") == 'XML') {
                header("Content-type: text/xml");
            } else {
                header("Content-type: text/html");
            }

            $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\r\n";
            $xml .= "<?xml-stylesheet href=\"fake.xsl\" type=\"text/xsl\"?>\r\n";
            $xml .= "<orders>\r\n";

            foreach ($orders as $row) {
                // Si le dernier status de la commande est dans ceux demandés
                // on enleve le state et ne gère que le status
                //$order_status = $row['state'] . '-' . $row['status'];
                $order_status = $row['status'];
                if (in_array($order_status, AtooSyncOrders::$arrayStatus)) {
                    // ajoute la commande au XML des commandes
                    $xml .= self::getOrder($row['entity_id']);
                }
            }
            $xml .= "</orders>";

            header("Content-type: text/xml");
        }

        echo AtooSyncGesComTools::formatXml($xml);
        return true;
    }

    /**
     * @param $id_order
     * @return CmsOrder;
     */
    public static function getOrder($id_order)
    {
        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();
        /** @var ResourceConnection $resource */
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');

        // la précision des prix
        $precision = (int)AtooSyncGesComTools::getConfig("atoosync_orders", "create", "round");
        if ($precision == '') {
            $precision= 2;
        }

        $connection= $resource->getConnection();
        $tableNameStatus = $resource->getTableName('sales_order_status_state');

        $state = 0;

        $connection= $resource->getConnection();
        $tableNameOrder = $resource->getTableName('sales_order');

        // Trouve le ps_orders
        $query = 'SELECT `entity_id` FROM `' . $tableNameOrder . '` WHERE `entity_id` = "' . $id_order . '"';
        $order_id = (array)$connection->fetchOne($query);
        if ($order_id) {
            /** @var \Magento\Sales\Model\Order $order*/
            $order  = $objectManager->create('\Magento\Sales\Model\Order')->load($id_order);
            $orderData = $order->getData();

            self::$productVATDiscountError = self::orderHasDiscountError($order);

            $orderInvoice = $order->hasInvoices();
            $orderInvoiceDate = "";

            $ordershipment = $order->hasShipments();
            $ordershipmentDate = "";

            if (AtooSyncGesComTools::getConfig("tax", "calculation", "based_on") == "billing") {
                $address = $order->getBillingAddress();
            } else {
                $address = $order->getShippingAddress();
            }

            $address_data = $address->getData();
            foreach ($order->getInvoiceCollection() as $invoice) {
                if ((int)$invoice['increment_id']==(int)$orderInvoice) {
                    $orderInvoiceDate =$invoice['created_at'];
                }
            }

            foreach ($order->getShipmentsCollection() as $shipment) {
                if ((int)$shipment['increment_id']==(int)$ordershipment) {
                    $ordershipmentDate =$shipment['created_at'];
                }
            }
            // tax class for shipping
            $tax_class_name = "";
            $taxClassObj = $objectManager->create('Magento\Tax\Model\TaxClass\Source\Product');
            foreach ($taxClassObj->getAllOptions() as $row) {
                if (AtooSyncGesComTools::getConfig("tax", "classes", "shipping_tax_class") == $row['value']) {
                    $tax_class_name = $row['label'];
                }
            }

            // Les frais de port et transporteur
            $shippingDetails = self::getShippingDetails($order);

            $id_zone = 1; // par défaut la zone_id = 1 puisque Magento ne gére pas les zones
            $cmsOrder = new CmsOrder();
            $cmsOrder->price_precision = $precision;
            $cmsOrder->order_type = CmsOrder::ORDER_TYPE_SALE;
            $cmsOrder->shop_key = $order->getStoreId();
            $cmsOrder->order_key = $order->getRealOrderId();
            $cmsOrder->cart_key = $orderData['quote_id'];
            $cmsOrder->zone_key = $id_zone;
            $cmsOrder->country_key = $address_data['country_id'];
            $cmsOrder->order_date = $orderData['created_at'];
            $cmsOrder->order_status = $orderData['status'];
            $cmsOrder->order_number = $order->getRealOrderId();
            $cmsOrder->order_reference = $order->getRealOrderId();
            $cmsOrder->delivery_date = $ordershipmentDate;
            $cmsOrder->invoice_date = $orderInvoiceDate;
            $cmsOrder->invoice_number = sprintf('%06d', $order->hasInvoices());

            $cmsOrder->currency_key = $orderData['order_currency_code'];
            $cmsOrder->currency_rate = $orderData['base_to_order_rate'];

            $cmsOrder->carrier_key = $shippingDetails['id_carrier'];
            $cmsOrder->carrier_name = $shippingDetails['carrier_name'];
            $cmsOrder->shipping_tax_name = $tax_class_name;
            $cmsOrder->shipping_tax_rate = $shippingDetails['shipping_tax_rate'];
            $cmsOrder->shipping_tax_excl = $shippingDetails['shipping_tax_excl'];
            $cmsOrder->shipping_tax_incl = $shippingDetails['shipping_tax_incl'];
            $cmsOrder->shipping_tax = $shippingDetails['shipping_tax_incl'] - $shippingDetails['shipping_tax_excl'];

            $cmsOrder->shipping_discount_tax_incl = $shippingDetails['shipping_discount_tax_incl'];
            $cmsOrder->shipping_discount_tax_excl = $shippingDetails['shipping_discount_tax_excl'];
            $cmsOrder->shipping_final_tax_excl = $shippingDetails['shipping_final_tax_excl'];
            $cmsOrder->shipping_final_tax_incl = $shippingDetails['shipping_final_tax_incl'];
            $cmsOrder->shipping_final_tax = $shippingDetails['shipping_final_tax_incl'] - $shippingDetails['shipping_final_tax_excl'];

            $cmsOrder->payment_name = $order->getPayment()->getMethod();
            // transporteur
            // emballage
            if (self::$productVATDiscountError) {
                $cmsOrder->total_discounts_tax_excl = abs($orderData['discount_amount']);
                $cmsOrder->total_discounts_tax_incl = abs($orderData['discount_amount']);
                $cmsOrder->total_discounts_tax = $cmsOrder->total_discounts_tax_incl  - $cmsOrder->total_discounts_tax_excl;
            }

            $cmsOrder->total_paid = $orderData['base_grand_total'];
            $cmsOrder->total_paid_real = $orderData['base_total_paid'];
            $cmsOrder->total_products_tax_excl = $orderData['base_subtotal'];
            $cmsOrder->total_products_tax_incl = $orderData['base_subtotal_incl_tax'];
            $cmsOrder->total_products_tax = $cmsOrder->total_products_tax_incl - $cmsOrder->total_products_tax_excl;
            $cmsOrder->total_tax_incl = $orderData['base_grand_total'];
            $cmsOrder->total_tax = $orderData['tax_amount'];
            $cmsOrder->total_tax_excl = $cmsOrder->total_tax_incl - $cmsOrder->total_tax;

            $cmsOrder->messages = self::getOrderMessages($order);

            $cmsOrder->customer = self::getCustomer($order);
            $cmsOrder->delivery_address = self::getDeliveryAddress($order);
            $cmsOrder->invoice_address = self::getInvoiceAddress($order);
            $cmsOrder->discounts = self::getDiscounts($order);
            $cmsOrder->payments = self::getPayments($order);
            $cmsOrder->products = self::getProducts($order);

            /** Customise l'objet de la commande si besoin */
            customizeCmsOrder($cmsOrder, $order->getEntityId());
            return $cmsOrder->getXML();
        }
        return '';
    }

    /*
     * @param \Magento\Sales\Model\Order $order
    * Retourne le transporteur de la commande
    **/
    public static function getShippingDetails($order)
    {
         global $objectManager;
         // les devises dans la boutique
        $shippingSource = $objectManager->create('\Magento\Shipping\Model\Config\Source\Allmethods');
        $shippingsSource = $shippingSource->toOptionArray();


        //$shipping_taxrate=0;
        $orderData = $order->getData();
        $shipping_taxrate = $order->getBaseShippingTaxAmount();
        //$shipping = $order->getShippingAmount();
        $shipping_wt = (float)$orderData['shipping_incl_tax'] - (float)$orderData['shipping_discount_amount'];
        $shipping = $shipping_wt - (float)$orderData['shipping_tax_amount'];
        //$shipping_wt = $shipping + $orderData['shipping_tax_amount'];

        if ((float)$shipping_wt > (float)$shipping) {
            $shipping_taxrate = (100 / $orderData['shipping_amount']) * ($orderData['shipping_incl_tax'] - $orderData['shipping_amount']);
        }
        $shippingName = "";
         foreach ($shippingsSource as $shippingArray) {
            if (is_array($shippingArray['value'])) {
                foreach ($shippingArray['value'] as $carrier_row) {
                    if($carrier_row['value'] == $order->getShippingMethod()){
                        $shippingName = $carrier_row['label'];
                    }
                }
            }
        }

        //$carrier_name ='';
        $shipping_tax_name ='';
        //$id_carrier =0;
        $shippingDetails = [];
        $shippingDetails['id_carrier'] = $order->getShippingMethod();
        $shippingDetails['carrier_name'] = $shippingName; // vide dans WooCommerce
        $shippingDetails['shipping_tax_name'] = $shipping_tax_name;
        $shippingDetails['shipping_tax_excl'] = $orderData['shipping_amount'];
        $shippingDetails['shipping_tax_rate'] = $shipping_taxrate;
        $shippingDetails['shipping_tax_incl'] = $orderData['shipping_incl_tax'];
        $shippingDetails['shipping_discount_tax_excl'] = $orderData['shipping_discount_amount'] / (1+ ($shipping_taxrate / 100));
        $shippingDetails['shipping_discount_tax_incl'] = $orderData['shipping_discount_amount'];
        $shippingDetails['shipping_final_tax_excl'] = $shipping;
        $shippingDetails['shipping_final_tax_incl'] =  $shipping_wt;

        return $shippingDetails;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     */
    private static function getCarrierTaxName($order)
    {
        // LLa fonction n'est pas utilisée dans magento
        // les classe de taxe utilisé pour le transport sont dans la fonction getShippingDetails
    }

    /**
     * Retourne l'emballage de la commande
     * @param \Magento\Sales\Model\Order $order
    **/
    public static function getWrappingDetails($order)
    {
        // pas de gift wrap de base dans magento
    }
    private static function getWrappingTaxName($order)
    {
        // pas de gift wrap de base dans magento
    }

    /**
     * Renseigne les informations du client de la commande dans l'objet CmsOrder
     *
     * @param \Magento\Sales\Model\Order $order
     * @return CmsOrderCustomer L'objet du client de la commande
     */
    public static function getCustomer($order)
    {
        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();

        $CustomerRepository = $objectManager->get('\Magento\Customer\Api\CustomerRepositoryInterface');
        $subsciberRepo = $objectManager->get('\Magento\Newsletter\Model\Subscriber');
        $orderData = $order->getData();

        $customer_id=0;
        // Vérifie si le client existe
        if ((int)$orderData['customer_id'] > 0) {
            $customer = @$CustomerRepository->getById((int)$orderData['customer_id']);
            if ($customer) {
                $customer_id = $customer->getId();
            }
        }
        $cmsOrderCustomer = new CmsOrderCustomer();
        $cmsOrderCustomer->erp_customer_account = self::getERPCustomerAccount($order);

        if ($customer_id > 0) {
            $civility= '';
            if ($orderData['customer_gender'] == 1) {
                $civility= 'M.';
            }
            if ($orderData['customer_gender'] == 2) {
                $civility= 'Mme';
            }
            $newsletter = 0;
            $checkSubscriber = $subsciberRepo->loadByCustomerId($customer_id);
            if ($checkSubscriber->isSubscribed()) {
                $newsletter = 1;
            }

            $cmsOrderCustomer->customer_key = $customer->getId();
            $cmsOrderCustomer->firstname = $customer->getFirstName();
            $cmsOrderCustomer->lastname = $customer->getLastName();
            $cmsOrderCustomer->email = $customer->getEmail();
            $cmsOrderCustomer->civility = $civility;
            $cmsOrderCustomer->birthday = $customer->getDob();
            $cmsOrderCustomer->newsletter = $newsletter;
            $invoiceAddress = self::getInvoiceAddress($order);
            $cmsOrderCustomer->vat_number = $invoiceAddress->vat_number;
        } else {
            $addressBilling = $order->getBillingAddress();
            $address_data_B = $addressBilling->getData();
            $customer_firstname = $address_data_B['firstname'];
            $customer_lastname = $address_data_B['lastname'];
            $id_customer= '999999999';

            $cmsOrderCustomer->customer_key = $id_customer;
            $cmsOrderCustomer->firstname = $customer_firstname;
            $cmsOrderCustomer->lastname = $customer_lastname;
            $cmsOrderCustomer->email = $orderData['customer_email'];
        }
        return $cmsOrderCustomer;
    }

    /**
     * Retourne l'adresse de livraison du client de la commande
     *
     * @param \Magento\Sales\Model\Order $order
     * @return CmsOrderAddress
     */
    public static function getDeliveryAddress($order)
    {
        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();

        $repoCountry = $objectManager->get('\Magento\Directory\Model\CountryFactory');
        $orderData = $order->getData();

        $civility= '';
        if ($orderData['customer_gender'] == 1) {
            $civility= 'M.';
        }
        if ($orderData['customer_gender'] == 2) {
            $civility= 'Mme';
        }

        // Adresse de facturation
        $addressShipping = $order->getShippingAddress();
        $address_data_S = $addressShipping->getData();
        $countryS = $repoCountry->create()->loadByCode($address_data_S['country_id']);

        $cmsOrderAddress = new CmsOrderAddress();
        $cmsOrderAddress->name =$address_data_S['address_type'];
        $cmsOrderAddress->company =$address_data_S['company'];
        $cmsOrderAddress->civility = $civility;
        $cmsOrderAddress->lastname = $address_data_S['lastname'];
        $cmsOrderAddress->firstname = $address_data_S['firstname'];
        $cmsOrderAddress->address1 = $addressShipping->getStreetLine(1);
        $cmsOrderAddress->address2 = $addressShipping->getStreetLine(2);
        $cmsOrderAddress->address3 = $addressShipping->getStreetLine(3);
        $cmsOrderAddress->postcode = $address_data_S['postcode'];
        $cmsOrderAddress->city = $address_data_S['city'];
        $cmsOrderAddress->state = $addressShipping->getRegion();
        $cmsOrderAddress->country = $countryS->getName();
        $cmsOrderAddress->country_iso_code = $address_data_S['country_id'];
        $cmsOrderAddress->phone = $address_data_S['telephone'];
        $cmsOrderAddress->vat_number = $address_data_S['vat_id'];

        /** Champ supplémentaire gestion commerciale */
        $cmsOrderAddress->contact_civility = $civility;
        $cmsOrderAddress->email = $orderData['customer_email'];

        return $cmsOrderAddress;
    }

    /**
     * Retourne l'adresse de facturation du client de la commande
     *
     * @param \Magento\Sales\Model\Order $order
     * @return CmsOrderAddress
     */
    public static function getInvoiceAddress($order)
    {
        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();

        $repoCountry = $objectManager->get('\Magento\Directory\Model\CountryFactory');
        $orderData = $order->getData();

        $civility= '';
        if ($orderData['customer_gender'] == 1) {
            $civility= 'M.';
        }
        if ($orderData['customer_gender'] == 2) {
            $civility= 'Mme';
        }

        $addressBilling = $order->getBillingAddress();
        if (empty($addressBilling)) {
            $addressBilling = $order->getShippingAddress();
        }
        $address_data_B = $addressBilling->getData();
        $countryB = $repoCountry->create()->loadByCode($address_data_B['country_id']);

        $cmsOrderAddress = new CmsOrderAddress();

        $cmsOrderAddress->name =$address_data_B['address_type'];
        $cmsOrderAddress->civility = $civility;
        $cmsOrderAddress->lastname = $address_data_B['lastname'];
        $cmsOrderAddress->company =$address_data_B['company'];
        $cmsOrderAddress->firstname = $address_data_B['firstname'];
        $cmsOrderAddress->address1 = $addressBilling->getStreetLine(1);
        $cmsOrderAddress->address2 = $addressBilling->getStreetLine(2);
        $cmsOrderAddress->address3 = $addressBilling->getStreetLine(3);
        $cmsOrderAddress->postcode = $address_data_B['postcode'];
        $cmsOrderAddress->city = $address_data_B['city'];
        $cmsOrderAddress->state = $addressBilling->getRegion();
        $cmsOrderAddress->country = $countryB->getName();
        $cmsOrderAddress->country_iso_code = $address_data_B['country_id'];
        $cmsOrderAddress->phone = $address_data_B['telephone'];
        $cmsOrderAddress->vat_number = $address_data_B['vat_id'];

        /** Champ supplémentaire gestion commerciale */
        $cmsOrderAddress->contact_civility = $civility;
        $cmsOrderAddress->email = $orderData['customer_email'];

        return $cmsOrderAddress;
    }

    /**
     * Retourne les paiements de la commande
     * @param \Magento\Sales\Model\Order $order
     * @return CmsOrderPayment[]
     */
    private static function getPayments($order)
    {
        $additionalData = $order->getPayment()->getAdditionalInformation();
        $orderData = $order->getData();
        $cmsOrderPayment = new CmsOrderPayment();
        $cmsOrderPayment->payment_key = $order->getPayment()->getMethod();
        $cmsOrderPayment->method = $order->getPayment()->getMethod();
        $cmsOrderPayment->date = $orderData['created_at'];
        $cmsOrderPayment->amount = $order->getPayment()->getAmountPaid();
        $cmsOrderPayment->currency_key = $orderData['order_currency_code'];
        $cmsOrderPayment->currency_rate = $orderData['base_to_order_rate'];
        $cmsOrderPayments[] = $cmsOrderPayment;

        return $cmsOrderPayments;
    }

    private static function validAddress(&$address)
    {
        //wait ansd see if we need it
    }

    /**
     * Test si on a une incohérence de TVA sur les remises des articles
     *
     * @param \Magento\Sales\Model\Order $order
     * @return boolean
     */
    public static function orderHasDiscountError($order)
    {
        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();


        $eavModel = $objectManager->create('Magento\Catalog\Model\ResourceModel\Eav\Attribute');
        $taxItem = $objectManager->get('\Magento\Sales\Model\ResourceModel\Order\Tax\Item');
        $taxItems= [];

        if ((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "ean_upc") != 0) {
            $attribute = $eavModel->load((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "ean_upc"));
        }
        if (count($taxItem->getTaxItemsByOrderId($order->getId())) > 0) {
            foreach ($taxItem->getTaxItemsByOrderId($order->getId()) as $row) {
                $taxItems[$row['item_id']][]= $row;
            }
        }

        $products = $order->getAllItems();

        foreach ($products as $product) {
            // ne prends que les lignes sans parent (valorisées)
            if ((int)$product['parent_item_id'] == 0) {
                $rate_tax = 0;

                if (count($taxItems) > 0 && !empty($taxItems[$product['item_id']])) {
                    foreach ($taxItems[$product['item_id']] as $row) {
                        $rate_tax .= $row['tax_percent'];
                    }
                } else {
                    $rate_tax .= 0;
                }

                //calcul du prix final en fonction de la clef de config si les remise sont appliquée sur le ht ou pas
                $discount_unit = ($product['discount_amount'] - $product['discount_tax_compensation_amount']) / $product['qty_ordered'];

                // calcul le montant de taxe final à partir du TTC de l'article
                $final_price_wt = (float)$product['price'] + (float)$product['base_tax_amount'] - $discount_unit;
                $final_price = (float)($final_price_wt / (1 + (float)$rate_tax / 100));
                $final_tax = (float)round(($final_price_wt -  $final_price), 2);
                $base_tax_amount = (float)round($product['base_tax_amount'], 2);

                // si le montant de la taxe trouvé à partir du TTC de l'article <> de la taxe dans la base de données
                // alors ne remonte la remise dans l'ERP
                // sinon remonte la remise
                if ($base_tax_amount!= $final_tax) {
                    return true;
                }
            }
        }
        return false;
    }
    /**
     * Retourne les articles de la commande
     *
     * @param \Magento\Sales\Model\Order $order
     * @return CmsOrderProduct[]
     */
    private static function getProducts($order)
    {
        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();
        /** @var ResourceConnection $resource */
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection= $resource->getConnection();

        $productRepo = $objectManager->create('Magento\Catalog\Model\ProductRepository');
        $eavModel = $objectManager->create('Magento\Catalog\Model\ResourceModel\Eav\Attribute');
        $taxItem = $objectManager->get('\Magento\Sales\Model\ResourceModel\Order\Tax\Item');
        //$tax_lanes = $taxItem->getTaxItemsByOrderId($order->getId());

        $taxItems= [];
        $salesOrderItemTableName = $resource->getTableName('sales_order_item');
        $cataloguProductEntityTableName = $resource->getTableName('catalog_product_entity ');

        if ((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "ean_upc") != 0) {
            $attribute = $eavModel->load((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "ean_upc"));
        }
        if (count($taxItem->getTaxItemsByOrderId($order->getId())) > 0) {
            foreach ($taxItem->getTaxItemsByOrderId($order->getId()) as $row) {
                $taxItems[$row['item_id']][]= $row;
            }
        }

        // la précision des prix
        $precision = (int)AtooSyncGesComTools::getConfig("atoosync_orders", "create", "round");
        if ($precision == 0) {
            $precision = 2;
        }

        $products = $order->getAllItems();
        $count = 1;
        foreach ($products as $product) {
            $productData = $product->getData();
            // ne prends que les lignes sans parent (valorisées)
            if ((int)$product['parent_item_id'] == 0) {
                $reference = customizeOrderProductReference($productData['order_id'], $productData['item_id']);
                // si la clé Atoo-Sync est vide alors utilise la référence de la ligne de la commande
                if (empty($reference)) {
                    $reference = $product['sku'];
                }
                try {
                    $productBase = $productRepo->getById($product['product_id'], false, 0);
                    $productBaseData = $productBase->getData();
                    // si pas de customisation alors essaye de trouver la référence depuis l'article ou la ligne de commande
                    if (empty($reference)) {
                        $reference = $productBaseData['atoosync_key'];
                    }

                    $EAN = "";
                    if (isset($attribute)) {
                        $EAN = $productBase->getdata($attribute['attribute_code']);
                    }
                } catch (Exception $e) {
                    $productBase = null;
                }

                $attribute_reference = '';
                $sage_gamme1 = '';
                $sage_gamme2 = '';
                $EAN = '';
                $tax_name = "";
                $id_tax = "";
                $rate_tax = "";

                if (count($taxItems) > 0 && !empty($taxItems[$product['item_id']])) {
                    foreach ($taxItems[$product['item_id']] as $row) {
                        $tax_name .= $row['title'];
                        $id_tax .= $row['tax_id'];
                        $rate_tax .= $row['tax_percent'];
                    }
                } else {
                    $tax_name .= "Hors Taxes";
                    $id_tax .= 0;
                    $rate_tax .= 0;
                }

                // trouve les informations de gammes si  elles existent
                $sql = "SELECT `product_id` FROM " . $salesOrderItemTableName . " WHERE `parent_item_id` = " . (int)$product['item_id'] . "";
                $childproduct_id = (int)$connection->fetchOne($sql);
                if ($childproduct_id > 0) {
                    try {
                        $productchild = $productRepo->getById($childproduct_id, false, 0);
                        $attribute_reference = $productchild->getSku();
                        $data = $productchild->getData();
                        $atoosync_gamme_key = $data['atoosync_gamme_key'];
                        $atoosync_key = $data['atoosync_key'];
                        if (isset($attribute)) {
                            if ($productBase) {
                                $EAN = $productBase->getResource()->getAttribute($attribute['attribute_code'])->getValue();
                            }
                        }
                    } catch (Exception $e) {
                        $productchild = null;
                    }

                    if (!empty($atoosync_gamme_key)) {
                        // enleve la référence + le séparateur de la chaine
                        $atoosync_gamme_key = str_replace($atoosync_key . '¤', '', $atoosync_gamme_key);
                        $gammes = explode("¤", $atoosync_gamme_key);
                        $sage_gamme1 = $gammes[0];
                        if (count($gammes) == 2) {
                            $sage_gamme2 = $gammes[1];
                        }
                    }
                }

                //calcul du prix final en fonction de la clef de config si les remise sont appliquée sur le ht ou pas
                $discount_unit = ($product['discount_amount'] - $product['discount_tax_compensation_amount']) / $product['qty_ordered'];

                if (self::$productVATDiscountError) {
                    $final_price_wt = (float)$product['price_incl_tax'];
                    $final_price = (float)$product['price'];
                    $price = $final_price;
                    $price_wt = $final_price_wt;
                    $product['discount_amount'] = 0;
                    $product['discount_percent'] = 0;
                } else {
                    $price = (float)$product['price'];
                    $price_wt = (float)$product['price_incl_tax'];
                    $final_price = (float)$product['price'] - (float)$discount_unit;
                    $final_price_wt = $final_price + (float)$product['base_tax_amount'];

                    // si il y a une rmeise en montant alors efface la remise en pourcentage car
                    // le pourcentage peut s'appliquer sur le TTC ou le HT et ce n'est pas mémorisé dans Magento
                    if ((float)$product['discount_amount'] > 0) {
                        $product['discount_percent'] = 0;
                    }
                }

                /** Créé l'objet CmsOrderProduct */
                $cmsOrderProduct = new CmsOrderProduct();
                $cmsOrderProduct->price_precision = $precision;
                $cmsOrderProduct->product_key = $reference;
                $cmsOrderProduct->product_variation_key = $attribute_reference;
                $cmsOrderProduct->product_ean13 = $EAN;
                $cmsOrderProduct->product_name = $product['name'];
                $cmsOrderProduct->quantity = $product['qty_ordered'];
                $cmsOrderProduct->unit_price_tax_excl = $price;
                $cmsOrderProduct->unit_price_tax_incl = $price_wt;
                $cmsOrderProduct->unit_price_tax = ($price_wt - $price);
                $cmsOrderProduct->unit_final_price_tax_excl = $final_price;
                $cmsOrderProduct->unit_final_price_tax_incl = $final_price_wt;
                $cmsOrderProduct->unit_final_price_tax = ($final_price_wt - $final_price);
                $cmsOrderProduct->tax_name = $tax_name;
                $cmsOrderProduct->tax_key = $id_tax;
                $cmsOrderProduct->tax_rate = $rate_tax;
                $cmsOrderProduct->unit_reduction_amount = $product['discount_amount'];
                $cmsOrderProduct->unit_reduction_percent = $product['discount_percent'];
                $cmsOrderProduct->unit_ecotax = $product['weee_tax_applied_amount'];
                $cmsOrderProduct->ecotax_tax_rate = $product['weee_tax_disposition'];
                $cmsOrderProduct->sage_gamme1 = $sage_gamme1;
                $cmsOrderProduct->sage_gamme2 = $sage_gamme2;

                /** Customize la ligne d'article de la commande */
                customizeCmsOrderProduct($cmsOrderProduct, $order, $product['item_id']);

                $cmsOrderProducts[] = $cmsOrderProduct;
            }
        }

        return $cmsOrderProducts;
    }

    /**
     * Retourne les bons de réduction de la commande
     *
     * @param \Magento\Sales\Model\Order $order
     * @return CmsOrderDiscount[]
    */
    private static function getDiscounts($order)
    {
        $cmsOrderDiscounts = [];
        // noeud de discount non utillisé car les remise sont directement appliqué sur les lignes de produit
        // en cas d'activation de ce noeud cela crée des erreurs en gestion commerciale
        /*
        $orderData = $order->getData();
        if (abs($orderData['base_discount_amount'])> 0) {
            $cmsOrderDiscount = new CmsOrderDiscount();
            $cmsOrderDiscount->name = $orderData['coupon_rule_name'];
            $cmsOrderDiscount->value_tax_incl = abs($orderData['base_discount_amount']);
            $cmsOrderDiscount->value_tax = abs($orderData['discount_tax_compensation_amount']);
            $cmsOrderDiscount->value_tax_excl =  $cmsOrderDiscount->value_tax_incl - $cmsOrderDiscount->value_tax;
            $cmsOrderDiscounts[] = $cmsOrderDiscount;
        }*/
        return $cmsOrderDiscounts;
    }

    /**
     * Retourne le message de la commande
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string
     */
    private static function getOrderMessages($order)
    {
        $MessagesOrder ='';
        if (AtooSyncGesComTools::getConfig("atoosync_orders", "create", "messages") == "first" or AtooSyncGesComTools::getConfig("atoosync_orders", "create", "messages") == "all") {
            $orderComments = $order->getAllStatusHistory();
            foreach ($orderComments as $id => $comment) {
                if (AtooSyncGesComTools::getConfig("atoosync_orders", "create", "messages") == "first" && $MessagesOrder != '') {
                    break;
                }
                $MessagesOrder .=  html_entity_decode(str_replace("\r\n", '\n', $comment->getData('comment')), ENT_QUOTES, 'UTF-8') . '\n';
                $MessagesOrder .='\n';
            }
        }
        return $MessagesOrder;
    }
    /*
     * function non utilisée dans Magento
   * Retourne le id_tax du transporteur
   */
    private static function getIdTaxCarrier($id_carrier)
    {
        //used in ps for shipping tax rate, we calculate it on top
    }
    /*
    * function non utilisée dans Magento
    * Retourne le taux de la taxe
    */
    private static function getTaxRate($id_tax)
    {
        //used in ps for shipping tax rate, we calculate it on top
    }
    /*
   * Marque une commande commande transférée
   */
    private static function markOrderAsTransfered($id_order)
    {
        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();
        /** @var ResourceConnection $resource */
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection= $resource->getConnection();

        $orderTableName = $resource->getTableName('sales_order');

        $sql = "Update " . $orderTableName . " Set
                            `atoosync_transfered` = '1'
                            where `increment_id`= '" . (int)$id_order . "';";

        $connection->query($sql);
        return true;
    }
    /*
   * Enregistre le nouveau statut de la commande
   */
    private static function setOrderStatus($id_order, $newstatut)
    {
		$succes = 0;
        if (!empty($id_order) and is_numeric($id_order) and !empty($newstatut)) {
            /* Si la commande existe*/
            /** @var ObjectManager $objectManager */
            $objectManager = ObjectManager::getInstance();
            /** @var ResourceConnection $resource */
            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');

            $order  = $objectManager->create('\Magento\Sales\Model\Order')->loadByIncrementId($id_order);
            if ($order) {
                $order->setStatus($newstatut)->setState($order->getState());
                $order->save();
                $succes = 1;
            }
        }
        return $succes;
    }
    /*
   * Enregistre le numéro du document
   */
    private static function setOrderDocumentNumber($id_order, $number)
    {
        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();
        /** @var ResourceConnection $resource */
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $order  = $objectManager->create('\Magento\Sales\Model\Order')->loadByIncrementId($id_order);
        //$orderData = $order->getData();
        $success = 0;
        if (!empty($id_order) and is_numeric($id_order)) {

            $connection= $resource->getConnection();
            $orderTableName = $resource->getTableName('sales_order');

            $sql = "Update " . $orderTableName . " Set
                                `atoosync_number` = '" . $number . "'
                                where `increment_id`= '" . (int)$id_order . "';";
            $connection->query($sql);
            $success = 1;
        }
        return $success;
    }
    /*
   * Enregistre le numéro de suivi du document
   */
    private static function setOrderShippingNumber($id_order, $shipping_number)
    {
        if (customizeSetOrderShippingNumber($id_order, $shipping_number)) {
            return true;
        }

        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();
        /** @var ResourceConnection $resource */
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $succes = 0;

        // Load the order increment ID
        $order  = $objectManager->create('\Magento\Sales\Model\Order')->loadByIncrementId($id_order);
        $convertOrder = $objectManager->create('Magento\Sales\Model\Convert\Order');
        $trackfactory = $objectManager->create('Magento\Sales\Model\Order\Shipment\TrackFactory');
        // Check if order can be shipped or has already shipped
        if ($order->canShip()) {
            // Initialize the order shipment object
            $shipment = $convertOrder->toShipment($order);
            // Loop through order items
            foreach ($order->getAllItems() as $orderItem) {
                // Check if order item has qty to ship or is virtual
                if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                    continue;
                }
                $qtyShipped = $orderItem->getQtyToShip();
                // Create shipment item with qty
                $shipmentItem = $convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);
                // Add shipment item to shipment
                $shipment->addItem($shipmentItem);
            }

            // Register shipment
            /** @var \Magento\Sales\Model\Order\Shipment $shipment */
            $shipment->register();

            /** @var \Magento\Sales\Api\ShipmentRepositoryInterface $shipmentRepository */
            $shipmentRepository = $objectManager->create('\Magento\Sales\Api\ShipmentRepositoryInterface');

            $data = [
                'carrier_code' => $order->getShippingDescription(),
                'title' => $order->getShippingMethod(),
                'number' => $shipping_number, // Replace with your tracking number
            ];

            $track = $trackfactory->create()->addData($data);
            $shipment->addTrack($track);

            //$shipment->getOrder()->setIsInProcess(true);
            // Save created shipment and order
            $shipmentRepository->save($shipment);
            $shipment->getOrder()->save();
            // Send email
            $objectManager->create('Magento\Shipping\Model\ShipmentNotifier')->notify($shipment);
        }
        $succes = 1;

        return $succes;
    }

    /**
     * Retourne le numéro du client dans l'ERP
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string
     */
    public static function getERPCustomerAccount($order)
    {
        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();
        /** @var ResourceConnection $resource */
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $orderData = $order->getData();

        $accountNumber = "";

        $connection= $resource->getConnection();
        $tableNameCustomer = $resource->getTableName('customer_entity');
        $query = 'SELECT `atoosync_account` FROM `' . $tableNameCustomer . '` WHERE `entity_id` = "' . (string)$orderData['customer_id'] . '"';
        $client = (array)$connection->fetchRow($query);
        if (array_key_exists('atoosync_account', $client)) {
            $accountNumber = (string)$client['atoosync_account'];
        }
        return $accountNumber;
    }

    /**
    * Enregistre le détail des livraisons de la commande
    *
    * @param string $xml
    * @return bool
    */
    private static function setOrderDeliveries($xml)
    {
        $xml = AtooSyncGesComTools::stripslashes($xml);
        $erpOrderDeliveriesXml = AtooSyncGesComTools::loadXML($xml);

        $erpOrderDeliveries = ErpOrderDeliveries ::createFromXML($erpOrderDeliveriesXml);
        if (empty($erpOrderDeliveries)) {
            return false;
        }
        // pas de customisation existante dans le fichier  customizable

        customizeSetOrderDeliveries($erpOrderDeliveries);
        /** Par défaut cette foSetOrderShippingDetailsnction ne fait rien
         * car ce n'est pas gérée nativement par la boutique.
         */

        return true;
    }

    /**
     * Enregistre la date de livraison du document
     *
     * @param int $id_order
     * @param string $delivery_date
     * @return bool
     */
    private static function setOrderDeliveryDate($id_order, $delivery_date)
    {
        return true;
    }

}
