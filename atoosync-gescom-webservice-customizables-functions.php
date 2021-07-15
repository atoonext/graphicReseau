<?php
/**
 * 2007-2020 Atoo Next
 *
 * --------------------------------------------------------------------------------
 *  /!\ Ne peut être utilisé en dehors du programme Atoo-Sync GesCom /!\
 * --------------------------------------------------------------------------------
 *
 *  Ce fichier fait partie du logiciel Atoo-Sync .
 *  Vous n'êtes pas autorisé à le modifier, à le recopier, à le vendre ou le redistribuer.
 *  Cet en-tête ne doit pas être retiré.
 *
 *  @author    Atoo Next SARL (contact@atoo-next.net)
 *  @copyright 2009-2020 Atoo Next SARL
 *  @license   Commercial
 *  @script    atoosync-gescom-webservice.php
 *
 * --------------------------------------------------------------------------------
 *  /!\ Ne peut être utilisé en dehors du programme Atoo-Sync GesCom /!\
 * --------------------------------------------------------------------------------
 */

use AtooNext\AtooSync\Cms\Configuration\CmsConfiguration;
use AtooNext\AtooSync\Cms\Order\CmsOrder;
use AtooNext\AtooSync\Cms\Order\CmsOrderCustomer;
use AtooNext\AtooSync\Cms\Order\CmsOrderProduct;
use AtooNext\AtooSync\Cms\Order\CmsQuote;
use AtooNext\AtooSync\Erp\Customer\ErpCustomer;
use AtooNext\AtooSync\Erp\Customer\ErpCustomerContact;
use AtooNext\AtooSync\Erp\Order\ErpOrderDeliveries;
use AtooNext\AtooSync\Erp\Order\ErpSalesDocument;
use AtooNext\AtooSync\Erp\Product\ErpProduct;
use AtooNext\AtooSync\Erp\Product\ErpProductDocument;
use AtooNext\AtooSync\Erp\Product\ErpProductPrice;
use AtooNext\AtooSync\Erp\Product\ErpProductSpecificPrice;
use AtooNext\AtooSync\Erp\Product\ErpProductStock;

/**
 * La personnalisation des fonctions présentes ci dessous se fait dans
 * un script à part qui doit se nommer 'atoosync-gescom-webservice-userfunctions.php'.
 */
/** Si le fichier atoosync-gescom-webservice-userfunctions.php existe il est chargé */
if (file_exists('atoosync-gescom-webservice-userfunctions.php')) {
    require_once 'atoosync-gescom-webservice-userfunctions.php';
}

/**
 * Fonction permettant de customiser l'objet CmsConfiguration du CMS
 *
 * @param CmsConfiguration $cmsConfiguration
 */
function customizeCmsConfiguration($cmsConfiguration)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeCmsConfiguration')) {
        _customizeCmsConfiguration($cmsConfiguration);
    }
}

/**
 * Fonction permettant de customiser l'objet CmsOrder de la commande du CMS
 *
 * @param CmsOrder $cmsOrder
 * @param string $order_key La clé de la commande dans le CMS
 */
function customizeCmsOrder($cmsOrder, $order_key)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeCmsOrder')) {
        _customizeCmsOrder($cmsOrder, $order_key);
    }
}

/**
 * Fonction permettant de customiser la ligne d'article de la commande
 *
 * @param CmsOrderProduct $cmsOrderProduct
 * @param string $order_key La clé de la commande du CMS
 * @param string $order_detail_key La clé de la ligne de la commande
 */
function customizeCmsOrderProduct($cmsOrderProduct, $order_key, $order_detail_key)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeCmsOrderProduct')) {
        _customizeCmsOrderProduct($cmsOrderProduct, $order_key, $order_detail_key);
    }
}


/**
 * Fonction permettant de customiser une commande groupée après la lecture
 *
 * @param CmsOrder $cmsOrder
 * @param array $orders Les commandes du CMS
 */
function customizeGroupedCmsOrder($cmsOrder, $orders)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeGroupedCmsOrder')) {
        _customizeGroupedCmsOrder($cmsOrder, $orders);
    }
}

/**
 * Fonction permettant de personnaliser le marquage de la commande comme transferé
 *
 * @param string $order_key La clé de la commande dans le CMS
 */
function customizeSetOrderCreated($order_key)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeSetOrderCreated')) {
        _customizeSetOrderCreated($order_key);
    }
}

/**
 * Fonction permettant de personnaliser la mise à jour du status de la commande
 *
 * @param string $order_key La clé de la commande dans le CMS
 * @param string $newstatut
 */
