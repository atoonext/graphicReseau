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
*  @script    atoosync-gescom-webservice-prices.php
*
* --------------------------------------------------------------------------------
*  /!\ Ne peut être utilisé en dehors du programme Atoo-Sync /!\
* --------------------------------------------------------------------------------
*/

class AtooSyncPrices
{
    public static function dispatcher()
    {
        $result= true;

        switch (AtooSyncGesComTools::getValue('cmd')) {
            case 'setdiscounts':
                $result=self::setDicounts(AtooSyncGesComTools::getValue('xml'));
                break;
        }
        return $result;
    }
    /*
   * créé les remises
   */
    private static function setDicounts($xml)
    {
        $succes = true;

        return $succes;
    }
}
