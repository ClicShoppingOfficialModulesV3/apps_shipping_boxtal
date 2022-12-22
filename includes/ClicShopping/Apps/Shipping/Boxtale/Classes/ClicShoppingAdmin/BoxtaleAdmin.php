<?php
  /**
   *
   * @copyright 2008 - https://www.clicshopping.org
   * @Brand : ClicShopping(Tm) at Inpi all right Reserved
   * @Licence GPL 2 & MIT
   * @licence MIT - Portion of osCommerce 2.4
   * @Info : https://www.clicshopping.org/forum/trademark/
   *
   */

  namespace ClicShopping\Apps\Shipping\Boxtale\Classes\ClicShoppingAdmin;

  use ClicShopping\OM\HTML;
  use ClicShopping\OM\Registry;
  use ClicShopping\OM\CLICSHOPPING;

  use ClicShopping\Apps\Shipping\Boxtale\Classes\Shop\BoxtaleShop;

  require_once(CLICSHOPPING::BASE_DIR . 'Apps/Shipping/Boxtale/API/Boxtale/vendor/boxtale/php-library/Emc/WebService.php');
  require_once(CLICSHOPPING::BASE_DIR . 'Apps/Shipping/Boxtale/API/Boxtale/vendor/boxtale/php-library/Emc/OrderStatus.php');
  require_once(CLICSHOPPING::BASE_DIR . 'Apps/Shipping/Boxtale/API/Boxtale/vendor/boxtale/php-library/Emc/ContentCategory.php');
  require_once(CLICSHOPPING::BASE_DIR . 'Apps/Shipping/Boxtale/API/Boxtale/vendor/boxtale/php-library/Emc/CarriersList.php');
  require_once(CLICSHOPPING::BASE_DIR . 'Apps/Shipping/Boxtale/API/Boxtale/vendor/boxtale/php-library/Emc/ParcelPoint.php');

  use Emc\OrderStatus;
  use \Emc\ContentCategory;
  use \Emc\CarriersList;
  use \Emc\ParcelPoint;

  class BoxtaleAdmin
  {
    protected $boxtaleShop;

    public function __construct()
    {

      if (defined('CLICSHOPPING_APP_BOXTALE_SERVER')) {
        define('EMC_MODE', CLICSHOPPING_APP_BOXTALE_SERVER);
        define('EMC_USER', CLICSHOPPING_APP_BOXTALE_USER);
        define('EMC_PASS', CLICSHOPPING_APP_BOXTALE_PASSWORD);
        define('EMC_KEY', CLICSHOPPING_APP_BOXTALE_KEY);
      }

      $this->boxtaleShop = new BoxtaleShop();
    }

    public function getStatus($emcRef)
    {
      $CLICSHOPPING_Mail = Registry::get('Mail');

      $lib = new OrderStatus();

      $lib->setLogin(EMC_USER);
      $lib->setPassword(EMC_PASS);
      $lib->setKey(EMC_KEY);
      $lib->setEnv(EMC_MODE);
      $lib->setLocale('fr-FR'); //en-EN

      $lib->getOrderInformations($emcRef);

      if (!$lib->curl_error && !$lib->resp_error) {
        $status = $lib->order_info;

      } else {
        $status = false;

        $email_text_subject = 'API error Boxtal' . STORE_NAME;

        $error = $lib->resp_errors_list[0]['message'];
        $body = 'Error has been detected, please test your website in checkout shipping process : ' . CLICSHOPPING::getConfig('http_server', 'Shop') . ' <br /><br />' . $error;

        $CLICSHOPPING_Mail->clicMail(STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, $email_text_subject, $body, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
      }

      return $status;
    }


    /**
     * Get the content category
     * @return string
     */
    public function getContentCategory()
    {
      $lib = new ContentCategory();

      $lib->setLogin(EMC_USER);
      $lib->setPassword(EMC_PASS);
      $lib->setKey(EMC_KEY);
      $lib->setEnv(EMC_MODE);
      $lib->setLocale('fr-FR'); //en-EN

      $lib->getCategories(); // load all content categories
      $lib->getContents(); // load all content types

      if (!$lib->curl_error && !$lib->resp_error) {
        $content = '<form class="form-horizontal" role="form">';

        $content .= '<div class="form-group">';
        $content .= '<div class="col-sm-8">';
        $content .= '<select name="content_category" class="form-control">';

        $content .= '<option value="' . $lib->contents[0][0]['code'] . '">' . $lib->contents[0][0]['label'] . '</option>';

        foreach ($lib->categories as $c => $category) {
          $content .= '<optgroup label="' . $category['label'] . '">';

          foreach ($lib->contents[$category['code']] as $ch => $child) {
            $content .= '<option value="' . $child['code'] . '">' . $child['label'] . '</option>';
          }

          $content .= '</optgroup>';
        }

        $content .= '</select>';
        $content .= '</div>';
        $content .= '</div>';
        $content .= '</form>';

        return $content;
      }
    }

    /**
     * Get carrier list
     * @return array|bool
     */
    public function getCarriersList()
    {
      $lib = new CarriersList();

      $lib->setLogin(EMC_USER);
      $lib->setPassword(EMC_PASS);
      $lib->setKey(EMC_KEY);
      $lib->setEnv(EMC_MODE);
      $lib->setLocale('fr-FR'); //en-EN

      $lib->getCarriersList();

      if (!$lib->curl_error && !$lib->resp_error) {
        return $lib->carriers;
      } else {
        return false;
      }
    }


    /**
     * Get carrier list home for dropdown
     * @param $carriers_list
     * @return array
     */
    public function getHomeCarrier($carriers_list)
    {
      $code_array[] = ['id' => '0', 'text' => CLICSHOPPING::getDef('text_select')];

      foreach ($carriers_list as $carrier) {
        $code_array[] = ['id' => $carrier['ope_code'] . '|' . $carrier['srv_code'],
          'text' => $carrier['ope_code'] . ' / ' . $carrier['ope_name'] . ' / ' . $carrier['srv_name_bo'] . ' / ' . $carrier['srv_code']
        ];
      }
      return $code_array;
    }


    /**
     * Operator selected for pickup
     * Pickup Operator
     * @return array
     */
    public function getOperatorPickUp()
    {
      $operator_code = ['POFR', 'MONR', 'SOGP', 'Chrono', 'CHRP', 'IMXE', 'UPSE', 'PUNT'];

      return $operator_code;
    }

    /**
     * get the good carrier in function the operator
     * @param $operator
     * @return mixed
     */
    public function getCarriersServicePickup($operator)
    {
      $carriers_list = $this->getCarriersList();

      foreach ($carriers_list as $carrier) {
        if (is_array($carrier)) {
          if ($carrier['ope_code'] == $operator) {
            $service = $carrier['srv_code'];
          }
        }
      }

      return $service;
    }

    /**
     * Return a pickup up list
     * @param $country_iso
     * @param $code_postal
     * @param $city
     * @return array
     */

    public function getPickUpList($country_iso, $code_postal, $city)
    {
      $operator_code = $this->getOperatorPickUp();

      $result_carrier_code[] = ['id' => '0', 'text' => CLICSHOPPING::getDef('text_select')];

      foreach ($operator_code as $operator) {
        $list_point = $this->boxtaleShop->getListPoint($country_iso, $code_postal, $city, $operator);

        foreach ($list_point as $carrier) {
          if (is_array($carrier)) {
            foreach ($carrier as $item) {
              if (is_array($item['points'])) {
                foreach ($item['points'] as $points) {
                  if (is_array($points)) {
                    $result_carrier_code[] = ['id' => $points['code'],
                      'text' => $points['city'] . ' / ' . $points['name'] . ' / ' . $points['address'] . ' / ' . $points['code']
                    ];
                  }
                }
              }
            }
          }
        }
      }

      return $result_carrier_code;
    }


    /**
     * @param $hour
     * @return string
     */
    public function getDropDownHour($field, $hour)
    {
      $open = [['id' => '8:00', 'text' => '8:00'],
        ['id' => '8:30', 'text' => '8:30'],
        ['id' => '9:00', 'text' => '9:00'],
        ['id' => '9:30', 'text' => '9:30'],
        ['id' => '10:00', 'text' => '10:00'],
        ['id' => '10:30', 'text' => '10:30'],
        ['id' => '11:00', 'text' => '11:00'],
        ['id' => '11:30', 'text' => '11:30'],
        ['id' => '12:00', 'text' => '12:00'],
        ['id' => '12:30', 'text' => '12:30'],
        ['id' => '13:00', 'text' => '13:00'],
        ['id' => '13:00', 'text' => '13:30'],
        ['id' => '14:00', 'text' => '14:00'],
        ['id' => '14:30', 'text' => '14:30'],
        ['id' => '15:00', 'text' => '15:00'],
        ['id' => '15:30', 'text' => '15:30'],
        ['id' => '16:00', 'text' => '16:00'],
        ['id' => '16:30', 'text' => '16:30'],
        ['id' => '17:00', 'text' => '17:00'],
        ['id' => '17:30', 'text' => '17:30']
      ];

      $operator_code = HTML::selectField($field, $open, $hour);

      return $operator_code;
    }


    public function getParcelPoint($id, $ref = 'pickup_point')
    {
      $lib = new ParcelPoint();

      $lib->setLogin(EMC_USER);
      $lib->setPassword(EMC_PASS);
      $lib->setKey(EMC_KEY);
      $lib->setEnv(EMC_MODE);
      $lib->setLocale('fr-FR'); //en-EN

      $lib->getParcelPoint($ref, $id);

      if (!$lib->curl_error && !$lib->resp_error) {
        foreach ($lib->points['pickup_point'] as $pickup_point) {
          $point_name = $pickup_point['name'] . ', ' . $pickup_point['address'] . ', ' . $pickup_point['zipcode'] . ', ' . $pickup_point['city'];
        }

        return $point_name;
      } else {
        return false;
      }
    }


    /**
     * Default tracking urls.
     * @access public
     * @var array
     */
    public static function trackingUrl($number)
    {

      $tracking_urls = [
        'CHRP_Chrono13' => 'http://www.chronopost.fr/expedier/inputLTNumbersNoJahia.do?lang=fr_FR&listeNumeros=' . $number,
        'CHRP_Chrono13Samedi' => 'http://www.chronopost.fr/expedier/inputLTNumbersNoJahia.do?lang=fr_FR&listeNumeros=' . $number,
        'CHRP_Chrono18' => 'http://www.chronopost.fr/expedier/inputLTNumbersNoJahia.do?lang=fr_FR&listeNumeros=' . $number,
        'CHRP_ChronoRelais' => 'http://www.chronopost.fr/expedier/inputLTNumbersNoJahia.do?lang=fr_FR&listeNumeros=' . $number,
        'CHRP_ChronoRelaisEurope' => 'http://www.chronopost.fr/expedier/inputLTNumbersNoJahia.do?lang=fr_FR&listeNumeros=' . $number,
        'CHRP_ChronoInternationalClassic' => 'http://www.chronopost.fr/expedier/inputLTNumbersNoJahia.do?lang=fr_FR&listeNumeros=' . $number,
        'MONR_CpourToi' => 'http://www.mondialrelay.fr/suivi-de-colis/?NumeroExpedition=@&CodePostal=',
        'MONR_CpourToiEurope' => 'http://www.mondialrelay.fr/suivi-de-colis/?NumeroExpedition=@&CodePostal=',
        'MONR_DomicileEurope' => 'http://www.mondialrelay.fr/suivi-de-colis/?NumeroExpedition=@&CodePostal=',
        'SOGP_RelaisColis' => 'http://relaiscolis.envoimoinscher.com/suivi-colis.html?reference=' . $number,
        'POFR_ColissimoAccess' => 'http://www.colissimo.fr/portail_colissimo/suivreResultat.do?parcelnumber=' . $number,
        'POFR_ColissimoExpert' => 'http://www.colissimo.fr/portail_colissimo/suivreResultat.do?parcelnumber=' . $number,
        'IMXE_PackSuiviEurope' => 'http://www.happy-post.com/envoyer-colis/followUp/',
        'TNTE_ExpressNational' => 'http://www.tnt.fr/public/suivi_colis/recherche/visubontransport.do?radiochoixrecherche=BT&bonTransport=' . $number,
        'TNTE_EconomyExpressInternational' => 'http://www.tnt.fr/public/suivi_colis/recherche/visubontransport.do?radiochoixrecherche=BT&bonTransport=' . $number,
        'FEDX_InternationalEconomy' => 'https://www.fedex.com/apps/fedextrack/?tracknumbers=' . $number,
        'FEDX_InternationalPriorityCC' => 'https://www.fedex.com/apps/fedextrack/?tracknumbers=' . $number,
        'SODX_ExpressStandardInterColisMarch' => 'http://www.sodexi.fr/fr/services/tracing/102.html',
        'DHLE_DomesticExpress' => 'http://www.dhl.fr/content/fr/fr/dhl_express/suivi_expedition.shtml?brand=DHL&AWB=' . $number,
        'DHLE_EconomySelect' => 'http://www.dhl.fr/content/fr/fr/dhl_express/suivi_expedition.shtml?brand=DHL&AWB=' . $number,
        'DHLE_ExpressWorldwide' => 'http://www.dhl.fr/content/fr/fr/dhl_express/suivi_expedition.shtml?brand=DHL&AWB=' . $number,
        'UPSE_ExpressSaver' => 'https://wwwapps.ups.com/WebTracking/track?HTMLVersion=5.0&loc=fr_FR&Requester=UPSHome&WBPM_lid=homepage%252Fct1.html_pnl_trk&track.x=Suivi&trackNums=' . $number,
        'UPSE_Standard' => 'https://wwwapps.ups.com/WebTracking/track?HTMLVersion=5.0&loc=fr_FR&Requester=UPSHome&WBPM_lid=homepage%252Fct1.html_pnl_trk&track.x=Suivi&trackNums=' . $number,
        'UPSE_StandardAP' => 'https://wwwapps.ups.com/WebTracking/track?HTMLVersion=5.0&loc=fr_FR&Requester=UPSHome&WBPM_lid=homepage%252Fct1.html_pnl_trk&track.x=Suivi&trackNums=' . $number,
        'UPSE_StandardDepot' => 'https://wwwapps.ups.com/WebTracking/track?HTMLVersion=5.0&loc=fr_FR&Requester=UPSHome&WBPM_lid=homepage%252Fct1.html_pnl_trk&track.x=Suivi&trackNums=' . $number,
        'UPSE_StandardAPDepot' => 'https://wwwapps.ups.com/WebTracking/track?HTMLVersion=5.0&loc=fr_FR&Requester=UPSHome&WBPM_lid=homepage%252Fct1.html_pnl_trk&track.x=Suivi&trackNums=' . $number,
        'UPSE_StandardEs' => 'https://www.ups.com/WebTracking/track?HTMLVersion=5.0&loc=es_ES&Requester=UPSHome&WBPM_lid=homepage%252Fct1.html_pnl_trk&track.x=Suivi&trackNums=' . $number,
        'UPSE_ExpressSaverEs' => 'https://www.ups.com/WebTracking/track?HTMLVersion=5.0&loc=es_ES&Requester=UPSHome&WBPM_lid=homepage%252Fct1.html_pnl_trk&track.x=Suivi&trackNums=' . $number,
        'PUNT_EntregaenPointsRelais' => 'http://www.puntopack.es/seguir-mi-envio/?NumeroExpedition=@&CodePostal=',
        'PUNT_EntregaenPuntosPack' => 'http://www.puntopack.es/seguir-mi-envio/?NumeroExpedition=@&CodePostal=',
        'PUNT_Domicilio' => 'http://www.puntopack.es/seguir-mi-envio/?NumeroExpedition=@&CodePostal=',
        'SEUR_SeurClassic' => 'http://www.seur.com/seguimiento-online.do',
        'SEUR_SeurInternational' => 'http://www.seur.com/seguimiento-online.do',
        'SEUR_SeurNational' => 'http://www.seur.com/seguimiento-online.do',
        'CREO_CorreosPaq72' => 'http://aplicacionesweb.correos.es/localizadorenvios/track.asp?numero=' . $number
      ];

      return $tracking_urls;
    }
  }
