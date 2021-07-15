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
*  @script    atoosync-gescom-webservice-customer-groups.php
*
* --------------------------------------------------------------------------------
*  /!\ Ne peut être utilisé en dehors du programme Atoo-Sync /!\
* --------------------------------------------------------------------------------
*/

class AtooSyncCustomerGroups
{
    public static function dispatcher()
    {
        $result= true;
        
        switch (AtooSyncGesComTools::getValue('cmd')) {
            case 'createcustomergroup':
                $result=self::createCustomerGroup(AtooSyncGesComTools::getValue('xml'));
                break;
        }

        return $result;
    }
    /*
    Ajoute ou modifie un groupe de client
  */
    private static function createCustomerGroup($xml)
    {
        global $objectManager;
        global $resource;
        
        $connection= $resource->getConnection();
        
        if (empty($xml)) {
            return 0;
        }
        $GroupXML = AtooSyncGesComTools::LoadXML(stripslashes($xml));
        if (empty($GroupXML)) {
            return 0;
        }
        $success = false;
        //je recherche si mon groupe existe déjà via la key
        
        $connection= $resource->getConnection();
        $tableName = $resource->getTableName('customer_group');
        
        // SELECT DATA
        $sql = "SELECT `customer_group_id` FROM " . $tableName." WHERE `atoosync_key` = '".AtooSyncGesComTools::pSQL((string)$GroupXML->atoosync_key)."' OR `customer_group_code` = '".AtooSyncGesComTools::pSQL((string)$GroupXML->name)."';";
        $customer_group_id = (int)$connection->fetchOne($sql);
        if($customer_group_id == 0){
            //création du groupe si la clef n'est pas trouvée
            $GroupFactory = $objectManager->get('\Magento\Customer\Model\GroupFactory');
            $group = $GroupFactory->create();
            $group->setCode((string)$GroupXML->name);
            $group->setTaxClassId(3);
            $group->save();
            //Update Data into table
            $sql = "Update " . $tableName . " Set `atoosync_key` = '".AtooSyncGesComTools::pSQL((string)$GroupXML->atoosync_key)."' where `customer_group_code`= '".AtooSyncGesComTools::pSQL((string)$GroupXML->name)."';";
            $connection->query($sql);
            $success = true;
            
        }
       else{
           //modification du nom du champ le cas échéant
           //Update Data into table
        $sql = "Update " . $tableName . " Set `customer_group_code` = '".AtooSyncGesComTools::pSQL((string)$GroupXML->name)."', `atoosync_key` = '".AtooSyncGesComTools::pSQL((string)$GroupXML->atoosync_key)."'  where `customer_group_id`= ".(int)$customer_group_id;
        $connection->query($sql);
        $success = true;
       }

        return $success;
    }
    /*
   *
   */
    public static function createCustomerGroupPrices($ProductXML)
    {
    
    }
}