function customizeSetOrderStatus($order_key, $newstatut)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeSetOrderStatus')) {
        _customizeSetOrderStatus($order_key, $newstatut);
    }
}

/**
 * Fonction permettant de personnaliser la modification du numéro de document de la commande
 *
 * @param string $order_key La clé de la commande dans le CMS
 * @param string $number
 */
function customizeSetOrderDocumentNumber($order_key, $number)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeSetOrderDocumentNumber')) {
        _customizeSetOrderDocumentNumber($order_key, $number);
    }
}

/**
 * Fonction permettant de personnaliser la modification de la date de livraison de la commande
 *
 * @param string $order_key La clé de la commande dans le CMS
 * @param string $delivery_date
 */
function customizeSetOrderDeliveryDate($order_key, $delivery_date)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeSetOrderDeliveryDate')) {
        _customizeSetOrderDeliveryDate($order_key, $delivery_date);
    }
}

/**
 * Fonction permettant de personnaliser la modification du numéro de transport de la commande
 *
 * @param string $order_key La clé de la commande dans le CMS
 * @param string $shipping_number
 * @return bool
 */
function customizeSetOrderShippingNumber($order_key, $shipping_number)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeSetOrderShippingNumber')) {
        return _customizeSetOrderShippingNumber($order_key, $shipping_number);
    }
    return false;
}

/**
 * Fonction permettant de spécifier une référence pour l'article de la commande
 *
 * @param string $order_key La clé de la commande dans le CMS
 * @param string $product_detail_key La clé de la ligne d'article de la commande
 * @return string
 */
function customizeOrderProductReference($order_key, $product_detail_key)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeOrderProductReference')) {
        return _customizeOrderProductReference($order_key, $product_detail_key);
    }
    return '';
}

/**
 * Fonction permettant de remplacer la création ou la modification du client.
 *
 * @param ErpCustomer $erpCustomer
 * @return bool
 */
function customizeCreateCustomer($erpCustomer)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeCreateCustomer')) {
        return _customizeCreateCustomer($erpCustomer);
    }
    return false;
}

/**
 * Fonction permettant d'effectuer des traitements après la création du client.
 *
 * @param string $customer_key La clé du client dans le CMS
 * @param ErpCustomer $erpCustomer
 * @param string $password
 */
function customizeNewCustomer($customer_key, $erpCustomer, $password)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeNewCustomer')) {
        _customizeNewCustomer($customer_key, $erpCustomer, $password);
    }
}

/**
 * Fonction permettant d'effectuer des traitements après la mise à jour d'un client.
 *
 * @param string $customer_key La clé du client dans le CMS
 * @param ErpCustomer $erpCustomer
 */
function customizeCustomer($customer_key, $erpCustomer)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeCustomer')) {
        _customizeCustomer($customer_key, $erpCustomer);
    }
}

/**
 * Fonction permettant d'effectuer des traitements après la mise à jour d'un contact d'un client.
 *
 * @param string $customer_key Le client dans le CMS
 * @param ErpCustomer $erpCustomer
 * @param ErpCustomerContact $erpCustomerContact
 */
function customizeCustomerContact($customer_key, $erpCustomer, $erpCustomerContact)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeCustomerContact')) {
        _customizeCustomerContact($customer_key, $erpCustomer, $erpCustomerContact);
    }
}


/**
 * Fonction permettant d'effectuer des traitements après l'enregistrement du code client.
 *
 * @param string $customer_key La clé du client dans le CMS
 * @param string $erp_account_number Le numéro de client dans l'EPR
 * @param string $erp_accounting_account le Numéro de compte comptable dans l'ERP
 */
function customizeCustomerAccountNumber($customer_key, $erp_account_number, $erp_accounting_account = '')
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeCustomerAccountNumber')) {
        _customizeCustomerAccountNumber($customer_key, $erp_account_number, $erp_accounting_account);
    }
}

/**
 * Fonction permettant de remplacer la mise à jour du prix de l'article dans le CMS.
 *
 * @param ErpProductPrice $erpProductPrice
 * @return bool
 */
function customizeProductPrice($erpProductPrice)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeProductPrice')) {
        return _customizeProductPrice($erpProductPrice);
    }
    return false;
}

/**
 * Fonction permettant d'executer des traitements après la mise à jour des prix  de l'article.
 *
 * @param ErpProductPrice $erpProductPrice
 */
function customizeAfterProductPrice($erpProductPrice)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeAfterProductPrice')) {
        _customizeAfterProductPrice($erpProductPrice);
    }
}

