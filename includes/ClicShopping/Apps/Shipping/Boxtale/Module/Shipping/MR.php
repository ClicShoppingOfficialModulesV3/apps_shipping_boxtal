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

  namespace ClicShopping\Apps\Shipping\Boxtale\Module\Shipping;

  use ClicShopping\OM\HTML;
  use ClicShopping\OM\Registry;
  use ClicShopping\OM\CLICSHOPPING;

  use ClicShopping\Apps\Shipping\Boxtale\Boxtale as BoxtaleApp;

  use ClicShopping\Sites\Common\B2BCommon;

  require_once(CLICSHOPPING::BASE_DIR . 'Apps/Shipping/Boxtale/API/Boxtale/vendor/boxtale/php-library/Emc/WebService.php');
  require_once(CLICSHOPPING::BASE_DIR . 'Apps/Shipping/Boxtale/API/Boxtale/vendor/boxtale/php-library/Emc/Quotation.php');

//  require_once (CLICSHOPPING::BASE_DIR . 'Apps/Shipping/Boxtale/API/Params.php');

  use Emc\Quotation;

  use ClicShopping\Apps\Shipping\Boxtale\Classes\Shop\BoxtaleShop;

  class MR implements \ClicShopping\OM\Modules\ShippingInterface
  {

    public $code;
    public $title;
    public $description;
    public $enabled;
    public $icon;
    public mixed $app;
    public $quotes;

    public function __construct()
    {
      $CLICSHOPPING_Customer = Registry::get('Customer');

      if (Registry::exists('Order')) {
        $CLICSHOPPING_Order = Registry::get('Order');
      }

      if (!Registry::exists('Boxtale')) {
        Registry::set('Boxtale', new BoxtaleApp());
      }

      $this->app = Registry::get('Boxtale');
      $this->app->loadDefinitions('Module/Shop/MR/MR');


      $this->signature = 'Boxtale|' . $this->app->getVersion() . '|1.0';
      $this->api_version = $this->app->getApiVersion();

      $this->code = 'MR';
      $this->title = $this->app->getDef('module_boxtale_mr_title');
      $this->public_title = $this->app->getDef('module_boxtale_mr_public_title');
      $this->sort_order = defined('CLICSHOPPING_APP_BOXTALE_MR_SORT_ORDER') ? CLICSHOPPING_APP_BOXTALE_MR_SORT_ORDER : 0;

// Activation module du paiement selon les groupes B2B
      if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
        if (B2BCommon::getShippingUnallowed($this->code)) {
          if (CLICSHOPPING_APP_BOXTALE_MR_STATUS == 'True') {
            $this->enabled = true;
          } else {
            $this->enabled = false;
          }
        }
      } else {
        if (defined('CLICSHOPPING_APP_BOXTALE_MR_NO_AUTHORIZE') && CLICSHOPPING_APP_BOXTALE_MR_NO_AUTHORIZE == 'True' && $CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
          if ($CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
            if (CLICSHOPPING_APP_BOXTALE_MR_STATUS == 'True') {
              $this->enabled = true;
            } else {
              $this->enabled = false;
            }
          }
        }
      }

      if (defined('CLICSHOPPING_APP_BOXTALE_MR_TAX_CLASS')) {
        if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
          if (B2BCommon::getTaxUnallowed($this->code) || !$CLICSHOPPING_Customer->isLoggedOn()) {
            $this->tax_class = defined('CLICSHOPPING_APP_BOXTALE_MR_TAX_CLASS') ? CLICSHOPPING_APP_BOXTALE_MR_TAX_CLASS : 0;

          }
        } else {
          if (B2BCommon::getTaxUnallowed($this->code)) {
            $this->tax_class = defined('CLICSHOPPING_APP_BOXTALE_MR_TAX_CLASS') ? CLICSHOPPING_APP_BOXTALE_MR_TAX_CLASS : 0;
          }
        }
      }

      if (($this->enabled === true) && ((int)CLICSHOPPING_APP_BOXTALE_MR_ZONE > 0)) {
        $check_flag = false;

        $Qcheck = $this->app->db->get('zones_to_geo_zones', 'zone_id', ['geo_zone_id' => (int)CLICSHOPPING_APP_BOXTALE_MR_ZONE,
          'zone_country_id' => $CLICSHOPPING_Order->delivery['country']['id']
        ],
          'zone_id'
        );

        while ($Qcheck->fetch()) {
          if (($Qcheck->valueInt('zone_id') < 1) || ($Qcheck->valueInt('zone_id') == $CLICSHOPPING_Order->delivery['zone_id'])) {
            $check_flag = true;
            break;
          }
        }

        if ($check_flag === false) {
          $this->enabled = false;
        }
      }

      if (!defined('CLICSHOPPING_APP_BOXTALE_KEY') || empty(CLICSHOPPING_APP_BOXTALE_KEY)) {
        $this->enabled = false;
      }

      if (!defined('CLICSHOPPING_APP_BOXTALE_KEY') || empty(CLICSHOPPING_APP_BOXTALE_PASSWORD)) {
        $this->enabled = false;
      }

      if (!defined('CLICSHOPPING_APP_BOXTALE_USER') || empty(CLICSHOPPING_APP_BOXTALE_USER)) {
        $this->enabled = false;
      }

      if (!defined('CLICSHOPPING_APP_BOXTALE_SERVER') || empty(CLICSHOPPING_APP_BOXTALE_SERVER)) {
        $this->enabled = false;
      }
    }

    public function quote($method = '')
    {
      $CLICSHOPPING_Order = Registry::get('Order');
      $CLICSHOPPING_Tax = Registry::get('Tax');
      $CLICSHOPPING_Template = Registry::get('Template');
      $CLICSHOPPING_Shipping = Registry::get('Shipping');
      $CLICSHOPPING_Mail = Registry::get('Mail');
      $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');


      $header_tag = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.4.0/leaflet.css">';
      $CLICSHOPPING_Template->addBlock($header_tag, 'header_tags');

      $limit = (int)CLICSHOPPING_APP_BOXTALE_MR_LIMIT_DISPLAY;

      $boxtaleShop = new BoxtaleShop();

      $shipping_weight = $CLICSHOPPING_Shipping->getShippingWeight();

      if ($shipping_weight >= 0.1 && $shipping_weight <= 30) {
        $from = ['pays' => CLICSHOPPING_APP_BOXTALE_ISO_COUNTRY,
          'code_postal' => CLICSHOPPING_APP_BOXTALE_POSTCODE,
          'ville' => CLICSHOPPING_APP_BOXTALE_CITY,
          'type' => 'entreprise',
          'adresse' => CLICSHOPPING_APP_BOXTALE_ADDRESS
        ];

        $dest_country = $CLICSHOPPING_Order->delivery['country']['iso_code_2'];
        $code_postal = $CLICSHOPPING_Order->delivery['postcode'];
        $city = $CLICSHOPPING_Order->delivery['city'];

// delivery
        $to = ['pays' => $dest_country,
          'code_postal' => $code_postal,
          'ville' => $city,
          'type' => 'particulier', // accepted values are "entreprise" or "particulier"
          'adresse' => $CLICSHOPPING_Order->delivery['street_address'] . ' ' . $CLICSHOPPING_Order->delivery['suburb']
        ];


//* Optionally you can define which carriers you want to quote if you don't want to quote all carriers
        $additionalParams = ['collecte' => date('Y-m-d'), //(sundays and holidays excluded)
          'delay' => 'aucun',
          'content_code' => 10120,
          'valeur' => $CLICSHOPPING_ShoppingCart->show_total() //"42.655" // (for a cross-boarder quotation)
        ];


        if (CLICSHOPPING_APP_BOXTALE_MR_ALL_SHIPPING == 'False') {
          if (CLICSHOPPING_APP_BOXTALE_MR_POUR_TOI == 'True') {
            $display_shipping[] = [1 => 'MONRCpourToi'];
          }

          if (CLICSHOPPING_APP_BOXTALE_MR_POUR_TOI_EUROPE == 'True') {
            $display_shipping[] = [2 => 'MONRCpourToiEurope'];
          }


          if (CLICSHOPPING_APP_BOXTALE_MR_POUR_TOI_DOMICILE == 'True') {
            $display_shipping[] = [3 => 'MONRDomicileEurope'];
          }


          if (CLICSHOPPING_APP_BOXTALE_MR_RELAIS_COLIS == 'True') {
            $display_shipping[] = [4 => 'SOGPRelaisColis'];
          }

          if (CLICSHOPPING_APP_BOXTALE_MR_CHRONOPOST_RELAIS_PICKUP == 'True') {
            $display_shipping[] = [5 => 'ChronoRelaisPickup'];
          }

          if (CLICSHOPPING_APP_BOXTALE_MR_CHRONOPOST_SHOPTOSHOP == 'True') {
            $display_shipping[] = [6 => 'ChronoShoptoShop'];
          }

          if (CLICSHOPPING_APP_BOXTALE_MR_CHRONOPOST_RELAIS == 'True') {
            $display_shipping[] = [7 => 'CHRPChronoRelais'];
          }

          if (CLICSHOPPING_APP_BOXTALE_MR_CHRONOPOST_EUROPE == 'True') {
            $display_shipping[] = [8 => 'CHRPChronoRelaisEurope'];
          }

          if (CLICSHOPPING_APP_BOXTALE_MR_HAPPY_POST_EUROPE == 'True') {
            $display_shipping[] = [9 => 'IMXEPackSuiviEurope'];
          }

          if (CLICSHOPPING_APP_BOXTALE_MR_UPS_STANDARD_AP == 'True') {
            $display_shipping[] = [10 => 'UPSEStandardAP'];
          }

          if (CLICSHOPPING_APP_BOXTALE_MR_UPS_STANDARD_AP_DEPOT == 'True') {
            $display_shipping[] = [11 => 'UPSEStandardAPDepot'];
          }

          if (CLICSHOPPING_APP_BOXTALE_MR_UPS_STANDARD_DEPOT == 'True') {
            $display_shipping[] = [12 => 'UPSEStandardDepot'];
          }

          if (CLICSHOPPING_APP_BOXTALE_MR_PUNTO_POINT_RELAIS == 'True') {
            $display_shipping[] = [14 => 'PUNTEntregaenPointsRelais'];
          }

          if (CLICSHOPPING_APP_BOXTALE_MR_PUNTO_DOMICILIO == 'True') {
            $display_shipping[] = [15 => 'PUNTEntregaenPointsRelais'];
          }

          $additionalParams['offers'] = $display_shipping;
        }
        /* Parcels informations */
        $item = $CLICSHOPPING_ShoppingCart->get_products();

        $depth = 0;
        $width = 0;
        $height = 0;

        for ($i = 0; $i < count($item); $i++) {
          $depth += (float)$item[$i]['products_dimension_depth'];
          $width += (float)$item[$i]['products_dimension_width'];
          $height += (float)$item[$i]['products_dimension_height'];
        }

// minimum 2cm
        if ($depth < 2) $depth = 2;
        if ($width < 2) $width = 2;
        if ($height < 2) $height = 2;

//minimum 100gr
        if ($shipping_weight < 0.1) $shipping_weight = 0.1;

        $parcels = [
          'type' => 'colis', //"encombrant" (bulky parcel), "colis" (parcel), "palette" (pallet), "pli" (envelope)
          'dimensions' => [
            1 => [
              'poids' => $shipping_weight,
              'longueur' => $depth,
              'largeur' => $width,
              'hauteur' => $height
            ]
          ]
        ];

        $lib = new Quotation();

        $lib->setLogin(EMC_USER);
        $lib->setPassword(EMC_PASS);
        $lib->setKey(EMC_KEY);
        $lib->setEnv(EMC_MODE);
        $lib->setLocale('fr-FR'); //en-EN

        $lib->getQuotation($from, $to, $parcels, $additionalParams);

        if (!$lib->curl_error && !$lib->resp_error) {
          $footer = '<script>$(document).ready(function(){$(\'[data-toggle="popover"]\').popover();});</script>';
          $CLICSHOPPING_Template->addBlock($footer, 'footer_scripts');

          foreach ($lib->offers as $offre) {
            $list_point = $boxtaleShop->getListPoint($dest_country, $code_postal, $city, $offre['operator']['code']);

            $i = 0;

            if (CLICSHOPPING_APP_BOXTALE_MR_LOGO == 'True') {
              $logo = HTML::image($offre['operator']['logo'], HTML::outputProtected($offre['service']['label']), 80, 80);
            }

            foreach ($list_point as $carrier) {
              if (is_array($carrier) && !empty($carrier)) {
                if ($offre['operator']['code'] == $carrier[$i]['operator']) {
                  foreach ($carrier[$i] as $pickup) {
                    if (is_array($pickup)) {
                      $n = 0;

                      foreach ($pickup as $relais) {
                        if ($n < $limit) {
                          $relais_code = $relais['code'];
                          $relais_name = $relais['name'];
                          $relais_address = $relais['address'];
                          $relais_city = $relais['city'];
                          $relais_zipcode = $relais['zipcode'];
                          $relais_phone = $relais['phone'];
                          $relais_latitude = $relais['latitude'];
                          $relais_longitude = $relais['longitude'];

                          $caracteristic_relais = '<h6><small>' . $relais_name . '<br />' . $relais_address . '<br />' . $relais_zipcode . ' ' . $relais_city . '<br />' . $relais_phone . '</small></h6>';

                          if (CLICSHOPPING_APP_BOXTALE_MR_DISPLAY_MAP == 'True') {
                            $geolocalisation = '
                                      <a data-toggle="modal" data-target="#GeoModal' . $relais['code'] . '"> - <i class="fas fa-map-marked-alt text-primary"></i> ' . $this->app->getDef('text_points_relais_map') . '  -</a>
                                      <div class="modal fade" id="GeoModal' . $relais['code'] . '" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
                                        <div class="modal-dialog">
                                          <div class="modal-content">
                                            <div class="modal-body">
                                              <iframe width="450" height="350" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://www.openstreetmap.org/export/embed.html?bbox=' . $relais_longitude . '%2C' . $relais_latitude . '%2C' . $relais_longitude . '%2C' . $relais_latitude . '&amp;layer=mapnik&amp;marker=' . $relais_latitude . '%2C' . $relais_longitude . '" style="border: 1px solid black"></iframe><br/><small><a target="_blank" href="https://www.openstreetmap.org/?mlat=' . $relais_latitude . '&amp;mlon=' . $relais_longitude . '#map=13/' . $relais_latitude . '/' . $relais_longitude . '">' . $caracteristic_relais . '<br /></a></small>
                                            </div>
                                          </div>
                                        </div>
                                      </div>
                                    ';
                          }

                          if (CLICSHOPPING_APP_BOXTALE_MR_DISPLAY_MAP == 'True') {
                            $caracteristic = '<button type="button" id="btn-popover_' . $offre['service']['code'] . '" class="btn btn-info btn-sm" data-toggle="popover" title="info" data-content="' . $offre['delivery']['label'] . ' ' . $offre['delivery']['date'] . '">' . $this->app->getDef('module_boxtale_mr_text_more_info') . '</button>';
                          }

                          $methods[] = ['id' => $relais_code,
                            'title' => $offre['service']['label'] . ' ' . $offre['delivery']['label'] . ' (' . $shipping_weight . ' Kg) <br />' . $caracteristic_relais,
                            'info' => $logo . ' ' . $caracteristic . ' ' . $geolocalisation,
                            'cost' => $offre['price']['tax-exclusive']
                          ];
                        }

                        $n++;
                      }
                    }
                  }
                }
              }

              $i++;
            }
          }

          $this->quotes = ['id' => $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code,
            'module' => $this->public_title,
            'methods' => $methods
          ];


          if ($this->tax_class > 0) {
            $this->quotes['tax'] = $CLICSHOPPING_Tax->getTaxRate($this->tax_class, $CLICSHOPPING_Order->delivery['country']['id'], $CLICSHOPPING_Order->delivery['zone_id']);
          }

          if (defined('CLICSHOPPING_APP_BOXTALE_BX_LOGO') && !empty(CLICSHOPPING_APP_BOXTALE_BX_LOGO)) {
            $this->icon = $CLICSHOPPING_Template->getDirectoryTemplateImages() . 'logos/shipping/' . CLICSHOPPING_APP_BOXTALE_BX_LOGO;
            $this->icon = HTML::image($this->icon, $this->title);
          } else {
            $this->icon = '';
          }

          if (!is_null($this->icon)) $this->quotes['icon'] = '&nbsp;&nbsp;&nbsp;' . $this->icon;

          return $this->quotes;
        } else {
          $email_text_subject = 'API error Boxtal Shipping ' . STORE_NAME;

          $error = array_merge($lib->getApiParam(), array('API response :' => $lib->offers)) . '<br />' . print_r($lib, true);
          $body = 'Error has been detected, please test your website in checkout shipping process : ' . CLICSHOPPING::getConfig('http_server', 'Shop') . ' <br /><br />' . $error;

          $CLICSHOPPING_Mail->clicMail(STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, $email_text_subject, $body, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
        }
      }
    }

    public function check()
    {
      return defined('CLICSHOPPING_APP_BOXTALE_MR_STATUS') && (trim(CLICSHOPPING_APP_BOXTALE_MR_STATUS) != '');
    }

    public function install()
    {
      $this->app->redirect('Configure&Install&module=Boxtale');
    }

    public function remove()
    {
      $this->app->redirect('Configure&Uninstall&module=Boxtale');
    }

    public function keys()
    {
      return array('CLICSHOPPING_APP_BOXTALE_MR_SORT_ORDER');
    }
  }