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

use AtooNext\AtooSync\Erp\Order\ErpSalesDocument;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\Write;

class AtooSyncDocuments
{
    /**
     * Execute les fonctions
     *
     * @return bool
     */
    public static function dispatcher()
    {
        $result= true;

        switch (AtooSyncGesComTools::getValue('cmd')) {

            case 'productdocumentexist':
                $result=self::productDocumentExist(AtooSyncGesComTools::getValue('reference'), AtooSyncGesComTools::getValue('key'));
                break;

            case 'createproductdocument':
                $result=self::createProductDocument(AtooSyncGesComTools::getValue('xml'));
                break;

            case 'deleteproductdocuments':
                $result=self::deleteProductDocuments(AtooSyncGesComTools::getValue('reference'));
                break;

            case 'createpdfinvoice':
                $result=self::createPDFInvoice(AtooSyncGesComTools::getValue('xml'));
                break;
        }
        return $result;
    }

    /**
     * Test si le document existe déjà dans le CMS
     *
     * @param string $reference
     * @param string $atoosync_key
     * @return bool
     */
    private static function productDocumentExist($reference, $atoosync_key)
    {
        $retval=true;

        return $retval;
    }

    /**
     * Supprime les documents de l'article
     * @param string $reference
     * @return bool
     */
    private static function deleteProductDocuments($reference)
    {
        return true;
    }

    /**
     * Créé le document de la fiche article dans le CMS
     *
     * @param string $xml
     * @return bool
     */
    private static function createProductDocument($xml)
    {
        $result = true;
        return $result;
    }

    /**
     * Créé la facture PDF de l'ERP issue de la commande du CMS dans la table Atoo-Sync
     *
     * @param string $xml
     * @return bool
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\ValidatorException
     */
    private static function createPDFInvoice($xml)
    {
        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();
        /** @var ResourceConnection $resource */
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');

        /** @var Filesystem $filesystem */
        $filesystem = $objectManager->get('Magento\Framework\Filesystem');

        /** @var DirectoryList $directoryList */
        $directoryList = $objectManager->get('Magento\Framework\App\Filesystem\DirectoryList');

        /** @var Write $vardirectory */
        $vardirectory = $filesystem->getDirectoryWrite($directoryList::VAR_DIR);
        
        $salesDocumentXML = AtooSyncGesComTools::loadXML(stripslashes($xml));
        if (empty($salesDocumentXML)) {
            return false;
        }
        /** @var ErpSalesDocument $erpSalesDocument */
        $erpSalesDocument = ErpSalesDocument::createFromXML($salesDocumentXML);

        $token = sha1(microtime());
        $customer_dir = '/invoices/' . $erpSalesDocument->customer_account . '/';
        $filename = $customer_dir . $token . '.file';

        if (!$vardirectory->isExist($customer_dir)) {
            $vardirectory->create($customer_dir);
        }

        if ($vardirectory->isExist($customer_dir)) {
            if ($vardirectory->isFile($filename)) {
                $vardirectory->delete($filename);
            }

            if ($vardirectory->writeFile($filename, $erpSalesDocument->documentpdf) > 0) {
                $connection= $resource->getConnection();

                $orderTableName = $resource->getTableName('atoosync_orders_documents');
                //si une ligne existe portant le meme numero de document, alors je la supprime
                $connection->delete($orderTableName, ["document_number = ?" => $erpSalesDocument->document_number]);

                $data = [];
                $data['order_id'] = AtooSyncGesComTools::pSQL($erpSalesDocument->order_key);
                $data['token'] = sha1(microtime());
                $data['filename'] = $filename;
                $data['customer_account'] = AtooSyncGesComTools::pSQL($erpSalesDocument->customer_account);
                $data['document_number'] = AtooSyncGesComTools::pSQL($erpSalesDocument->document_number);
                $data['document_reference'] = AtooSyncGesComTools::pSQL($erpSalesDocument->document_reference);
                $data['document_name'] = AtooSyncGesComTools::pSQL($erpSalesDocument->document_name);
                $data['document_date'] = AtooSyncGesComTools::pSQL($erpSalesDocument->document_date);
                $data['document_total_tax_excl'] = (float)$erpSalesDocument->document_total_tax_excl;
                $data['document_total_tax_incl'] = (float)$erpSalesDocument->document_total_tax_incl;
                $connection->insert($orderTableName, $data);

                customizeErpSaleDocument($erpSalesDocument, $filename);
            }
        }
        return true;
    }
}