/**
 * Fonction permettant de modifier la création de l'article.
 *
 * @param ErpProduct $erpProduct
 * @return bool
 */
function customizeCreateProduct($erpProduct)
{
    // Execute la function dans le AtooSync-UserFunctions.php si présent
    if (function_exists('_customizeCreateProduct')) {
        return _customizeCreateProduct($erpProduct);
    }
    return false;
}
/**
 * Fonction permettant de modifier l'objet ErpProduct avant l'utilisation dans le CMS
 * @param ErpProduct  $erpProduct
 */
function customizeErpProduct($erpProduct)
{
    // Execute la function dans le AtooSync-UserFunctions.php si présent
    if (function_exists('_customizeErpProduct')) {
        _customizeErpProduct($erpProduct);
    }
}
/**
 * Fonction permettant de modifier l'objet ErpProductPrice avant l'utilisation dans le CMS
 * @param ErpProductPrice  $erpProductPrice
 */
function customizeErpProductPrice($erpProductPrice)
{
    // Execute la function dans le AtooSync-UserFunctions.php si présent
    if (function_exists('_customizeErpProductPrice')) {
        _customizeErpProductPrice($erpProductPrice);
    }
}
/**
 * Fonction permettant de modifier l'objet ErpProductStock avant l'utilisation dans le CMS
 * @param ErpProductStock  $erpProductStock
 */
function customizeErpProductStock($erpProductStock)
{
    // Execute la function dans le AtooSync-UserFunctions.php si présent
    if (function_exists('_customizeErpProductStock')) {
        _customizeErpProductStock($erpProductStock);
    }
}
/**
 * Fonction permettant de remplacer la mise à jour des prix spécifiques de l'article.
 *
 * @param string $product_key La clé de l'article dans le CMS
 * @param ErpProductSpecificPrice[] $specificPrices
 * @return bool
 */
function customizeSpecificPrices($product_key, $specificPrices)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeSpecificPrices')) {
        return _customizeSpecificPrices($product_key, $specificPrices);
    }
    return false;
}
/**
 * Fonction permettant de retourner la liste des codes des articles présent dans le le CMS
 * @return array la liste des clé (références) des produits dans le CMS
 */
function customizeGetProductsKeys()
{
    // Execute la function dans le AtooSync-UserFunctions.php si présent
    if (function_exists('_customizeGetProductsKeys')) {
        return _customizeGetProductsKeys();
    }
    return array();
}
/**
 * Fonction permettant de remplacer la mise à jour de l'état de l'article dans le CMS.
 *
 * @param string $erp_product_key
 * @param int $state
 * @return bool
 */
function customizeSetProductState($erp_product_key, $state)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeSetProductState')) {
        return _customizeSetProductState($erp_product_key, $state);
    }
    return false;
}
/**
 * Fonction permettant de remplacer la mise à jour du stock de l'article.
 *
 * @param ErpProductStock $erpProductStock
 * @return bool
 */
function customizeProductQuantity($erpProductStock)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeProductQuantity')) {
        return _customizeProductQuantity($erpProductStock);
    }
    return false;
}

/**
 * Fonction permettant d'executer des traitements après la mise à jour du stock de l'article.
 *
 * @param ErpProductStock $erpProductStock
 */
function customizeAfterProductQuantity($erpProductStock)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeAfterProductQuantity')) {
        _customizeAfterProductQuantity($erpProductStock);
    }
}

/**
 * Fonction permettant de modifier l'article après la création de l'article.
 *
 * @param ErpProduct $erpProduct
 */
function customizeNewProduct($erpProduct)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeNewProduct')) {
        _customizeNewProduct($erpProduct);
    }
}

/**
 * Fonction permettant de modifier l'article après la mise à jour.
 *
 * @param ErpProduct $erpProduct
 */
function customizeProduct($erpProduct)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeProduct')) {
        _customizeProduct($erpProduct);
    }
}

/**
 * Fonction permettant de modifier l'article créé en variation après la mise à jour.
 *
 * @param ErpProduct $erpProduct
 */
function customizeProductAsVariation($erpProduct)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeProductAsVariation')) {
        _customizeProductAsVariation($erpProduct);
    }
}

/**
 * Fonction permettant de remplacer la lectures des commandes dans le CMS.
 *
 * @param string $from
 * @param string $to
 * @param string $status
 * @param string $shops
 * @param string $reload
 * @param string $all
 * @return CmsOrder[] La liste des commandes.
 */
