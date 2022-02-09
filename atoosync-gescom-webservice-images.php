<?php

use Magento\Framework\Api\ImageContentFactory;
use Magento\Catalog\Model\Product\Gallery\EntryFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use AtooNext\AtooSync\Erp\Product\ErpProductImage;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\Write;
use Magento\Store\Model\StoreManagerInterface;

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
 *  @script    atoosync-gescom-webservice-images.php
 *
 * --------------------------------------------------------------------------------
 *  /!\ Ne peut être utilisé en dehors du programme Atoo-Sync /!\
 * --------------------------------------------------------------------------------
 */

class AtooSyncImages
{
    public static function dispatcher()
    {
        $result= true;
        
        switch (AtooSyncGesComTools::getValue('cmd')) {
            case 'productimageexist':
                $result=self::productImageExist(AtooSyncGesComTools::getValue('reference'), AtooSyncGesComTools::getValue('key'));
                break;
            
            case 'createproductimage':
                $result=self::createProductImage(AtooSyncGesComTools::getValue('xml'));
                break;
            
            case 'deleteproductimages':
                $result=self::deleteProductImages(AtooSyncGesComTools::getValue('reference'));
                break;
        }
        return $result;
    }
    
    private static function createProductImage($xml)
    {
        $success = 0;
        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();
        /** @var \Magento\Store\Model\App\Emulation $emulation $emulation */
        $emulation = $objectManager->get('\Magento\Store\Model\App\Emulation');
        $emulation->startEnvironmentEmulation(0, 'adminhtml');
        /** @var ResourceConnection $resource */
        $resource = $objectManager->get(ResourceConnection::class);
        /** @var StoreManagerInterface $storeManager */
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        /** @var ScopeConfigInterface $scopeConfig */
        $scopeConfig = $objectManager->get(ScopeConfigInterface::class);
        
        $mediaGalleryEntryFactory = $objectManager->get(EntryFactory::class);
        $mediaGalleryManagement = $objectManager->get('\Magento\Catalog\Model\Product\Gallery\GalleryManagement');
        $imageContentFactory = $objectManager->get(ImageContentFactory::class);
        
        //ma variable de connexion à la bdd
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('catalog_product_entity_media_gallery');
        
        $ImageXML = AtooSyncGesComTools::LoadXML(stripslashes($xml));
        if (empty($ImageXML)) {
            return 0;
        }
        //ma variable de connexion à la bdd
        $ErpProductImage = ErpProductImage::createFromXML($ImageXML);
        $connection = $resource->getConnection();
        $tableName = $resource->getTableName('catalog_product_entity_media_gallery');
        if (empty($ImageXML)) {
            return 0;
        }
        
        $sql = "SELECT `value_id` FROM " . $tableName . " WHERE `atoosync_image_id` = '" . AtooSyncGesComTools::pSQL((string)$ErpProductImage->atoosync_key) . "';";
        $id_image = (int)$connection->fetchOne($sql);
        //!! dans lle cas de l'utilisation de delete product dans l'admin la tablle galery n'est pas réélement vidé cella génére une erreur
        $tableNameImage = $resource->getTableName('catalog_product_entity_media_gallery');
        $sql = "SELECT `value` FROM " . $tableNameImage . " WHERE `value_id` = " . (int)$id_image;
        $id_image_gallerie = (int)$connection->fetchOne($sql);
        if($id_image_gallerie == 0){
            $sql = "DELETE FROM " . $tableName . " WHERE `value_id` = " . (int)$id_image;
            $id_image_gallerie = (int)$connection->query($sql);
            $id_image = 0;
        }
        if (($id_image == 0) or (int)AtooSyncGesComTools::getConfig("atoosync_products", "update", "image") == 1) {
            if($id_image  >0){
                
                // trouve le chemin de l'image
                
                self::deleteImage($ErpProductImage->reference, $id_image);
            }
            
            /** @var ProductRepository $productRepository */
            $productRepository = $objectManager->get('Magento\Catalog\Model\ProductRepository');
            $product= $productRepository->get($ErpProductImage->reference, false, 0);
			
            if ($product) {
                $product->getMediaGalleryImages()->clear();
                /** @var Filesystem $filesystem */
                $filesystem = $objectManager->get('Magento\Framework\Filesystem');
                
                /** @var DirectoryList $directoryList */
                $directoryList = $objectManager->get('Magento\Framework\App\Filesystem\DirectoryList');
                
                /** @var Write $mediadirectory */
                $mediadirectory = $filesystem->getDirectoryWrite($directoryList::MEDIA);
                
                $filename =  $ErpProductImage->filename;
                
                if ($mediadirectory->isFile($filename)) {
                    $mediadirectory->delete($filename);
                }
                if ($mediadirectory->writeFile($filename, $ErpProductImage->imagedata) > 0) {
                    $srcfile = $mediadirectory->getAbsolutePath($filename);
                    
                    /** @var \Magento\Catalog\Model\Product\Gallery\EntryFactory $mediaGalleryEntryFactory */
                    $mediaGalleryEntryFactory = $objectManager->get('\Magento\Catalog\Model\Product\Gallery\EntryFactory');
                    /** @var \Magento\Catalog\Model\Product\Gallery\GalleryManagement $mediaGalleryManagement */
                    $mediaGalleryManagement = $objectManager->get('\Magento\Catalog\Model\Product\Gallery\GalleryManagement');
                    /** @var \Magento\Framework\Api\ImageContentFactory $imageContentFactory */
                    $imageContentFactory = $objectManager->get('\Magento\Framework\Api\ImageContentFactory');
                    
                    $entry = $mediaGalleryEntryFactory->create();
                    $entry->setFile($srcfile);
                    $entry->setMediaType('image');
                    $entry->setDisabled(false);
                    $entry->setTypes(array('image', 'small_image', 'thumbnail'));
                    
                    $imageContent = $imageContentFactory->create();
                    $imageContent->setType(mime_content_type($srcfile))
                        ->setName($ErpProductImage->filename)
                        ->setBase64EncodedData(base64_encode(file_get_contents($srcfile)));
                    
                    $entry->setContent($imageContent);
                    $id_image = $mediaGalleryManagement->create($ErpProductImage->reference, $entry);
                    
                    @$mediadirectory->delete($filename);
                    
                    $catalog_product_entity_media_gallery = $resource->getTableName('catalog_product_entity_media_gallery');
                    $query = 'UPDATE ' . $catalog_product_entity_media_gallery . ' SET  `atoosync_image_id` = "' . $ErpProductImage->atoosync_key . '"WHERE  `value_id` = ' . $id_image;
                    $atoosync_key = $connection->query($query);
                    $success= 1;
					
                    $product= $productRepository->get($ErpProductImage->reference, false, 0);
                    $mediaGalleryEntriesTemp = [];
                    $i = 1;
                    foreach ($product->getMediaGalleryEntries() as $item) {
                        if ($i === 1) {
                            $item->setTypes(['image', 'small_image', 'thumbnail']);
                        }
                        $mediaGalleryEntriesTemp[] = $item;
                        $i++;
                    }
                    $product->setMediaGalleryEntries($mediaGalleryEntriesTemp);
                    $productRepository->save($product);
                }
            } else{
                $success = 1;
            }
            return $success;
        }
    }

