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
*  @script    atoosync-gescom-webservice-categories.php
*
* --------------------------------------------------------------------------------
*  /!\ Ne peut être utilisé en dehors du programme Atoo-Sync /!\
* --------------------------------------------------------------------------------
*/

class AtooSyncCategories
{
    public static function dispatcher()
    {
        $result= true;

        switch (AtooSyncGesComTools::getValue('cmd')) {
            case 'createcategories':
                $result = self::createCategories(AtooSyncGesComTools::getValue('xml'));
                break;
        }
        return $result;
    }
    /*
     * Créé les catégories
     */
    private static function createCategories($xml)
    {
        if (empty($xml)) {
            return 0;
        }

        $result = true;
        $xml = AtooSyncGesComTools::stripslashes($xml);
        $CategoriesXML = AtooSyncGesComTools::LoadXML($xml);

        foreach ($CategoriesXML->category as $CategoryXML) {
            if (self::createCategory($CategoryXML) == false) {
                $result = false;
            }
        }
        return $result;
    }
    /*
    Créé une catégorie
  */
    private static function createCategory($CategoryXML)
    {
        global $objectManager;
        global $storeManager;
        global $resource;

        $success = false;
        $connection= $resource->getConnection();
        $categoryFactory=$objectManager->get('\Magento\Catalog\Model\CategoryFactory');

        $store = $storeManager->getStore();  // Get Store ID
        $rootNodeId = $store->getRootCategoryId();

        /// Get Root Category
        $rootCat = $objectManager->get('Magento\Catalog\Model\Category');
        $cat_info = $rootCat->load($rootNodeId);

        // trouve le groupe par défaut correspondant à groupe du client
        $tableNameCat = $resource->getTableName('catalog_category_entity');
        $sql = "SELECT `entity_id` FROM " . $tableNameCat." WHERE `atoosync_key` = '".AtooSyncGesComTools::pSQL((string)$CategoryXML->atoosync_key)."';";
        $connection->query($sql);
        $entity_id = (int)$connection->fetchOne($sql);

        // si la catégorie existe alors quitte la focntion
        if ((int)$entity_id != 0){
            return true;
        }

        // je créé mon objet
        $categoryTmp = $categoryFactory->create();
        $categoryTmp->setName((string)$CategoryXML->name);

        // active la catégorie uniquement si All Stores.(0)
        if ((int)AtooSyncGesComTools::getConfig("atoosync_categories", "create", "stores") == 0) {
            $categoryTmp->setData('is_active', true);
        } else {
            $categoryTmp->setData('is_active', false);
        }

        $entity_id_parent = 0;
        $parentId = $rootCat->getId();
        if((string)$CategoryXML->parent_atoosync_key !=""){
            $sql_parent = "SELECT `entity_id` FROM " . $tableNameCat." WHERE `atoosync_key` = '".AtooSyncGesComTools::pSQL((string)$CategoryXML->parent_atoosync_key)."';";
            $connection->query($sql_parent);
            $entity_id_parent = (int)$connection->fetchOne($sql_parent);
            if($entity_id_parent != 0 ){
                $parentId = $entity_id_parent;
            }
        }
        if($entity_id_parent != 0){
            // je créé mopn objet parent en cas de besoin
            $parentCategory = $objectManager->get('\Magento\Catalog\Model\Category')->load($parentId);
            $categoryTmp->setPath($parentCategory->getPath());
            $categoryTmp->setParentId($parentId);
        }
        else{
            $categoryTmp->setParentId($parentId);
            $categoryTmp->setPath($rootCat->getPath());
        }

        $categoryTmp->save();
        $entity_id=$categoryTmp->getId();

        if ($entity_id != 0) {
            $categoryFactory = $categoryFactory->create();
            // associe la catégorie aux stores si différent de All Stores (0)
            if ((int)AtooSyncGesComTools::getConfig("atoosync_categories", "create", "stores") != 0) {
//                $stores = explode(",",AtooSyncGesComTools::getConfig("atoosync_categories", "create", "stores"));
                $stores = array(0);
                foreach($stores as $storeId){
                    $cat = $categoryFactory->load($entity_id);
                    $cat->setData('store_id', $storeId);
                    $cat->setData('is_active', true);
                    $cat->save();
                }
            }

            $sql = "Update " . $tableNameCat . " Set `atoosync_key` = '".AtooSyncGesComTools::pSQL((string)$CategoryXML->atoosync_key)."' where `entity_id`= '".(int)$entity_id."';";
            $connection->query($sql);

            $succes = true;
        }

        return $succes;
    }
}