function customizeGetCmsOrders($from, $to, $status, $shops, $reload = 'no', $all = 'no')
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeGetCmsOrders')) {
        return _customizeGetCmsOrders($from, $to, $status, $shops, $reload, $all);
    }
    return array();
}

/**
 * Fonction permettant d'ajouter des commandes dans la liste des commandes.
 *
 * @param CmsOrder[] $cmsOrders La liste des commandes.
 * @param string $from
 * @param string $to
 * @param string $status
 * @param string $shops
 * @param string $reload
 * @param string $all
 */
function customizeCmsOrders($cmsOrders, $from, $to, $status, $shops, $reload, $all)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeCmsOrders')) {
        _customizeCmsOrders($cmsOrders, $from, $to, $status, $shops, $reload, $all);
    }
}

/**
 * Fonction permettant de remplacer la lectures des avoirs dans le CMS.
 *
 * @param string $from
 * @param string $to
 * @param string $status
 * @param string $shops
 * @param string $reload
 * @param string $all
 * @return CmsOrder[] La liste des commandes.
 */
function customizeGetCmsCreditsNotes($from, $to, $status, $shops, $reload = 'no', $all = 'no')
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeGetCmsCreditsNotes')) {
        return _customizeGetCmsCreditsNotes($from, $to, $status, $shops, $reload, $all);
    }
    return array();
}

/**
 * Fonction permettant d'ajouter des avoirs dans la liste des avoirs.
 *
 * @param CmsOrder[] $cmsOrders La liste des avoirs.
 * @param string $from
 * @param string $to
 * @param string $status
 * @param string $shops
 * @param string $reload
 * @param string $all
 */
function customizeCmsCreditNotes($cmsOrders, $from, $to, $status, $shops, $reload, $all)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeCmsCreditNotes')) {
        _customizeCmsCreditNotes($cmsOrders, $from, $to, $status, $shops, $reload, $all);
    }
}

/**
 * Fonction permettant de customiser l'objet CmsOrder de l'avoir du CMS
 *
 * @param CmsOrder $cmsOrder l'objet CmsOrder
 * @param string $order_key La clé de l'avoir dans le CMS
 */
function customizeCmsCreditNote($cmsOrder, $order_key)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeCmsCreditNote')) {
        _customizeCmsCreditNote($cmsOrder, $order_key);
    }
}

/**
 * Fonction permettant de personnaliser le marquage comme transferé
 *
 * @param string $creditnote_key La clé de l'avoir dans le CMS
 */
function customizeSetCreditNoteCreated($creditnote_key)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeSetCreditNoteCreated')) {
        _customizeSetCreditNoteCreated($creditnote_key);
    }
}

/**
 * Fonction permettant de modifier le génération du XML des prospects (clients sans commandes)
 *
 * @return CmsOrderCustomer[] La liste des clients du CMS
 */
function customizeGetCmsProspects()
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeGetCmsProspects')) {
        return _customizeGetCmsProspects();
    }
    return array();
}

/**
 * Fonction permettant de modifier un prospect (client sans commandes)
 *
 * @param CmsOrderCustomer $cmsOrderCustomer
 * @param string $customer_key Le client dans le CMS
 */
function customizeCmsProspect($cmsOrderCustomer, $customer_key)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeCmsProspect')) {
        _customizeCmsProspect($cmsOrderCustomer, $customer_key);
    }
}

/**
 * Fonction permettant de renseigner les détails des bons de livraison associés à une commande
 * par défaut cette fonction ne fait rien car ce n'est pas gérée nativement par la boutique.
 *
 * @param ErpOrderDeliveries $erpOrderDeliveries
 */
function customizeSetOrderDeliveries($erpOrderDeliveries)
{
    // Execute la function dans le _AtooSync-userfunctions.php si présent
    if (function_exists('_customizeSetOrderDeliveries')) {
        _customizeSetOrderDeliveries($erpOrderDeliveries);
    }
}


/**
 * Fonction permettant de modifier l'objet ErpCustomer avant l'utilisation dans le CMS
 *
 * @param ErpCustomer $erpCustomer
 */
function customizeErpCustomer($erpCustomer)
{
    // Execute la function dans le _AtooSync-userfunctions.php si présent
    if (function_exists('_customizeErpCustomer')) {
        _customizeErpCustomer($erpCustomer);
    }
}

/**
 * Fonction permettant de personnaliser le marquage du retour produit comme transféré
 *
 * @param string $order_key La clé de la commande dans le CMS
 */