    private static function productImageExist($reference, $atoosync_key)
    {
        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();
        /** @var \Magento\Store\Model\App\Emulation $emulation $emulation */
        $emulation = $objectManager->get('\Magento\Store\Model\App\Emulation');
        $emulation->startEnvironmentEmulation(0, 'adminhtml');
        /** @var ResourceConnection $resource */
		
        $connection= $resource->getConnection();
        $tableName = $resource->getTableName('catalog_product_entity');
		
        $sql = "SELECT `entity_id` FROM " . $tableName . " WHERE `sku` = '" . AtooSyncGesComTools::pSQL((string)$reference) . "';";
        $product_id = (int)$connection->fetchOne($sql);
        if ($product_id != 0) {
            // instancie l'article
            $productRepo = $objectManager->create('Magento\Catalog\Model\ProductRepository');
            $product = $productRepo->getById($product_id, false, 0);
			
            // trouve l'image
            $tableName = $resource->getTableName('catalog_product_entity_media_gallery');
            $sql = "SELECT `value_id` FROM " . $tableName . " WHERE `atoosync_image_id` = '" . AtooSyncGesComTools::pSQL((string)$atoosync_key) . "';";
            $id_image = (int)$connection->fetchOne($sql);
            $imageAffiliate = 0;
            $arrayGallery=$product->getMediaGallery('images');
            foreach ($arrayGallery as $row) {
                if ($row['value_id'] == $id_image) {
                    $imageAffiliate=1;
                }
            }
            if ($imageAffiliate == 0) {
                self::deleteOrphanImage($id_image);
            }
            if ($id_image != 0 and $imageAffiliate == 1) {
                $mediaEntries = $product->getMediaGalleryEntries();
                foreach ($mediaEntries as $image) {
                    if ($image['id'] == $id_image) {
                        if ((int)AtooSyncGesComTools::getConfig("atoosync_products", "update", "image") == 1) {
                            return false;
                        } else {
                            return true;
                        }
                    }
                }
            } else {
                return false;
            }
        }
        
        return true;
    }
    
