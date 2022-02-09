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
 *  @script    atoosync-gescom-webservice-products.php
 *
 * --------------------------------------------------------------------------------
 *  /!\ Ne peut être utilisé en dehors du programme Atoo-Sync /!\
 * --------------------------------------------------------------------------------
 */

use AtooNext\AtooSync\Erp\Product\ErpProductPrice;
use AtooNext\AtooSync\Erp\Product\ErpProductStock;
use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Model\CategoryLinkRepository;
use Magento\CatalogInventory\Model\StockRegistry;
use AtooNext\AtooSync\Erp\Product\ErpProduct;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\ConfigurableProduct\Helper\Product\Options\Factory;
use Magento\ConfigurableProduct\Model\LinkManagement;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\StoreRepository;
use Magento\Eav\Model\Entity\TypeFactory;
use Magento\Eav\Model\Config;

class AtooSyncProducts
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
            case 'createproducts':
                $result=self::createProducts(AtooSyncGesComTools::getValue('xml'));
                break;
            case 'setproductsprice':
                $result=self::setProductsPrice(AtooSyncGesComTools::getValue('xml'));
                break;

            case 'setproductsquantity':
                $result=self::setProductsQuantity(AtooSyncGesComTools::getValue('xml'));
                break;

            case 'setproductstate':
                $result=self::setproductstate(AtooSyncGesComTools::getValue('reference'), AtooSyncGesComTools::getValue('state'));
                break;
        }
        return $result;
    }
    /**
     * Modifier l'état de l'article
     *
     * @param string $reference
     * @param int $state
     * @return bool
     */
    private static function setproductstate($reference, $state)
    {

        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();
        /** @var ResourceConnection $resource */
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');

        /** @var StoreRepository $StoreRepository */
        $StoreRepository = $objectManager->create('Magento\Store\Model\StoreRepository');
        /** @var ProductRepository $productRepo */
        $productRepo = $objectManager->create('Magento\Catalog\Model\ProductRepository');

        $connection= $resource->getConnection();
//        $websites = (explode(",", AtooSyncGesComTools::getConfig("atoosync_products", "create", "websites")));
//        if (AtooSyncGesComTools::getConfig("atoosync_products", "create", "websites")==0) {
//            $stores = $StoreRepository->getList();
//            $websiteIds = [];
//            foreach ($stores as $storeRow) {
//                $websiteId = $storeRow["website_id"];
//                array_push($websiteIds, $websiteId);
//            }
//
//            $websites = $websiteIds;
//        }
        $websites = array(0);
        $tableName = $resource->getTableName('catalog_product_entity');
        $sql = "SELECT `entity_id` FROM " . $tableName . " WHERE `atoosync_key` = '" . (string)$reference . "';";
        $products_id = (array)$connection->fetchAll($sql);
        if ($products_id) {
            foreach ($products_id as $row) {
                $product = $productRepo->getById($row['entity_id'], false, 0);
                $product->setWebsiteIds(array(1));
                if ($state == 0) {
                    $product->setStatus(2); // Attribute set id
                    // le produit est mis en sommeil
                    // Ensuite recherche la catégorie par la famille si la sous famille n'a pas été trouvée et si la famille est présent
                    $tableNameCat = $resource->getTableName('catalog_category_entity_varchar');
                    $sql = "SELECT `entity_id` FROM " . $tableNameCat." WHERE `value` = 'sommeil';";
                    $cat_id = (int)$connection->fetchOne($sql);
                    if($cat_id > 0){
                        $product->setCategoryIds($cat_id);
                    }
                    // si on a trouvée une catégorie alors elle est associée à l'article
                } else {
                    $product->setStatus(1); // Attribute set id

                }
                $product->save();
            }
        }
        return true;
    }

    /**
     * Créer les articles dans le CMS à partir du XML envoyé depuis l'application Atoo-Sync
     *
     * @param string $xml
     * @return bool
     */
    private static function createProducts($xml)
    {

        if (empty($xml)) {
            return 0;
        }
        $result = true;
        $xml = stripslashes($xml);
        $ProductsXML = AtooSyncGesComTools::LoadXML($xml);
        foreach ($ProductsXML->product as $ProductXML) {

            if (!empty($ProductXML)) {
                // initialise l'objet ErpProduct à partir du XML de l'application Atoo-Sync.
                $erpProduct = ErpProduct::createFromXML($ProductXML);

                // Appel la surcharge de modification de l'objet
                customizeErpProduct($erpProduct);
                // si on doit nettoyer les caractères interdit sur l'article
                if (AtooSyncGesComTools::getConfig("atoosync_others_settings", "special_chars",
                        "valid_product_field") == 1) {
                    $erpProduct = self::cleanProductFields($erpProduct);
                }
                // si ecotaxe désactivé alors vide le montant de l'exotaxe
                if (AtooSyncGesComTools::getConfig("tax", "weee", "enable") == 1) {
                    $erpProduct->ecotax = 0;
                }

                // si pas de surcharge de la création ou de la modification de l'article
                if (customizeCreateProduct($erpProduct) == false) {
                    if ((string)$erpProduct->variation_reference == '') {
                        if (self::createProductSimple($erpProduct) == false) {
                            $result = false;

                        }
                    } else {
                        if (self::addProductAsVariation($erpProduct) == false) {
                            $result = false;

                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Créer ou mettre à jour l'article dans le CMS
     *
     * @param ErpProduct $erpProduct
     * @return bool
     */
    private static function createProductSimple($erpProduct)
    {

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
//        $websites = (explode(",",AtooSyncGesComTools::getConfig("atoosync_products", "create", "websites")));
//
//        if((int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "websites") == 0){
//            $stores = $StoreRepository->getList();
//            $websiteIds = array();
//            foreach ($stores as $storeRow) {
//                $websiteId = $storeRow["website_id"];
//                array_push($websiteIds, $websiteId);
//            }
//            $websites = $websiteIds;
//        }
//        $websites = array_unique($websites);
        $websites = array(0);
        $store = $storeManager->getStore();  // Get Store ID
        $success = false;

        $connection= $resource->getConnection();
        $tableName = $resource->getTableName('catalog_product_entity');
        $tableNameCat = $resource->getTableName('catalog_category_entity');

        $sql = "SELECT `entity_id` FROM " . $tableName." WHERE `sku` = '".(string)$erpProduct->reference."';";
        $product_id = (int)$connection->fetchOne($sql);


        if($product_id == 0){
            /** @var Attribute $eavModel */
            $eavModel = $objectManager->create('Magento\Catalog\Model\ResourceModel\Eav\Attribute');
            // le produit existe pas
            $websiteId = $storeManager->getWebsite()->getWebsiteId();
            /** @var Product $product */
            $product = $objectManager->create('\Magento\Catalog\Model\Product');
            $product->getDefaultAttributeSetId();
            $product->setSku(trim((string)$erpProduct->reference)); // Set your sku here
            $product->setName(trim((string)$erpProduct->name));
            $product->setData('tax_class_id',(int)$erpProduct->tax_key);
            $product->setData('meta_description',(string)$erpProduct->meta_description);
            $product->setData('meta_keyword',(string)$erpProduct->meta_keywords);
            $product->setData('meta_title',(string)$erpProduct->meta_title);
            $product->setData('url_key',(string)$erpProduct->link_rewrite);
            $product->setWebsiteIds(array(1));
            $product->setShortDescription((string)$erpProduct->description_short);
            $product->setDescription((string)$erpProduct->description);

            //par défault statut inactif ==> voir le changement par boutique
            if ((int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "state") == 0) {
                $product->setStatus(2); // Attribute set id
            } else {
                $product->setStatus(1); // Attribute set id
            }


            if((int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "attributeset") == 0 ){
                //patch mis en place en attendant la correction de la partie admin de magento l'attribut ici doit etre ficé à 9 par défaut
                $product->setAttributeSetId($product->getDefaultAttributeSetId());
            }
            else{
                $product->setAttributeSetId((int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "attributeset"));
            }

            // par défaut la catégorie est à zéro
            $cat_id =0;
            // associe l'article à la catégorie configurée dans les options
            if((int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "category")!=0 ){
                // Vérifie si la catégorie existe toujours
                $sql = "SELECT `entity_id` FROM " . $tableNameCat." WHERE `entity_id` = ".(int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "category");
                $cat_id = (int)$connection->fetchOne($sql);
                if($cat_id ==  (int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "category")){
                    $product->setCategoryIds($cat_id);
                }
            }

            $product->save();
            die;
            $product_id=$product->getId();
            $newProduct = true;

            // enregistre la clé Atoo-Sync sur l'article
            $sql = "Update " . $tableName. " Set `atoosync_key` = '".AtooSyncGesComTools::pSQL((string)$erpProduct->reference)."' where `entity_id`= '".(int)$product_id."';";
            $connection->query($sql);
        }
        // modifie l'article
        if ($product_id != 0){
            // mon produit existe pas
            /** @var ProductRepository $productRepo */
            $productRepo = $objectManager->create('Magento\Catalog\Model\ProductRepository');
            $productRepobis = $objectManager->create('Magento\Catalog\Model\ProductRepository');
            /** @var Attribute $eavModel */
            $eavModel = $objectManager->create('Magento\Catalog\Model\ResourceModel\Eav\Attribute');
            /** @var Config $eavConfig */
            $eavConfig = $objectManager->create('Magento\Eav\Model\Config');

            $stores = $StoreRepository->getList();


            // associe l'article à la catégorie configurée dans les options ==> globale sur le site
            if ((int)AtooSyncGesComTools::getConfig("atoosync_products", "update", "category")==1 || $newProduct) {
                $productCategorised = $productRepo->getById($product_id, false, 0);

                /** @var CategoryLinkManagementInterface $categoryLinkManagementInterface */
                $categoryLinkManagementInterface = $objectManager->get('\Magento\Catalog\Api\CategoryLinkManagementInterface');
                /** @var CategoryLinkRepository $categoryLinkrepository */
                $categoryLinkrepository = $objectManager->get('Magento\Catalog\Model\CategoryLinkRepository');
                $categoryLinkRepository = $objectManager->get(CategoryLinkRepository::class);

                foreach($productCategorised->getCategoryIds() as $categoryAssigned){
                    $categoryLinkrepository->deleteByIds($categoryAssigned, (string)$erpProduct->reference);
                    //$categoryLinkrepository->save();
                }
                $productCategorised->save();
                unset($productCategorised);
                // par défaut la catégorie est à zéro
                $cat_id =0;
                // associe l'article à la catégorie configurée dans les options
                if((int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "category")!=0 ){
                    // Vérifie si la catégorie existe toujours
                    $sql = "SELECT `entity_id` FROM " . $tableNameCat." WHERE `entity_id` = ".(int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "category");
                    $cat_id = (int)$connection->fetchOne($sql);
                    if($cat_id ==  (int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "category")){
                        $cat_ids[] = $cat_id;
                        $categoryLinkManagementInterface->assignProductToCategories($erpProduct->reference, $cat_ids);
                    }
                } else {
                    $cat_ids = findProductCategoriesId($erpProduct);
                    $categoryLinkManagementInterface->assignProductToCategories($erpProduct->reference, $cat_ids);

                }
                $product = $productRepobis->getById($product_id, false, 0);
                //$product->setWebsiteIds($websites);
                $product->save();
            }

            // je fait mon tableau de langue
            $langbyshop = [];
            foreach($erpProduct->languages as $erpProductLanguage) {
                $langbyshop[$erpProductLanguage->language_key] = $erpProductLanguage;
            }
            //réinitiallisation du répo pour éviter de potentiel soucis de cache
            $product = $productRepo->getById($product_id, false, 0);

            foreach ($stores as $storeRow) {
                $product->setWebsiteIds(array(1));
                if(in_array($storeRow["website_id"], $websites)){
                    $product = $productRepo->getById($product_id,false,$storeRow["store_id"]);
                    $product->setWebsiteIds(array(1));
                    //$product->setStoreId($storeRow["store_id"]);
                    //modification du statut en cas de nouveau produit
                    if($newProduct){
                        if ((int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "state") == 0) {
                            $product->setStatus(2); // Attribute set id
                        } else {
                            $product->setStatus(1); // Attribute set id
                        }
                    }
                    //modification du nom
                    if(isset($storeRow["store_id"])){
                        if(AtooSyncGesComTools::getConfig("atoosync_products", "update", "name")==1 or $newProduct == true){
                            $product->setName((string)$langbyshop[$storeRow["store_id"]]->name);
                            if($langbyshop[$storeRow["store_id"]]->link_rewrite == ""){
                                $product->setUrlKey((string)$langbyshop[$storeRow["store_id"]]->name);
                            }
                        }
                        //modification de la description courte
                        if(AtooSyncGesComTools::getConfig("atoosync_products", "update", "desc_short")==1 or $newProduct == true){
                            $product->setShortDescription((string)$langbyshop[$storeRow["store_id"]]->description_short);
                        }
                        //modification de la description
                        if(AtooSyncGesComTools::getConfig("atoosync_products", "update", "description")==1 or $newProduct == true){
                            $product->setDescription((string)$langbyshop[$storeRow["store_id"]]->description);
                        }

                        if(AtooSyncGesComTools::getConfig("atoosync_products", "update", "seo")==1 or $newProduct == true){
                            $product->setData('meta_description',(string)$langbyshop[$storeRow["store_id"]]->meta_description);
                            $product->setData('meta_keyword',(string)$langbyshop[$storeRow["store_id"]]->meta_keywords);
                            $product->setData('meta_title',(string)$langbyshop[$storeRow["store_id"]]->meta_title);
                            $product->setVisibility(4);
                        }
                    }


                    // //modification du prix
                    if(AtooSyncGesComTools::getConfig("atoosync_products", "update", "price")==1 or $newProduct == true){
                        if(AtooSyncGesComTools::getConfig("tax", "calculation", "price_includes_tax")==1){
                            $product->setPrice((float)$erpProduct->price_tax_include);
                            $product->setSpecialPrice((float)$erpProduct->price_tax_include);
                        }else{
                            $product->setPrice((float)$erpProduct->price_tax_exclude);
                            $product->setSpecialPrice((float)$erpProduct->price_tax_exclude);
                        }
                        $product->setData('tax_class_id',(int)$erpProduct->tax_key);
                        $product->setData('cost',$erpProduct->wholesale_price);
                        if((int)AtooSyncGesComTools::getConfig("tax", "weee", "enable")==1){
                            if((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "ecotax", "ecotax") != 0 ){
                                $attribute = $eavModel->load((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "ecotax", "ecotax"));
                                $product->setData($attribute['attribute_code'],(string)$erpProduct->ecotax);
                            }
                        }

                    }

                    //modification du poids
                    if(AtooSyncGesComTools::getConfig("atoosync_products", "update", "weight")==1 or $newProduct == true){
                        $product->setWeight((float)$erpProduct->weight);
                    }

                    //modification des info de colisage
                    if(AtooSyncGesComTools::getConfig("atoosync_products", "update", "ship_info")==1 or $newProduct == true){
                        if((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "width") != 0 ){
                            $attribute = $eavModel->load((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "width"));
                            $product->setData($attribute['attribute_code'],(string)$erpProduct->width);
                        }
                        if((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "height") != 0 ){
                            $attribute = $eavModel->load((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "height"));
                            $product->setData($attribute['attribute_code'],(string)$erpProduct->height);
                        }
                        if((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "depth") != 0 ){
                            $attribute = $eavModel->load((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "depth"));
                            $product->setData($attribute['attribute_code'],(string)$erpProduct->depth);
                        }
                    }
                    //modification de l'ean ou upc
                    if(AtooSyncGesComTools::getConfig("atoosync_products", "update", "ean_upc")==1 or $newProduct == true){
                        if((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "ean_upc") != 0 ){
                            $attribute = $eavModel->load((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "ean_upc"));
                            $product->setData($attribute['attribute_code'],(string)$erpProduct->ean13);
                        }
                    }
                    //modification du manufacturer
                    if(AtooSyncGesComTools::getConfig("atoosync_products", "update", "manufacturer")==1 or $newProduct == true){
                        if((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "manufacturer", "manufacturer") != 0){
                            $attribute = $eavModel->load((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "manufacturer", "manufacturer"));
                            $product->setData($attribute['attribute_code'],(string)$erpProduct->manufacturer_name);
                        }
                    }


                    //modification du supplier
                    if(AtooSyncGesComTools::getConfig("atoosync_products", "update", "supplier")==1 or $newProduct == true){
                        if((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "general", "supplier") != 0){
                            $attribute = $eavModel->load((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "general", "supplier"));
                            $product->setData($attribute['attribute_code'],(string)$erpProduct->supplier_name);
                        }
                    }


                    //modification des features
                    if(AtooSyncGesComTools::getConfig("atoosync_products", "update", "features")==1 or $newProduct == true){
                        $eavModel = $objectManager->create('Magento\Eav\Model\Entity\Attribute');

                        //attribut existant, je ne le créé pas
                        if ($erpProduct->features) {
                            foreach ($erpProduct->features as $feature) {
                                $tmp = (string)$feature->value;
                                if (!empty($tmp)) {
                                    if (!empty($feature->feature_key)) {
                                        //$attribute = $eavModel->load($feature->feature_key);
                                        $attribute = $eavConfig->getAttribute('catalog_product', $feature->feature_key);
                                        $options = $attribute->getSource()->getAllOptions();

                                        if ($attribute->usesSource()) {
                                            $id = (int)$attribute->setStoreId(0)->getSource()->getOptionId($tmp);
                                            // si pas trouvé recherche sur la boutique admin.
                                            if ($id == 0) {
                                                $id = (int)$attribute->getSource()->getOptionId($tmp);
                                            }
                                            $product->setData($attribute['attribute_code'],$id );
                                        } else {
                                            $product->setData($attribute['attribute_code'],$tmp);
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $tableNameoption = $resource->getTableName('catalog_product_option');
                    $sqlOption = "SELECT `option_id` FROM " . $tableNameoption." WHERE `product_id` = '".(string)$product->getId()."';";
                    $productOption = (int)$connection->fetchOne($sqlOption);
                    if($productOption > 0){
                        $product->hasCustomOptions(true);
                    }

                    $product->save();
                    $sql = "Update " . $tableName. " Set
                      `atoosync_key` = '".AtooSyncGesComTools::pSQL((string)$erpProduct->reference)."'
                      where `entity_id`= '".(int)$product_id."';";
                    $connection->query($sql);
                }

            }

            if (AtooSyncGesComTools::getConfig("atoosync_products", "update", "quantity")==1) {
                if ((int)$erpProduct->quantity==0) {
                    $is_in_stock = 0;
                } else {
                    $is_in_stock = 1;
                }
                /** @var Magento\CatalogInventory\Model\StockRegistry $stockRegistry */
                $stockRegistry = $objectManager->get('Magento\CatalogInventory\Model\StockRegistry');
                $stockItem = $stockRegistry->getStockItemBySku($erpProduct->reference);
                $stockItem->setQty($erpProduct->quantity);
                $stockItem->setManageStock(true);
                $stockItem->setIsInStock($is_in_stock);
                $stockRegistry->updateStockItemBySku($erpProduct->reference,$stockItem);
            }

            if(AtooSyncGesComTools::getConfig("atoosync_products", "update", "multiprices")==1 or $newProduct == true){
                // créé les prix par groupes de clients si présent
                //TODO ==>voir comment cette fonctio nà été renommé
                /*if ($erpProduct->group_prices) {
                    AtooSyncCustomerGroups::createCustomerGroupPrices($erpProduct);
                }*/

                // créé les prix spécifiques si présent
                if ($erpProduct->specificPrices) {
                    self::createSpecificPrices($product->getId(), $erpProduct);
                }
            }

            /* Créé les déclinaisons si présente */
            if ($erpProduct->variations) {
                self::createVariations($erpProduct);
            }

            // Appel la surcharge de modification de l'article
            customizeProduct($erpProduct);
            $success = true;
        }
        return $success;
    }

    /**
     * Met à jour le prix des articles
     *
     * @param int $product_id
     * @param ErpProduct $erpProduct
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private static function createSpecificPrices($product_id, $erpProduct)
    {
        if ((int)$product_id == 0) {
            return;
        }
        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();
        /** @var ResourceConnection $resource */
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        /** @var ProductRepository $productRepo */
        $productRepo = $objectManager->create('Magento\Catalog\Model\ProductRepository');
        $product = $productRepo->getById($product_id, false, 0);
        $connection= $resource->getConnection();
        /** @var StoreManagerInterface $storeManager */
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');

        $StoreRepository = $objectManager->create('Magento\Store\Model\StoreRepository');
//        $websites = (explode(",", AtooSyncGesComTools::getConfig("atoosync_products", "create", "websites")));
//
//        if ((int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "websites") == 0) {
//            $stores = $StoreRepository->getList();
//            $websiteIds = [];
//            foreach ($stores as $storeRow) {
//                $websiteId = $storeRow["website_id"];
//                array_push($websiteIds, $websiteId);
//            }
//            $websites = $websiteIds;
//        }
//        $websites = array_unique($websites);
        $websites = array(0);
        $store = $storeManager->getStore();  // Get Store ID
        $sql = 'DELETE FROM `catalog_product_entity_tier_price`
                WHERE `entity_id` = ' . $product_id;
        $connection->query($sql);
        $tierPrices=[];
        $product->setWebsiteIds(array(1));
        $stores = $StoreRepository->getList();
        foreach ($stores as $storeRow) {
            if (in_array($storeRow["website_id"], $websites)) {
//                $product->setStoreId($storeRow["store_id"]);
                foreach ($erpProduct->specificPrices as $specificPrice) {
                    // je retrouve le groupe du prix spécifique ==> en version de base je ne gere pas le prix par client
                    if (!empty((string)$specificPrice->erp_customer_group_key)) {
                        $createPrice = true;

                        $tableNameGroup = $resource->getTableName('customer_group');
                        $sql = "SELECT `customer_group_id` FROM ".$tableNameGroup." WHERE `atoosync_key` = '".(string)$specificPrice->erp_customer_group_key."';";
                        $customer_group_id = (int)$connection->fetchOne($sql);
                        if ((int)$customer_group_id == 0) {
                            $createPrice = false;
                        }
                        if ($createPrice) {
                            // soit c'est une réduction du montant, soit c'est un prix fixe
                            if ($specificPrice->reduction_type == "percentage") {
                                if ((int)$specificPrice->regular_price_tax_exclude == 0) {
                                    $tierPrices[] = [
                                        'website_id' => $storeRow["website_id"],
                                        'cust_group' => $customer_group_id,
                                        'price_qty' => (int)$specificPrice->from_quantity,
                                        'price_type' => 'discount',
                                        'price' => '',
                                        'percentage_value' => (float)$specificPrice->reduction
                                    ];
                                } else {
                                    $reduction = 0;
                                    if(AtooSyncGesComTools::getConfig("tax", "calculation", "price_includes_tax")==1){
                                        $reduction = (float)$specificPrice->price_tax_include - ((float)$specificPrice->price_tax_include * ((float)$specificPrice->reduction / 100));
                                    }else{
                                        $reduction = (float)$specificPrice->price_tax_exclude - ((float)$specificPrice->price_tax_exclude * ((float)$specificPrice->reduction / 100));
                                    }
                                    $tierPrices[] = [
                                        'website_id' => $storeRow["website_id"],
                                        'cust_group' => $customer_group_id,
                                        'price_qty' => (int)$specificPrice->from_quantity,
                                        'price_type' => 'fixed',
                                        'price' => (float)$reduction,
                                    ];
                                }
                            } else {
                                $price = 0;
                                if(AtooSyncGesComTools::getConfig("tax", "calculation", "price_includes_tax")==1){
                                    $price = (float)$specificPrice->price_tax_include;
                                }else{
                                    $price = (float)$specificPrice->price_tax_exclude;
                                }
                                $tierPrices[] = [
                                    'website_id' => $storeRow["website_id"],
                                    'cust_group' => $customer_group_id,
                                    'price_qty' => (int)$specificPrice->from_quantity,
                                    'price_type' => 'fixed',
                                    'price' => $price
                                ];
                            }
                        }
                    }
                }
            }
        }
        $product->setTierPrice((array)$tierPrices);
        $product->save();
    }

    /**
     * Met à jour le prix des articles
     *
     * @param string $xml
     * @return bool
     */
    private static function setProductsPrice($xml)
    {
        if (empty($xml)) {
            return false;
        }
        $result = true;
        $xml = AtooSyncGesComTools::stripslashes($xml);
        $productsPricesXML = AtooSyncGesComTools::loadXml($xml);

        foreach ($productsPricesXML->productprice as $productPriceXML) {
            $erpProductPrice = ErpProductPrice::createFromXML($productPriceXML);
            // Appel la surcharge de modification de l'objet
            customizeErpProductPrice($erpProductPrice);

            if (self::setProductPrice($erpProductPrice) == false) {
                $result = false;
            }
        }
        return $result;
    }

    /**
     * Met à jour le prix de l'article
     *
     * @param ErpProductPrice $erpProductPrice
     * @return bool
     */
    private static function setProductPrice($erpProductPrice)
    {
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

        // Si la création/modification du prix de l'article est surchargé.
        if (customizeProductPrice($erpProductPrice) == true) {
            return true;
        }

        // Essaye de trouver le ou les articles avec la référence
        $tableName = $resource->getTableName('catalog_product_entity');
        $sql = "SELECT `entity_id` FROM " . $tableName . " WHERE `atoosync_key` = '" . (string)$erpProductPrice->reference . "'  AND `atoosync_gamme_key` ='' ;";
        $products_id = (array)$connection->fetchall($sql);
        if ($products_id) {
            foreach ($products_id as $row) {
                $StoreRepository = $objectManager->create('Magento\Store\Model\StoreRepository');
//                $websites = (explode(",", AtooSyncGesComTools::getConfig("atoosync_products", "create", "websites")));
//
//                if ((int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "websites") == 0) {
//                    $stores = $StoreRepository->getList();
//                    $websiteIds = [];
//                    foreach ($stores as $storeRow) {
//                        $websiteId = $storeRow["website_id"];
//                        array_push($websiteIds, $websiteId);
//                    }
//                    $websites = $websiteIds;
//                }
//                $websites = array_unique($websites);
                $websites = array(1);
                $store = $storeManager->getStore();  // Get Store ID
                $sql = 'DELETE FROM `catalog_product_entity_tier_price`
                WHERE `entity_id` = ' . $row['entity_id'];

                $connection->query($sql);
                $product = $productRepo->getById($row['entity_id'], false, 0);

                $product->setWebsiteIds(array(1));
                $stores = $StoreRepository->getList();
                foreach ($stores as $storeRow) {
                    if (in_array($storeRow["website_id"], $websites)) {
//                        $product->setStoreId($storeRow["store_id"]);

                        if (AtooSyncGesComTools::getConfig("atoosync_products", "update", "price")==1) {
                            if(AtooSyncGesComTools::getConfig("tax", "calculation", "price_includes_tax")==1){
                                $product->setPrice((float)$erpProductPrice->price_tax_include);
                                $product->setSpecialPrice((float)$erpProductPrice->price_tax_include);
                            }else{
                                $product->setPrice((float)$erpProductPrice->price_tax_exclude);
                                $product->setSpecialPrice((float)$erpProductPrice->price_tax_exclude);
                            }
                            $product->setData('cost', (float)$erpProductPrice->wholesale_price);
                            $product->setData('tax_class_id', (int)$erpProductPrice->tax_key);
                            if ((int)AtooSyncGesComTools::getConfig("tax", "weee", "enable")==1) {
                                if ((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "ecotax", "ecotax") != 0) {
                                    $attribute = $eavModel->load((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "ecotax", "ecotax"));
                                    $product->setData($attribute['attribute_code'], (string)$erpProductPrice->ecotax);
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
                                                if(AtooSyncGesComTools::getConfig("tax", "calculation", "price_includes_tax")==1){
                                                    $reduction = (float)$specific_price->price_tax_include-((float)$specific_price->price_tax_include * ((float)$specific_price->reduction/100));
                                                }else{
                                                    $reduction = (float)$specific_price->price_tax_exclude-((float)$specific_price->price_tax_exclude * ((float)$specific_price->reduction/100));
                                                }
                                                $tierPrices[] = [
                                                    'website_id'       => $storeRow["website_id"],
                                                    'cust_group'       => $customer_group_id,
                                                    'price_qty'        => (int)$specific_price->from_quantity,
                                                    'price_type'       => 'fixed',
                                                    'price' =>  (float)$reduction,
                                                ];
                                            }
                                        } else {
                                            $price = 0;
                                            if(AtooSyncGesComTools::getConfig("tax", "calculation", "price_includes_tax")==1){
                                                $price = (float)$specific_price->price_tax_include;
                                            }else{
                                                $price = (float)$specific_price->price_tax_exclude;
                                            }
                                            $tierPrices[] = [
                                                'website_id'       => $storeRow["website_id"],
                                                'cust_group'       => $customer_group_id,
                                                'price_qty'        => (int)$specific_price->from_quantity,
                                                'price_type'       => 'fixed',
                                                'price' => $price
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
                                $price= (float)$erpProductPrice->price + (float)$variation->price;
                            } else {
                                $price= (float)$erpProductPrice->price;
                            }
                            $product = $productRepo->getById($row['entity_id'], false, 0);
                            if (AtooSyncGesComTools::getConfig("atoosync_products", "update", "price")==1) {
                                $product->setPrice((float)$price);
                                $product->setSpecialPrice((float)$price);
                                $product->setData('tax_class_id', (int)$erpProductPrice->tax_key);
                                $product->setData('cost', (float)$erpProductPrice->wholesale_price);
                                if ((int)AtooSyncGesComTools::getConfig("tax", "weee", "enable")==1) {
                                    if ((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "ecotax", "ecotax") != 0) {
                                        $attribute = $eavModel->load((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "ecotax", "ecotax"));
                                        $product->setData($attribute['attribute_code'], (string)$erpProductPrice->ecotax);
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

    /**
     * Met à jour le stock des articles dans le CMS
     *
     * @param string $xml le XML contenant les articles a mettre à jour
     * @return bool
     */
    private static function setProductsQuantity($xml)
    {
        if (empty($xml)) {
            return false;
        }

        $result = true;
        $xml = AtooSyncGesComTools::stripslashes($xml);
        $productsStocksXML = AtooSyncGesComTools::loadXml($xml);

        foreach ($productsStocksXML->productstock as $productStockXML) {
            $erpProductStock = ErpProductStock::createFromXML($productStockXML);
            // Appel la surcharge de modification de l'objet
            customizeErpProductStock($erpProductStock);
            if (self::setProductQuantity($erpProductStock) == false) {
                $result = false;
            }
        }
        return $result;
    }

    /**
     * Met à jour le stock des articles dans le CMS
     *
     * @param ErpProductStock $erpProductStock
     * @return bool
     */
    private static function setProductQuantity($erpProductStock)
    {
        // Si la création/modification du stock est surchargé.
        if (customizeProductQuantity($erpProductStock) == true) {
            return true;
        }
        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();
        /** @var ResourceConnection $resource */
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        /** @var Attribute $eavModel */
        $eavModel = $objectManager->create('Magento\Catalog\Model\ResourceModel\Eav\Attribute');
        /** @var ProductRepository $productRepo */
        $productRepo = $objectManager->create('Magento\Catalog\Model\ProductRepository');
        //$product = $productRepo->getById($product_id);
        $connection= $resource->getConnection();

        $succes = true;
        // Essaye de trouver le ou les articles avec la référence
        $tableName = $resource->getTableName('catalog_product_entity');
        $sql = "SELECT `entity_id` FROM " . $tableName . " WHERE `atoosync_key` = '" . (string)$erpProductStock->reference . "'  AND `atoosync_gamme_key` ='' ;";
        $products_id = (array)$connection->fetchall($sql);
        if ($products_id) {
            foreach ($products_id as $row) {
                $product = $productRepo->getById($row['entity_id'], false, 0);

                if (AtooSyncGesComTools::getConfig("atoosync_products", "update", "ean_upc")==1) {
                    if ((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "ean_upc") != 0) {
                        $attribute = $eavModel->load((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "ean_upc"));
                        $product->setData($attribute['attribute_code'], (string)$erpProductStock->ean13);
                    }
                }

                if (AtooSyncGesComTools::getConfig("atoosync_products", "update", "weight")==1) {
                    $product->setWeight((float)$erpProductStock->weight);
                }
                $product->save();
                //modification de la quantité
                if (AtooSyncGesComTools::getConfig("atoosync_products", "update", "quantity")==1) {
                    if ((int)$erpProductStock->quantity==0) {
                        $is_in_stock = 0;
                    } else {
                        $is_in_stock = 1;
                    }
                    /** @var Magento\CatalogInventory\Model\StockRegistry $stockRegistry */
                    $stockRegistry = $objectManager->get('Magento\CatalogInventory\Model\StockRegistry');
                    $stockItem = $stockRegistry->getStockItemBySku($erpProductStock->reference);
                    $stockItem->setQty($erpProductStock->quantity);
                    $stockItem->setManageStock(true);
                    $stockItem->setIsInStock($is_in_stock);
                    $stockRegistry->updateStockItemBySku($erpProductStock->reference,$stockItem);
                }
            }
        }

        if ($erpProductStock->variations) {
            foreach ($erpProductStock->variations as $variation) {

                $sql = "SELECT `entity_id` FROM " . $tableName . " WHERE `atoosync_key` = '" . (string)$variation->reference . "'  AND `atoosync_gamme_key` = '" . (string)$variation->atoosync_key . "' ;";
                $products_id = (array)$connection->fetchall($sql);
                if ($products_id) {
                    foreach ($products_id as $row) {
                        $product = $productRepo->getById($row['entity_id'], false, 0);

                        if (AtooSyncGesComTools::getConfig("atoosync_products", "update", "ean_upc")==1) {
                            if ((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "ean_upc") != 0) {
                                $attribute = $eavModel->load((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "ean_upc"));
                                $product->setData($attribute['attribute_code'], (string)$variation->ean13);
                            }
                        }

                        if (AtooSyncGesComTools::getConfig("atoosync_products", "update", "weight")==1) {
                            $product->setWeight((float)$variation->weight);
                        }
                        $product->save();
                        //modification de la quantité
                        if (AtooSyncGesComTools::getConfig("atoosync_products", "update", "quantity")==1) {
                            if ((int)$variation->quantity==0) {
                                $is_in_stock = 0;
                            } else {
                                $is_in_stock = 1;
                            }
                            /** @var Magento\CatalogInventory\Model\StockRegistry $stockRegistry */
                            $stockRegistry = $objectManager->get('Magento\CatalogInventory\Model\StockRegistry');
                            $stockItem = $stockRegistry->getStockItemBySku($variation->reference);
                            $stockItem->setQty($variation->quantity);
                            $stockItem->setManageStock(true);
                            $stockItem->setIsInStock($is_in_stock);
                            $stockRegistry->updateStockItemBySku($variation->reference,$stockItem);
                        }
                    }
                }
            }
        }

        // Execute la function de modification après la mise à jour du stock.
        customizeAfterProductQuantity($erpProductStock);

        return $succes;
    }
    /**
     * @param ErpProduct $erpProduct
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private static function addProductAsVariation($erpProduct) {
        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();
        /** @var ResourceConnection $resource */
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        /** @var StoreManagerInterface $storeManager */
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        /** @var ProductRepository $productRepo */
        $productRepo = $objectManager->create('Magento\Catalog\Model\ProductRepository');

        /** @var StoreRepository $StoreRepository */
        $StoreRepository = $objectManager->create('Magento\Store\Model\StoreRepository');
//        $websites = (explode(",",AtooSyncGesComTools::getConfig("atoosync_products", "create", "websites")));
//
//        if((int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "websites") == 0){
//            $stores = $StoreRepository->getList();
//            $websiteIds = array();
//            foreach ($stores as $storeRow) {
//                $websiteId = $storeRow["website_id"];
//                array_push($websiteIds, $websiteId);
//            }
//            $websites = $websiteIds;
//        }
//        $websites = array_unique($websites);
        $websites = array(0);
        $store = $storeManager->getStore();  // Get Store ID
        $success = false;

        $connection= $resource->getConnection();
        $tableName = $resource->getTableName('catalog_product_entity');
        $tableNameCat = $resource->getTableName('catalog_category_entity');

        $sql = "SELECT `entity_id` FROM " . $tableName." WHERE `sku` = '".(string)$erpProduct->variation_reference."';";
        $product_id = (int)$connection->fetchOne($sql);
        if ($product_id == 0) {
            $erpProductParent = clone $erpProduct;
            $erpProductParent->reference = $erpProduct->variation_reference;
            $erpProductParent->name = $erpProduct->variation_reference;
            $erpProductParent->price = 0;
            $erpProductParent->ecotax = 0;
            $erpProductParent->weight = 0;
            $erpProductParent->variations = array();
            $erpProductParent->specificPrices = array();
            self::createProductSimple($erpProductParent);

            $sql = "SELECT `entity_id` FROM " . $tableName." WHERE `sku` = '".(string)$erpProduct->variation_reference."';";
            $product_id = (int)$connection->fetchOne($sql);
        }

        if ($product_id > 0) {
            $main_product = $productRepo->getById($product_id, false, 0);
            $main_product->setTypeId('configurable');
            $main_product->save();

            /* Créé les déclinaisons si présente */
            self::createVariation($erpProduct);

            // Appel la surcharge de modification de l'article
            customizeProductAsVariation($erpProduct);
            $success = true;
        }
        return $success;
    }

    /**
     * Créer les déclinaisons de l'article
     *
     * @param $product
     * @param ErpProduct $erpProduct
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private static function createVariations($erpProduct)
    {
        // Créé les groupes d'attributs et les attributs
        self::createProductAttributes($erpProduct);

        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();
        /** @var ResourceConnection $resource */
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        /** @var StoreManagerInterface $storeManager */
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        /** @var Registry $registry */
        $registry = $objectManager->get('Magento\Framework\Registry');


        //$registry->register('isSecureArea', true);
        /** @var StoreRepository $StoreRepository */
        $StoreRepository = $objectManager->create('Magento\Store\Model\StoreRepository');
        /** @var Attribute $eavModel */
        $eavModel = $objectManager->create('Magento\Catalog\Model\ResourceModel\Eav\Attribute');

        /** @var ProductRepository $productRepo */
        $productRepo = $objectManager->create('Magento\Catalog\Model\ProductRepository');
        /** @var Factory $optionsFactory */
        $optionsFactory = $objectManager->create('Magento\ConfigurableProduct\Helper\Product\Options\Factory');
        /** @var LinkManagement $linkManagement */
        $linkManagement = $objectManager->create('Magento\ConfigurableProduct\Model\LinkManagement');

        $store = $storeManager->getStore();  // Get Store ID

//        $websites = (explode(",", AtooSyncGesComTools::getConfig("atoosync_products", "create", "websites")));
//        if ((int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "websites") == 0) {
//            $stores = $StoreRepository->getList();
//            $websiteIds = [];
//            foreach ($stores as $storeRow) {
//                $websiteId = $storeRow["website_id"];
//                array_push($websiteIds, $websiteId);
//            }
//            $websites = $websiteIds;
//        }
//        $websites = array_unique($websites);
        $websites = array(0);
        $valuesOption1 = [];
        $valuesOption2 = [];

        $connection= $resource->getConnection();
        $ProductTableName = $resource->getTableName('catalog_product_entity');

        // listing de toutes les combinaisons existantes
        $AttributeGamme1 =0;
        $AttributeGamme2 =0;
        if (trim((string)$erpProduct->variation_1) != '') {
            $AttributeGamme1 = (int)self::createAttribute((string)$erpProduct->variation_1);
        }
        if (trim((string)$erpProduct->variation_2) != '') {
            $AttributeGamme2 = (int)self::createAttribute((string)$erpProduct->variation_2);
        }

        $product_attribute_set = $product->getDefaultAttributeSetId();
        if ((int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "attributeset") != 0) {
            $product_attribute_set = (int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "attributeset");
        }

        $linkedProduct = [];

        // créé ou modifie les articles correspondant aux énumérés de gamme
        foreach ($erpProduct->variations as $variation) {

            $producOption1 = [];
            $producOption2 = [];

            $Attribute1OptionId = 0;
            $Attribute2OptionId = 0;
            /* la gamme 1 de l'énuméré */
            if ($AttributeGamme1 != 0) {
                if ((string)($variation->variation_value_1) != '') {
                    $Attribute1OptionId = self::getAttributeOptionId($AttributeGamme1, (string)$variation->variation_value_1);

                    if (!in_array($Attribute1OptionId, $valuesOption1)) {
                        $valuesOption1[]  = $Attribute1OptionId;
                    }
                }
            }

            /* la gamme 2 de l'énuméré */
            if ($AttributeGamme2 != 0) {
                if ((string)($variation->variation_value_2) != '') {
                    $Attribute2OptionId =self::getAttributeOptionId($AttributeGamme2, (string)$variation->variation_value_2);
                    if (!in_array($Attribute2OptionId, $valuesOption2)) {
                        $valuesOption2[]  = $Attribute2OptionId;
                    }
                }
            }

            // créé l'article correspondant à la gamme
            if ((int)$Attribute1OptionId != 0) {
                $newProduct = false;
                $sql = "SELECT `entity_id` FROM " . $ProductTableName . " WHERE `atoosync_key` = '" . (string)$variation->reference . "' AND atoosync_gamme_key ='" . (string)$variation->atoosync_key . "'";
                $product_id = (int)$connection->fetchOne($sql);
                if ($product_id == 0) {
                    // le produit existe pas
                    $configuration = [];

                    $reference = trim((string)$variation->reference);
                    if ($reference == "") {
                        $reference = trim((string)$erpProduct->reference);
                    }

                    $product = $objectManager->create('\Magento\Catalog\Model\Product');
                    //$websiteId = $storeManager->getWebsite()->getWebsiteId();

                    $product->setSku($reference); // Set your sku here
                    $product->setName(trim((string)$erpProduct->name . ' ' . (string)$variation->variation_value_1 . ' ' . (string)$variation->variation_value_2));
                    $product->setData('tax_class_id', (int)$erpProduct->tax_key);
                    $product->setVisibility(1); // Not visible individually
                    // seo
                    $product->setData('meta_title', (string)$erpProduct->meta_title);
                    $product->setData('meta_keyword', (string)$erpProduct->meta_keywords);
                    $product->setData('meta_description', (string)$erpProduct->meta_description);
                    // attribut divers
                    if ((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "width") != 0) {
                        $attribute = $eavModel->load((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "width"));
                        $product->setData($attribute['attribute_code'], (string)$erpProduct->manufacturer_name);
                    }
                    if ((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "height") != 0) {
                        $attribute = $eavModel->load((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "height"));
                        $product->setData($attribute['attribute_code'], (string)$erpProduct->manufacturer_name);
                    }
                    if ((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "depth") != 0) {
                        $attribute = $eavModel->load((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "depth"));
                        $product->setData($attribute['attribute_code'], (string)$erpProduct->manufacturer_name);
                    }

                    $status= 1;
                    if ((int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "state") == 0) {
                        $product->setStatus(2); // Attribute set id
                    } else {
                        $product->setStatus(1); // Attribute set id
                    }
                    $product->setAttributeSetId($product_attribute_set); // Attribute set id


                    // ajoute l'attribut de la gamme 1
                    if ((int)$AttributeGamme1 > 0) {
                        $attribute = $eavModel->load($AttributeGamme1);
                        $product->setData($attribute['attribute_code'], $Attribute1OptionId);
                    }
                    // ajoute l'attribut de la gamme 2
                    if ((int)$AttributeGamme2 > 0) {
                        $attribute = $eavModel->load($AttributeGamme2);
                        $product->setData($attribute['attribute_code'], $Attribute2OptionId);
                    }

                    $product->save();
                    $product_id=$product->getId();
                    $newProduct = true;

                    $productData[$product_id] = $configuration;

                    $sql = "Update " . $ProductTableName . " Set
                            `atoosync_key` = '" . AtooSyncGesComTools::pSQL((string)$variation->reference) . "' ,
                            `atoosync_gamme_key` = '" . AtooSyncGesComTools::pSQL((string)$variation->atoosync_key) . "'
                            where `entity_id`= '" . (int)$product_id . "';";
                    $connection->query($sql);
                }

                // modifie l'article
                if ($product_id != 0) {
                    $stores = $StoreRepository->getList();
                    foreach ($stores as $storeRow) {
                        if (in_array($storeRow["website_id"], $websites)) {

                            // ajout la référence  dans le tableau des articles des gammes

                            $product = $productRepo->getById($product_id, false, 0);
                            $product->setWebsiteIds(array(1));
//                            $product->setStoreId($storeRow["store_id"]);
                            $product->save();
                        }
                    }
                    $ProductItems[] = (string)$variation->reference;
                    $linkedProduct[] = (int)$product_id;

                    if ((int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "category")!=0) {
                        //j'ai spécifiquement demandé à ce que mon produit ce crée dans cette catégorie
                        $product->setCategoryIds((int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "category"));
                    } else {
                        //sinon j'attribue à ma catégorie
                        $tableNameCat = $resource->getTableName('catalog_category_entity');
                        $sql = "SELECT `entity_id` FROM " . $tableNameCat . " WHERE `atoosync_key` = '" . AtooSyncGesComTools::pSQL((string)$erpProduct->product_family) . "';";
                        $cat_id = (int)$connection->fetchOne($sql);
                        if ($cat_id==0) {
                            // je n'ai pas trouver d'id de ma famille, je test avec subfamily
                            $tableNameCat = $resource->getTableName('catalog_category_entity');
                            $sql = "SELECT `entity_id` FROM " . $tableNameCat . " WHERE `atoosync_key` = '" . AtooSyncGesComTools::pSQL((string)$erpProduct->product_subfamily) . "';";
                            $cat_id = (int)$connection->fetchOne($sql);
                        }
                        if ($cat_id !=0) {
                            $product->setCategoryIds($cat_id);
                            //je vérifie ma cléf de config je veux que mes produit soit crééz dans les catégorie parentes.
                            if ((int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "category_parent")!= 0) {
                                $idParents="";
                                $categoryFactory = $objectManager->get('\Magento\Catalog\Model\CategoryFactory');// Instance of Category Model
                                $category = $categoryFactory->create()->load($cat_id);
                                // Parent Categories
                                $parentCategories = $category->getParentCategories();
                                $idParent = [];
                                foreach ($parentCategories as $parent) {
                                    if ($parent['entity_id'] > 2) {
                                        $idParent[] = $parent['entity_id'];
                                    }
                                }
                                $product->setCategoryIds($idParent);
                                $product->save();
                            }
                        } else {
                            //il me faut l'identifiant de la ctégory par defaut pour chaque boutique
                            $cat_id = $store->getRootCategoryId();
                            $product->setCategoryIds($cat_id);
                        }
                    }

                    //modification du prix
                    if (AtooSyncGesComTools::getConfig("atoosync_products", "update", "price")==1 or $newProduct == true) {
                        if ((float)$variation->price != 0) {
                            $price= (float)$erpProduct->price + (float)$variation->price;
                        } else {
                            $price= (float)$erpProduct->price;
                        }

                        $product->setPrice((float)$price);
                        $product->setSpecialPrice((float)$price);
                        $product->setData('tax_class_id', (int)$erpProduct->tax_key);
                        $product->setData('cost', $variation->wholesale_price);
                    }

                    //modification de la quantité
                    if (AtooSyncGesComTools::getConfig("atoosync_products", "update", "quantity")==1 or $newProduct == true) {
                        if ((int)$variation->quantity==0) {
                            $is_in_stock = 0;
                        } else {
                            $is_in_stock = 1;
                        }
                        $product->setStockData(
                            [
                                'use_config_manage_stock' => 0,
                                'manage_stock' => 1,
                                'is_in_stock' => $is_in_stock,
                                'qty' => (int)$variation->quantity
                            ]
                        );
                    }

                    //modification de l'ean ou upc
                    if (AtooSyncGesComTools::getConfig("atoosync_products", "update", "ean_upc")==1 or $newProduct == true) {
                        if ((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "ean_upc") != 0) {
                            $attribute = $eavModel->load((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "ean_upc"));
                            $product->setData($attribute['attribute_code'], (string)$variation->ean13);
                        }
                    }

                    // Force la gamme 1
                    if ((int)$AttributeGamme1 > 0) {
                        $attribute = $eavModel->load($AttributeGamme1);
                        $product->setData($attribute['attribute_code'], $Attribute1OptionId);
                    }
                    // Force la gamme 2
                    if ((int)$AttributeGamme2 > 0) {
                        $attribute = $eavModel->load($AttributeGamme2);
                        $product->setData($attribute['attribute_code'], $Attribute2OptionId);
                    }

                    // enregistre l'article
                    $product->save();
                }
            }
        }

        // associe les articles des gammes à l'article principal (configurable)
        $sql = "SELECT `entity_id` FROM " . $ProductTableName . " WHERE `sku` = '" . (string)$erpProduct->reference . "'";
        $main_product_id = (int)$connection->fetchOne($sql);
        if ($main_product_id > 0) {
            $product = $objectManager->create('Magento\Catalog\Model\Product')->load($main_product_id); // Load Configurable Product
            /** @var $attributeModel Magento\ConfigurableProduct\Model\Product\Type\Configurable\Attribute */
            $attributeModel = $objectManager->create('Magento\ConfigurableProduct\Model\Product\Type\Configurable\Attribute');
            $attributeModel->deleteByProduct($product);
            $position = 0;
            $attributes = [];
            if ($AttributeGamme1 > 0) {
                $attributes[] = $AttributeGamme1;
            }
            if ($AttributeGamme2 > 0) {
                $attributes[] = $AttributeGamme2;
            }
            foreach ($attributes as $attributeId) {
                $data = array('attribute_id' => $attributeId, 'product_id' => $main_product_id, 'position' => $position);
                $position++;
                $attributeModel->setData($data)->save();
            }
            $product->setTypeId("configurable"); // Setting Product Type As Configurable
            $product->setAffectConfigurableProductAttributes($product_attribute_set);
            $objectManager->create('Magento\ConfigurableProduct\Model\Product\Type\Configurable')->setUsedProductAttributeIds($attributes, $product);
            $product->setNewVariationsAttributeSetId($product_attribute_set); // Setting Attribute Set Id
            $product->setAssociatedProductIds($linkedProduct);// Setting Associated Products
            $product->setCanSaveConfigurableAttributes(true);
            $product->save();

            // suprimer les variations qui n'existent plus
            $linkedString = implode(",", $linkedProduct);
            $sql_combinaison = "SELECT `entity_id`,`sku` FROM " . $ProductTableName . " WHERE
                    `atoosync_key` = '" . AtooSyncGesComTools::pSQL((string)$erpProduct->reference) . "'
                    AND `atoosync_gamme_key` <> ''
                    AND `entity_id` NOT IN(" . $linkedString . ") ;";
            $rows=$connection->fetchAll($sql_combinaison);
            foreach ($rows as $row) {
                $product = $productRepo->getById($row['entity_id'], false, 0);
                $product->delete();
            }
        }
    }

    /**
     * Créé les attributs associé à l'article
     * @param ErpProduct $erpProduct
     */
    private static function createProductAttributes($erpProduct)
    {
        global $objectManager;
        global $resource;
        global $storeManager;

        // cas de combinaisons issues des gammes sage
        if (!empty($erpProduct->variations)) {
            $AttributeGamme1 =0;
            $AttributeGamme2 =0;
            if (trim((string)$erpProduct->variation_1) != '') {
                $AttributeGamme1 = (int)self::createAttribute((string)$erpProduct->variation_1);
            }
            if (trim((string)$erpProduct->variation_2) !='') {
                $AttributeGamme2 = (int)self::createAttribute((string)$erpProduct->variation_2);
            }

            foreach ($erpProduct->variations as $variation) {
                /* la gamme 1 de l'énuméré */
                if ($AttributeGamme1 != 0) {
                    if ((string)($variation->variation_value_1) != '') {
                        self::createAttributeOption($AttributeGamme1, (string)$variation->variation_value_1);
                    }
                }

                /* la gamme 2 de l'énuméré */
                if ($AttributeGamme2 != 0) {
                    if ((string)($variation->variation_value_2) != '') {
                        self::createAttributeOption($AttributeGamme2, (string)$variation->variation_value_2);
                    }
                }
            }
        }
        else{
            if ($erpProduct->variation_reference !='') {
                for ($i = 1; $i <= 10; $i++) {
                    $variation_key = 'variation_'.$i;
                    $AttributeGamme = 'AttributeGamme'.$i;
                    $variation_value_key = 'variation_value_'.$i;
                    if (!empty((string)$erpProduct->$variation_key)) {
                        if (trim((string)$erpProduct->$variation_key) != '') {
                            $AttributeGamme = (int)self::createAttribute((string)$erpProduct->$variation_key);
                        }
                        self::createAttributeOption($AttributeGamme, (string)$erpProduct->$variation_value_key);
                    }
                }
            }
        }
    }

    /**
     * creation des attribute utilisés pour les déclinaisons
     * @param $name
     * @return int
     */
    private static function createAttribute($name)
    {
        global $objectManager;
        global $resource;
        global $storeManager;

        if (trim((string)$name) == '') {
            return 0;
        }

        $eavSetupFactory = $objectManager->create('Magento\Eav\Setup\EavSetup');
        $attributeFactory = $objectManager->create('Magento\Catalog\Model\ResourceModel\Eav\Attribute');

        $attributeset = $objectManager->create('Magento\Eav\Model\AttributeSetRepository');

        $attributeCode = 'sage_' . AtooSyncGesComTools::replaceAccentedChars((string)$name);
        $attributeCode = AtooSyncGesComTools::sanitize($attributeCode);

        if ((int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "attributeset") != 0) {
            $attributeSetId = (int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "attributeset");
            $attributeset = $attributeset->get($attributeSetId);
            $attributeSetName= $attributeset->getAttributeSetName();
        } else {
            /** @var TypeFactory $entityType */
            $entityType = $objectManager->get('\Magento\Eav\Model\Entity\TypeFactory');
            $entityType = $entityType->create()->loadByCode('catalog_product');

            //id par défault de l'attributeSet pour les produit
            $attributeSetId = $entityType->getDefaultAttributeSetId();
            $attributeset = $attributeset->get($attributeSetId);
            $attributeSetName= $attributeset->getAttributeSetName();
        }


        $attribute_group = 'Sage Gamme';
        $eavSetupFactory->addAttributeGroup(\Magento\Catalog\Model\Product::ENTITY, $attributeSetId, $attribute_group);

        $eavSetupFactory->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            $attributeCode,   /* Define the attribute code */
            [

                'attribute_set' => $attributeSetName ,
                'type' => 'text', /*Define the datatype of attribute such as string, int*/
                'backend' => '',  /* Define the backend */
                'frontend' => '',
                'label' => (string)$name, /* Provide the label of attribute to be used to show on frontend and admin*/
                'input' => 'select',  /*Define the formelement of attribute such as select,input,textbox */
                'class' => '', /* Add a class name if you want to provide any */
                'source' => '', /* If attribute is select or multiselect then to get the options detail provide the source */
                'global' => \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => true,
                'default' => '',
                'searchable' => true,
                'filterable' => true,
                'comparable' => true,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'unique' => false,
                'apply_to' => ''
            ]
        );

        $attributeInfo=$attributeFactory->getCollection()
            ->addFieldToFilter('attribute_code', ['eq'=>$attributeCode])
            ->getFirstItem();
        $attribute_id = $attributeInfo->getAttributeId();

        // ajoute l'attribut à l'attribute Set dans le groupe Sage Gamme
        $eavSetupFactory->addAttributeToSet(\Magento\Catalog\Model\Product::ENTITY, $attributeSetId, $attribute_group, $attributeCode, null);

        return $attribute_id;
    }

    /**
     * creation des option utilisé pour les attributs de déclinaison
     * @param $attributeId
     * @param $optionValue
     * @return int|void
     */
    private static function createAttributeOption($attributeId, $optionValue)
    {
        global $objectManager;
        global $storeManager;

        if ($attributeId == 0) {
            return;
        }
        if (empty($optionValue)) {
            return;
        }
        $eavModel = $objectManager->create('Magento\Catalog\Model\ResourceModel\Eav\Attribute');
        $eavSetupFactory = $objectManager->create('Magento\Eav\Setup\EavSetup');
        $stores = $storeManager->getStores($withDefault = false);

        $optionId = self::getAttributeOptionId($attributeId, $optionValue);

        if ($optionId == 0) {
            $attribute_arr = [(string)$optionValue];

            $option=[];
            $option['attribute_id'] = $attributeId;
            foreach ($attribute_arr as $key=>$value) {
                $option['value'][$key][0]=$value;
                foreach ($stores as $store) {
                    $option['value'][$key][$store->getId()] = $value;
                }
            }
            // créé l'option
            $eavSetupFactory->addAttributeOption($option);

            // récupére l'id de l'option dans l'attribut
            $optionId = self::getAttributeOptionId($attributeId, $optionValue);

            // définit l'option par défaut pour l'attribut
            if ((int)$optionId > 0) {
                $attribute = $eavModel->load($attributeId);
                $attribute->setData('default_value', $optionId);
                $attribute->save();
            }
        }

        return $optionId;
    }

    /**
     * @param $attributeId
     * @param $optionValue
     * @return int|void
     */
    private static function getAttributeOptionId($attributeId, $optionValue)
    {
        global $objectManager;

        if ($attributeId == 0) {

            return;
        }
        if (empty($optionValue)) {
            return;
        }

        $eavModel = $objectManager->create('Magento\Catalog\Model\ResourceModel\Eav\Attribute');

        $attribute = $eavModel->load($attributeId);

        $options = $attribute->getSource()->getAllOptions();
        $optionid = 0;

        foreach ($options as $option) {
            if ($option['label'] == $optionValue) {
                $optionid = (int)$option['value'];
                break;
            }
        }

        return $optionid;
    }

    /*
 * Supprimer les caractères interdit sur l'article
 */
    /**
     * @param $erpProduct
     * @return mixed
     */
    private static function cleanProductFields($erpProduct)
    {
        $erpProduct->name = (string)preg_replace('/[<>;=#{}]/', ' ', $erpProduct->name); // isCatalogName
        $erpProduct->meta_description = (string)preg_replace('/[<>={}]/', ' ', $erpProduct->meta_description); // isGenericName
        $erpProduct->meta_keywords = (string)preg_replace('/[<>={}]/', ' ', $erpProduct->meta_keywords); // isGenericName
        $erpProduct->meta_title = (string)preg_replace('/[<>={}]/', ' ', $erpProduct->meta_title); // isGenericName

        return $erpProduct;
    }

    /**
     * @param ErpProduct $erpProduct
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private static function createVariation($erpProduct) {

        // Créé les groupes d'attributs et les attributs
        self::createProductAttributes($erpProduct);

        /** @var ObjectManager $objectManager */
        $objectManager = ObjectManager::getInstance();
        /** @var ResourceConnection $resource */
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        /** @var StoreManagerInterface $storeManager */
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
        /** @var Registry $registry */
        $registry = $objectManager->get('Magento\Framework\Registry');


        //$registry->register('isSecureArea', true);
        /** @var StoreRepository $StoreRepository */
        $StoreRepository = $objectManager->create('Magento\Store\Model\StoreRepository');
        /** @var Attribute $eavModel */
        $eavModel = $objectManager->create('Magento\Catalog\Model\ResourceModel\Eav\Attribute');

        /** @var ProductRepository $productRepo */
        $productRepo = $objectManager->create('Magento\Catalog\Model\ProductRepository');
        /** @var Factory $optionsFactory */
        $optionsFactory = $objectManager->create('Magento\ConfigurableProduct\Helper\Product\Options\Factory');
        /** @var LinkManagement $linkManagement */
        $linkManagement = $objectManager->create('Magento\ConfigurableProduct\Model\LinkManagement');

        $store = $storeManager->getStore();  // Get Store ID

//        $websites = (explode(",", AtooSyncGesComTools::getConfig("atoosync_products", "create", "websites")));
//        if ((int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "websites") == 0) {
//            $stores = $StoreRepository->getList();
//            $websiteIds = [];
//            foreach ($stores as $storeRow) {
//                $websiteId = $storeRow["website_id"];
//                array_push($websiteIds, $websiteId);
//            }
//            $websites = $websiteIds;
//        }
//        $websites = array_unique($websites);
        $websites = array(0);
        $valuesOption1 = [];
        $valuesOption2 = [];

        $connection= $resource->getConnection();
        $ProductTableName = $resource->getTableName('catalog_product_entity');

        // listing de toutes les combinaisons existantes

        //TODO
        // pour chaque variation de 1 a 10 => je dois reproduire exactement le meme comportemennt dans lles endroit notifié come a faire en commentaire
        // 1) je dois identifier combien de variation j'ai ==> $countVariationActive sera mon identifiant

        $VariationsArray = [];
        $countVariationActive = 0 ;
        for ($i = 1; $i <= 10; $i++) {
            $variation_key = 'variation_'.$i; // Exemple: Couleur
            $variation_value_key = 'variation_value_'.$i; // Exemple: Rouge
            if (trim((string)$erpProduct->$variation_key) != '') {
                $attribute_id = (int)self::createAttribute((string)$erpProduct->$variation_key);
                if ($attribute_id > 0) {
                    $attribute_option_id = (int)self::getAttributeOptionId($attribute_id, (string)$erpProduct->$variation_value_key);
                    if ($attribute_option_id > 0) {
                        $countVariationActive++;
                        $VariationsArray[$countVariationActive]['variation_value'] = $erpProduct->$variation_key;
                        $VariationsArray[$countVariationActive]['attribute_id'] = $attribute_id;
                        $VariationsArray[$countVariationActive]['attribute_option_id'] = $attribute_option_id;
                    }
                }
            }
        }

        $linkedProduct = [];
        $ProductItems = [];

        // créé l'article correspondant à la gamme
        //if ((int)$Attribute1OptionId != 0) {
        $newProduct = false;
        $sql = "SELECT `entity_id` FROM ".$ProductTableName." WHERE `atoosync_key` = '".(string)$erpProduct->reference."'";
        $product_id = (int)$connection->fetchOne($sql);
        if ($product_id == 0) {
            // le produit existe pas
            $configuration = [];

            $product = $objectManager->create('\Magento\Catalog\Model\Product');
            //$websiteId = $storeManager->getWebsite()->getWebsiteId();

            $product->setSku(trim((string)$erpProduct->reference)); // Set your sku here
            $product->setName(trim((string)$erpProduct->name));
            $product->setData('tax_class_id', (int)$erpProduct->tax_key);
            $product->setVisibility(1);
            // seo
            $product->setData('meta_title', (string)$erpProduct->meta_title);
            $product->setData('meta_keyword', (string)$erpProduct->meta_keywords);
            $product->setData('meta_description', (string)$erpProduct->meta_description);
            // attribut divers
            if ((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "width") != 0) {
                $attribute = $eavModel->load((int)AtooSyncGesComTools::getConfig("atoosync_attributes",
                    "product", "width"));
                $product->setData($attribute['attribute_code'], (string)$erpProduct->manufacturer_name);
            }
            if ((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "height") != 0) {
                $attribute = $eavModel->load((int)AtooSyncGesComTools::getConfig("atoosync_attributes",
                    "product", "height"));
                $product->setData($attribute['attribute_code'], (string)$erpProduct->manufacturer_name);
            }
            if ((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "depth") != 0) {
                $attribute = $eavModel->load((int)AtooSyncGesComTools::getConfig("atoosync_attributes",
                    "product", "depth"));
                $product->setData($attribute['attribute_code'], (string)$erpProduct->manufacturer_name);
            }

            if ((int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "state") == 0) {
                $product->setStatus(2); // Attribute set id
            } else {
                $product->setStatus(1); // Attribute set id
            }
            if ((int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "attributeset") == 0) {
                $product->setAttributeSetId(4); // Attribute set id
            } else {
                $product->setAttributeSetId((int)AtooSyncGesComTools::getConfig("atoosync_products", "create",
                    "attributeset"));
            }
            // AFAIRE

            foreach ($VariationsArray as $variation) {
                $attribute = $eavModel->load($variation['attribute_id']);
                $product->setData($attribute['attribute_code'], $variation['attribute_option_id']);
            }

            $product->save();
            $product_id = $product->getId();
            $newProduct = true;


            $sql = "Update ".$ProductTableName." Set
                        `atoosync_key` = '".AtooSyncGesComTools::pSQL((string)$erpProduct->reference)."'
                        where `entity_id`= '".(int)$product_id."';";
            $connection->query($sql);
        }

        // modifie l'article
        if ($product_id != 0) {
            $stores = $StoreRepository->getList();
            foreach ($stores as $storeRow) {
                if (in_array($storeRow["website_id"], $websites)) {
                    $product = $productRepo->getById($product_id, false, 0);
                    $product->setWebsiteIds(array(1));
//                    $product->setStoreId($storeRow["store_id"]);
                    $product->save();
                }
            }
            $linkedProduct[] = (int)$product_id;

            if ((int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "category") != 0) {
                //j'ai spécifiquement demandé à ce que mon produit ce crée dans cette catégorie
                $product->setCategoryIds((int)AtooSyncGesComTools::getConfig("atoosync_products", "create", "category"));
            } else {
                //sinon j'attribue à ma catégorie
                $tableNameCat = $resource->getTableName('catalog_category_entity');
                $sql = "SELECT `entity_id` FROM ".$tableNameCat." WHERE `atoosync_key` = '".AtooSyncGesComTools::pSQL((string)$erpProduct->product_family)."';";
                $cat_id = (int)$connection->fetchOne($sql);
                if ($cat_id == 0) {
                    // je n'ai pas trouver d'id de ma famille, je test avec subfamily
                    $tableNameCat = $resource->getTableName('catalog_category_entity');
                    $sql = "SELECT `entity_id` FROM ".$tableNameCat." WHERE `atoosync_key` = '".AtooSyncGesComTools::pSQL((string)$erpProduct->product_subfamily)."';";
                    $cat_id = (int)$connection->fetchOne($sql);
                }
                if ($cat_id != 0) {
                    $product->setCategoryIds($cat_id);
                    //je vérifie ma cléf de config je veux que mes produit soit crééz dans les catégorie parentes.
                    if ((int)AtooSyncGesComTools::getConfig("atoosync_products", "create",
                            "category_parent") != 0) {
                        $idParents = "";
                        $categoryFactory = $objectManager->get('\Magento\Catalog\Model\CategoryFactory');// Instance of Category Model
                        $category = $categoryFactory->create()->load($cat_id);
                        // Parent Categories
                        $parentCategories = $category->getParentCategories();
                        $idParent = [];
                        foreach ($parentCategories as $parent) {
                            if ($parent['entity_id'] > 2) {
                                $idParent[] = $parent['entity_id'];
                            }
                        }
                        $product->setCategoryIds($idParent);
                        $product->save();
                    }
                } else {
                    //il me faut l'identifiant de la ctégory par defaut pour chaque boutique
                    $cat_id = $store->getRootCategoryId();
                    $product->setCategoryIds($cat_id);
                }
            }

            //modification du prix
            if (AtooSyncGesComTools::getConfig("atoosync_products", "update",
                    "price") == 1 or $newProduct == true) {
                if ((float)$erpProduct->price != 0) {
                    $price = (float)$erpProduct->price + (float)$erpProduct->price;
                } else {
                    $price = (float)$erpProduct->price;
                }

                $product->setPrice((float)$price);
                $product->setSpecialPrice((float)$price);
                $product->setData('tax_class_id', (int)$erpProduct->tax_key);
                $product->setData('cost', $erpProduct->wholesale_price);
            }

            //modification de l'ean ou upc
            if (AtooSyncGesComTools::getConfig("atoosync_products", "update",
                    "ean_upc") == 1 or $newProduct == true) {
                if ((int)AtooSyncGesComTools::getConfig("atoosync_attributes", "product", "ean_upc") != 0) {
                    $attribute = $eavModel->load((int)AtooSyncGesComTools::getConfig("atoosync_attributes",
                        "product", "ean_upc"));
                    $product->setData($attribute['attribute_code'], (string)$erpProduct->ean13);
                }
            }
            // AFAIRE
            // Force la gamme 1
            foreach ($VariationsArray as $variation) {
                $attribute = $eavModel->load($variation['attribute_id']);
                $product->setData($attribute['attribute_code'], $variation['attribute_option_id']);
            }
            // enregistre l'article
            $product->save();
            //modification de la quantité
            if(AtooSyncGesComTools::getConfig("atoosync_products", "update", "quantity")==1 or $newProduct == true){
                if((int)$erpProduct->quantity == 0) {
                    $is_in_stock = false;
                } else {
                    $is_in_stock = true;
                }

                /** @var Magento\CatalogInventory\Model\StockRegistry $stockRegistry */
                $stockRegistry = $objectManager->get('Magento\CatalogInventory\Model\StockRegistry');
                $stockItem = $stockRegistry->getStockItemBySku($erpProduct->reference);
                $stockItem->setQty($erpProduct->quantity);
                $stockItem->setManageStock(true);
                $stockItem->setIsInStock($is_in_stock);
                $stockRegistry->updateStockItemBySku($erpProduct->reference,$stockItem);
            }

        }
        // associe les articles des gammes à l'article principal (configurable)
        $sql = "SELECT `entity_id` FROM " . $ProductTableName . " WHERE `sku` = '" . (string)$erpProduct->variation_reference . "'";
        $main_product_id = (int)$connection->fetchOne($sql);
        if ($main_product_id != 0) {
            $main_product = $productRepo->getById($main_product_id, false, 0);
            $extensionConfigurableAttributes = $main_product->getExtensionAttributes();

            $producOption= [];
            $linkedProduct = $extensionConfigurableAttributes->getConfigurableProductLinks();
            $configurableOptions = $extensionConfigurableAttributes->getConfigurableProductOptions();

            $values = array();
            foreach ($VariationsArray as $variation) {
                $producOption[] =[
                    "attribute_id" =>  $variation['attribute_id'],
                    "label" => (string)$variation['variation_value'],
                    "position" => 0,
                    "values" => array([
                        'label' => 'Option',
                        'attribute_id' => $variation['attribute_id'],
                        'value_index' => $variation['attribute_option_id']
                    ]),
                ];
            }

            // spécifie les données des gammes
            $configurableOptions = $optionsFactory->create($producOption);
            $extensionConfigurableAttributes = $main_product->getExtensionAttributes();
            $extensionConfigurableAttributes->setConfigurableProductOptions($configurableOptions);
            $extensionConfigurableAttributes->setConfigurableProductLinks($linkedProduct);
            $main_product->setExtensionAttributes($extensionConfigurableAttributes);
            $main_product->save();

            $children = $linkManagement->getChildren($main_product->getSku());
            $isChild = 0;
            foreach($children as $child) {
                if($child->getSku() == $product->getSku()){
                    $isChild = 1;
                }
            }
            if($isChild == 0){
                $linkManagement->addChild($main_product->getSku(),$product->getSku());
            }
        }
    }

}

//je recherche chaque id de catégories qui m'est envoyé
/**
 * @param $erpProduct
 */
function findProductCategoriesId($erpProduct){
    /** @var ObjectManager $objectManager */
    $objectManager = ObjectManager::getInstance();
    /** @var ResourceConnection $resource */
    $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
    /** @var StoreManagerInterface $storeManager */
    $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
    /** @var ScopeConfigInterface $scopeConfig */
    $scopeConfig = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');
    $connection= $resource->getConnection();
    /** @var StoreRepository $StoreRepository */
    $StoreRepository = $objectManager->create('Magento\Store\Model\StoreRepository');
    // mon produit existe
    /** @var ProductRepository $productRepo */
    $productRepo = $objectManager->create('Magento\Catalog\Model\ProductRepository');
    $tableNameOrder = $resource->getTableName('sales_order');
    $stores = $StoreRepository->getList();

    $tableName = $resource->getTableName('catalog_product_entity');
    $tableNameCat = $resource->getTableName('catalog_category_entity');

    // les parents sont toujours la catégorie inférieur
    $product_category_1 = (string)$erpProduct->product_category_1;
    $product_category_2 = (string)$erpProduct->product_category_2;
    $product_category_3 = (string)$erpProduct->product_category_3;
    $product_category_4 = (string)$erpProduct->product_category_4;
    $product_category_5 = (string)$erpProduct->product_category_5;
    $product_category_6 = (string)$erpProduct->product_category_6;
    $product_category_7 = (string)$erpProduct->product_category_7;
    $product_category_8 = (string)$erpProduct->product_category_8;
    $product_category_9 = (string)$erpProduct->product_category_9;
    $product_category_10 = (string)$erpProduct->product_category_10;

    //initialisation du retour
    $categoryIds = [];

    $store = $storeManager->getStore(3);  // Get Store ID
    $rootNodeId = $store->getRootCategoryId();

    /// Get Root Category
    $rootCat = $objectManager->get('Magento\Catalog\Model\Category');
    $idParentCategory = $rootNodeId;
    if (!empty($product_category_1)) { // catégorie de premier niveau
        $idParentCategory = getOrCreateCategoryId($product_category_1, $idParentCategory);
        $categoryIds[] = $idParentCategory;
        // catégorie niveau 2
        if ($idParentCategory > 0 and !empty($product_category_2)) {
            $idParentCategory = getOrCreateCategoryId($product_category_2, $idParentCategory);
            $categoryIds[] = $idParentCategory;
            // catégorie niveau 3
            if ($idParentCategory > 0 and !empty($product_category_3)) {
                $idParentCategory = getOrCreateCategoryId($product_category_3, $idParentCategory);
                $categoryIds[] = $idParentCategory;
                // catégorie niveau 4
                if ($idParentCategory > 0 and !empty($product_category_4)) {
                    $idParentCategory = getOrCreateCategoryId($product_category_4, $idParentCategory);
                    $categoryIds[] = $idParentCategory;
                    // catégorie niveau 5
                    if ($idParentCategory > 0 and !empty($product_category_5)) {
                        $idParentCategory = getOrCreateCategoryId($product_category_5, $idParentCategory);
                        $categoryIds[] = $idParentCategory;
                        // catégorie niveau 6
                        if ($idParentCategory > 0 and !empty($product_category_6)) {
                            $idParentCategory = getOrCreateCategoryId($product_category_6, $idParentCategory);
                            $categoryIds[] = $idParentCategory;
                            // catégorie niveau 7
                            if ($idParentCategory > 0 and !empty($product_category_7)) {
                                $idParentCategory = getOrCreateCategoryId($product_category_7, $idParentCategory);
                                $categoryIds[] = $idParentCategory;
                                // catégorie niveau 8
                                if ($idParentCategory > 0 and !empty($product_category_8)) {
                                    $idParentCategory = getOrCreateCategoryId($product_category_8, $idParentCategory);
                                    $categoryIds[] = $idParentCategory;
                                    // catégorie niveau 9
                                    if ($idParentCategory > 0 and !empty($product_category_9)) {
                                        $idParentCategory = getOrCreateCategoryId($product_category_9, $idParentCategory);
                                        $categoryIds[] = $idParentCategory;
                                        // catégorie niveau 10
                                        if ($idParentCategory > 0 and !empty($product_category_10)) {
                                            $idParentCategory = getOrCreateCategoryId($product_category_10, $idParentCategory);
                                            $categoryIds[] = $idParentCategory;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    $additionnalCatIds = [];
    if(!empty($erpProduct->additionnal_categories)){
        $additionnalCatIds = explode(';',(string)$erpProduct->additionnal_categories);
    }
    if(!empty($additionnalCatIds)){
        $idParentCategory = $rootNodeId;
        foreach($additionnalCatIds as $category){
            $category = trim($category);
            if (!empty($category)) {
                $idParentCategory = getOrCreateCategoryId($category, $idParentCategory);
                $categoryIds[] = $idParentCategory;
            }
        }
    }
    $categoryIds = array_unique($categoryIds);
    return $categoryIds;
}

/**
 * Retourne la catégorie par son nom. Si la catégorie n'existe pas elle est créée.
 *
 * @param string $categoryName
 * @param int $idParentCategory
 * @return int
 */
function getOrCreateCategoryId($categoryName, $idParentCategory)
{
    global $objectManager;
    global $storeManager;
    global $resource;

    $connection= $resource->getConnection();
    /** @var CategoryFactory $categoryFactory */
    $categoryFactory=$objectManager->get('\Magento\Catalog\Model\CategoryFactory');

    // je recherche le numero de l'attribut name pour les c0tégory
    /** @var TypeFactory $entityType */
    $entityType = $objectManager->get('\Magento\Eav\Model\Entity\TypeFactory');
    $entityType = $entityType->create()->loadByCode('catalog_category');

    // trouve l'EAV  par défaut correspondant àu nom de catégorie du client
    $tableNameEAV = $resource->getTableName('eav_attribute');
    $sql = "SELECT `attribute_id` FROM " . $tableNameEAV." WHERE `entity_type_id` = '".$entityType->getId()."' AND attribute_code = 'name';";

    $connection->query($sql);
    $attribute_id = (int)$connection->fetchOne($sql);
    // je recherche maintenant le nom de la catégorie ayant le même $idParentCategory
    $tableNameCat = $resource->getTableName('catalog_category_entity');
    $tableNameCatVarchar = $resource->getTableName('catalog_category_entity_varchar');
    $sql = "SELECT `entity_id` FROM " . $tableNameCatVarchar."
                WHERE `value` = '".$categoryName."'
                AND attribute_id = '".$attribute_id."'
                AND `entity_id` IN (SELECT `entity_id` FROM " . $tableNameCat . " WHERE `parent_id` = '".$idParentCategory."');";

    $connection->query($sql);
    $entity_id = (int)$connection->fetchOne($sql);

    // si la catégorie existe alors quitte la focntion
    if ((int)$entity_id != 0){
        return $entity_id;
    }

    // je créé mon objet
    $categoryTmp = $categoryFactory->create();
    $categoryTmp->setName($categoryName);
    $categoryTmp->setUrlKey($categoryName);

    // active la catégorie uniquement si All Stores.(0)
    if ((int)AtooSyncGesComTools::getConfig("atoosync_categories", "create", "stores") == 0) {
        $categoryTmp->setData('is_active', true);
    } else {
        $categoryTmp->setData('is_active', false);
    }

    $entity_id_parent = $idParentCategory;
    // je créé mon objet
    $parentCategory = $objectManager->get('\Magento\Catalog\Model\Category')->load($entity_id_parent);
    $categoryTmp->setPath($parentCategory->getPath());
    $categoryTmp->setParentId($entity_id_parent);

    $categoryTmp->save();

    if ($categoryTmp->getId() != 0) {
        $categoryFactory = $categoryFactory->create();
        // associe la catégorie aux stores si différent de All Stores (0)
        if ((int)AtooSyncGesComTools::getConfig("atoosync_categories", "create", "stores") != 0) {
            $stores = explode(",",AtooSyncGesComTools::getConfig("atoosync_categories", "create", "stores"));
            foreach($stores as $storeId){
                $cat = $categoryFactory->load($entity_id);
                $cat->setData('store_id', $storeId);
                $cat->setData('is_active', true);
                $cat->setUrlKey($categoryName);
                $cat->save();
            }
        }
        return $categoryTmp->getId();
    }
}