function customizeOrderReturnTransferred($order_key)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeOrderReturnTransferred')) {
        _customizeOrderReturnTransferred($order_key);
    }
}

/**
 * Fonction permettant de personnaliser le marquage de l'avoir comme transféré
 *
 * @param string $order_key La clé de la commande dans le CMS
 */
function customizeOrderSlipTransferred($order_key)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeOrderSlipTransferred')) {
        _customizeOrderSlipTransferred($order_key);
    }
}

/**
 * Fonction permettant de personnaliser la création des documents PDF de l'ERP dans le CMS
 *
 * @param ErpSalesDocument  $erpSalesDocument
 * @param string $filepath
 */
function customizeErpSaleDocument($erpSalesDocument, $filepath)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeErpSaleDocument')) {
        _customizeErpSaleDocument($erpSalesDocument, $filepath);
    }
}
/**
 * Fonction permettant de définir le tableau de catégories de l'article
 *
 * @param ErpProduct $erpProduct
 * @return array
 */
function customizeProductCategories($erpProduct)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeProductCategories')) {
        return _customizeProductCategories($erpProduct);
    }
    return array();
}

/**
 * Fonction permettant de remplacer la lectures des commandes dans le CMS.
 *
 * @param string $from
 * @param string $to
 * @param string $status
 * @param string $shops
 * @param string $reload
 * @param string $all
 * @return CmsQuote[] La liste des devis.
 */
function customizeGetCmsQuotes($from, $to, $status, $shops, $reload = 'no', $all = 'no')
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeGetCmsQuotes')) {
        return _customizeGetCmsQuotes($from, $to, $status, $shops, $reload, $all);
    }
    return array();
}

/**
 * Fonction permettant d'ajouter des devis dans la liste des devis.
 *
 * @param CmsQuote[] $cmsQuotes La liste des devis.
 * @param string $from
 * @param string $to
 * @param string $status
 * @param string $shops
 * @param string $reload
 * @param string $all
 */
function customizeCmsQuotes($cmsQuotes, $from, $to, $status, $shops, $reload, $all)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeCmsQuotes')) {
        _customizeCmsQuotes($cmsQuotes, $from, $to, $status, $shops, $reload, $all);
    }
}
/**
 * Fonction permettant de customiser l'objet CmsQuote du devis du CMS
 *
 * @param CmsQuote $cmsQuote
 * @param string $quote_key La clé du devis dans le CMS
 */
function customizeCmsQuote($cmsQuote, $quote_key)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeCmsQuote')) {
        _customizeCmsQuote($cmsQuote, $quote_key);
    }
}

/**
 * Fonction permettant de personnaliser le marquage du devis comme transferé
 *
 * @param string $quote_key La clé de la commande dans le CMS
 */
function customizeSetQuoteCreated($quote_key)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeSetQuoteCreated')) {
        _customizeSetQuoteCreated($quote_key);
    }
}

/**
 * Fonction permettant de personnaliser la mise à jour du status du devis
 *
 * @param string $quote_key La clé du devis dans le CMS
 * @param string $newstatut
 */
function customizeSetQuoteStatus($quote_key, $newstatut)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeSetQuoteStatus')) {
        _customizeSetQuoteStatus($quote_key, $newstatut);
    }
}

/**
 * Fonction permettant de personnaliser la création du document dans le CMS
 * La fonction doit retourner true si la surcharge est faite.
 *
 * @param ErpProductDocument $erpProductDocument L'objet ErpProductDocument
 * @return boolean
 */
function customizeCreateProductDocument($erpProductDocument)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeCreateProductDocument')) {
        return _customizeCreateProductDocument($erpProductDocument);
    }
    return false;
}

/**
 * Fonction permettant de modifier l'objet ErpSalesDocument avant la création du devis dans le CMS
 *
 * @param ErpSalesDocument $erpSalesDocument L'objet ErpSalesDocument
 */
function customizeErpQuoteDocument($erpSalesDocument)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeErpQuoteDocument')) {
        return _customizeErpQuoteDocument($erpSalesDocument);
    }
}

/**
 * Fonction permettant de personnaliser la création du devis dans le CMS
 * La fonction doit retourner true si la surcharge est faite.
 *
 * @param ErpSalesDocument $erpSalesDocument L'objet ErpSalesDocument
 * @return boolean
 */
function customizeCreateQuote($erpSalesDocument)
{
    // Execute la function dans le script de customisation si présent
    if (function_exists('_customizeCreateQuote')) {
        return _customizeCreateQuote($erpSalesDocument);
    }
    return false;
}