    private static function deleteProductImages($reference)
    {
        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();
        /** @var \Magento\Store\Model\App\Emulation $emulation $emulation */
        $emulation = $objectManager->get('\Magento\Store\Model\App\Emulation');
        $emulation->startEnvironmentEmulation(0, 'adminhtml');
        /** @var ResourceConnection $resource */
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        
        $connection= $resource->getConnection();
        $tableName = $resource->getTableName('catalog_product_entity');
        
        $sql = "SELECT `entity_id` FROM " . $tableName . " WHERE `sku` = '" . AtooSyncGesComTools::pSQL((string)$reference) . "';";
        $product_id = (int)$connection->fetchOne($sql);
        if ($product_id != 0) {
            
            // instancie l'article
            $productRepo = $objectManager->create('Magento\Catalog\Model\ProductRepository');
            $product = $productRepo->getById($product_id, false, 0);
			
            $mediatype = 'image';
            $mediaEntries = $product->getMediaGalleryEntries();
            foreach ($mediaEntries as $image) {
                if ($image['media_type'] == $mediatype) {
                    self::deleteImage((string)$reference, $image['id']);
                }
            }
        }
        return true;
    }
    
    private static function deleteImage($sku, $id_image)
    {
        
        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();
        /** @var \Magento\Store\Model\App\Emulation $emulation $emulation */
        $emulation = $objectManager->get('\Magento\Store\Model\App\Emulation');
        $emulation->startEnvironmentEmulation(0, 'adminhtml');
        /** @var ResourceConnection $resource */
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        
        $filesystem = $objectManager->get('Magento\Framework\Filesystem');
        $mediaGalleryManagement = $objectManager->get('\Magento\Catalog\Model\Product\Gallery\GalleryManagement');
        
        $connection= $resource->getConnection();
        $tableNameImage = $resource->getTableName('catalog_product_entity_media_gallery');
        
        $reader = $filesystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
        
        // trouve le chemin de l'image
        $sql = "SELECT `value` FROM " . $tableNameImage . " WHERE `value_id` = " . (int)$id_image;
        $value = $connection->fetchOne($sql);
        $image_path = $reader->getAbsolutePath('catalog/product' . $value);
        
        // supprime l'image de l'article
        $mediaGalleryManagement->remove($sku, $id_image);
        
        // supprime l'image de la base de données
        $sql = "delete  FROM " . $tableNameImage . " WHERE `value_id` = " . (int)$id_image;
        $connection->query($sql);
        
        // supprime l'image du disque
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
    private static function deleteOrphanImage($id_image)
    {
        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();
        /** @var \Magento\Store\Model\App\Emulation $emulation $emulation */
        $emulation = $objectManager->get('\Magento\Store\Model\App\Emulation');
        $emulation->startEnvironmentEmulation(0, 'adminhtml');
        /** @var ResourceConnection $resource */
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        
        $filesystem = $objectManager->get('Magento\Framework\Filesystem');
        $mediaGalleryManagement = $objectManager->get('\Magento\Catalog\Model\Product\Gallery\GalleryManagement');
        
        $connection= $resource->getConnection();
        $tableNameImage = $resource->getTableName('catalog_product_entity_media_gallery');
        
        $reader = $filesystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
        
        // trouve le chemin de l'image
        $sql = "SELECT `value` FROM " . $tableNameImage . " WHERE `value_id` = " . (int)$id_image;
        $value = $connection->fetchOne($sql);
        $image_path = $reader->getAbsolutePath('catalog/product' . $value);
        
        // supprime l'image de la base de données
        $sql = "delete  FROM " . $tableNameImage . " WHERE `value_id` = " . (int)$id_image;
        $connection->query($sql);
        
        // supprime l'image du disque
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
}
