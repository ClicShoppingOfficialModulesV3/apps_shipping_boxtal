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

  class BX implements \ClicShopping\OM\Modules\ShippingInterface
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
      $this->app->loadDefinitions('Module/Shop/BX/BX');


      $this->signature = 'Boxtale|' . $this->app->getVersion() . '|1.0';
      $this->api_version = $this->app->getApiVersion();

      $this->code = 'BX';
      $this->title = $this->app->getDef('module_boxtale_bx_title');
      $this->public_title = $this->app->getDef('module_boxtale_bx_public_title');
      $this->sort_order = defined('CLICSHOPPING_APP_BOXTALE_BX_SORT_ORDER') ? CLICSHOPPING_APP_BOXTALE_BX_SORT_ORDER : 0;

// Activation module du paiement selon les groupes B2B
      if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
        if (B2BCommon::getShippingUnallowed($this->code)) {
          if (CLICSHOPPING_APP_BOXTALE_BX_STATUS == 'True') {
            $this->enabled = true;
          } else {
            $this->enabled = false;
          }
        }
      } else {
        if (defined('CLICSHOPPING_APP_BOXTALE_BX_NO_AUTHORIZE') && CLICSHOPPING_APP_BOXTALE_BX_NO_AUTHORIZE == 'True' && $CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
          if ($CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
            if (CLICSHOPPING_APP_BOXTALE_BX_STATUS == 'True') {
              $this->enabled = true;
            } else {
              $this->enabled = false;
            }
          }
        }
      }

      if (defined('CLICSHOPPING_APP_BOXTALE_BX_TAX_CLASS')) {
        if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
          if (B2BCommon::getTaxUnallowed($this->code) || !$CLICSHOPPING_Customer->isLoggedOn()) {
            $this->tax_class = defined('CLICSHOPPING_APP_BOXTALE_BX_TAX_CLASS') ? CLICSHOPPING_APP_BOXTALE_BX_TAX_CLASS : 0;

          }
        } else {
          if (B2BCommon::getTaxUnallowed($this->code)) {
            $this->tax_class = defined('CLICSHOPPING_APP_BOXTALE_BX_TAX_CLASS') ? CLICSHOPPING_APP_BOXTALE_BX_TAX_CLASS : 0;
          }
        }
      }

      if (($this->enabled === true) && ((int)CLICSHOPPING_APP_BOXTALE_BX_ZONE > 0)) {
        $check_flag = false;

        $Qcheck = $this->app->db->get('zones_to_geo_zones', 'zone_id', ['geo_zone_id' => (int)CLICSHOPPING_APP_BOXTALE_BX_ZONE,
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

// delivery
        $to = ['pays' => $dest_country,
          'code_postal' => $CLICSHOPPING_Order->delivery['postcode'],
          'ville' => $CLICSHOPPING_Order->delivery['city'],
          'type' => 'particulier', // accepted values are "entreprise" or "particulier"
          'adresse' => $CLICSHOPPING_Order->delivery['street_address'] . ' ' . $CLICSHOPPING_Order->delivery['suburb']
        ];


//* Optionally you can define which carriers you want to quote if you don't want to quote all carriers
        $additionalParams = ['collecte' => date('Y-m-d'), //(sundays and holidays excluded)
          'delay' => 'aucun',
          'content_code' => 10120,
          'valeur' => $CLICSHOPPING_ShoppingCart->show_total() //"42.655" // (for a cross-boarder quotation)
        ];

//tnt
        if (CLICSHOPPING_APP_BOXTALE_BX_ALL_SHIPPING == 'False') {

          if (CLICSHOPPING_APP_BOXTALE_BX_COLISSIMO == 'True') {
            $display_shipping[] = [5 => 'POFRColissimoAccess'];
          }

          if (CLICSHOPPING_APP_BOXTALE_BX_COLISSIMO_EXPERT == 'True') {
            $display_shipping[] = [6 => 'POFRColissimoExpert'];
          }

          if (CLICSHOPPING_APP_BOXTALE_BX_CHRONOPOST == 'True') {
            $display_shipping[] = [7 => 'CHRPChrono13'];
          }

          if (CLICSHOPPING_APP_BOXTALE_BX_CHRONOPOST_SAMEDI == 'True') {
            $display_shipping[] = [8 => 'CHRPChrono13Samedi'];
          }

          if (CLICSHOPPING_APP_BOXTALE_BX_CHRONOPOST_18 == 'True') {
            $display_shipping[] = [9 => 'CHRPChrono18'];
          }

          if (CLICSHOPPING_APP_BOXTALE_BX_CHRONOPOST_INTERNATIONAL_CLASSIC == 'True') {
            $display_shipping[] = [12 => 'CHRPChronoInternationalClassic'];
          }

          if (CLICSHOPPING_APP_BOXTALE_BX_UPS_EXPRESS == 'True') {
            $display_shipping[] = [13 => 'UPSEExpressSaver'];
          }

          if (CLICSHOPPING_APP_BOXTALE_BX_UPS_STANDARD == 'True') {
            $display_shipping[] = [14 => 'UPSEStandard'];
          }

          if (CLICSHOPPING_APP_BOXTALE_BX_UPS_STANDARD_ES == 'True') {
            $display_shipping[] = [18 => 'UPSEStandardEs'];
          }

          if (CLICSHOPPING_APP_BOXTALE_BX_DHL_EXPRESS == 'True') {
            $display_shipping[] = [19 => 'DHLEExpressWorldwide'];
          }

          if (CLICSHOPPING_APP_BOXTALE_BX_DHL_DOMESTIC_EXPRESS == 'True') {
            $display_shipping[] = [21 => 'DHLEDomesticExpress'];
          }

          if (CLICSHOPPING_APP_BOXTALE_BX_DHL_ECONOMY_SELECT == 'True') {
            $display_shipping[] = [22 => 'DHLEEconomySelect'];
          }

          if (CLICSHOPPING_APP_BOXTALE_BX_FEDEX_INTERNATIONAL_ECONOMY == 'True') {
            $display_shipping[] = [23 => 'FEDXInternationalEconomy'];
          }

          if (CLICSHOPPING_APP_BOXTALE_BX_FEDEX_INTERNATIONAL_PRIORITY_CC == 'True') {
            $display_shipping[] = [24 => 'FEDXInternationalPriorityCC'];
          }

          if (CLICSHOPPING_APP_BOXTALE_BX_SODEXI_STANTARD_COLIS_MARCH == 'True') {
            $display_shipping[] = [28 => 'SODXExpressStandardInterColisMarch'];
          }

          if (CLICSHOPPING_APP_BOXTALE_BX_SEUR_CLASSIC == 'True') {
            $display_shipping[] = [30 => 'SEURSeurClassic'];
          }

          if (CLICSHOPPING_APP_BOXTALE_BX_SEUR_INTERNATIONAL == 'True') {
            $display_shipping[] = [31 => 'SEURSeurInternational'];
          }

          if (CLICSHOPPING_APP_BOXTALE_BX_SEUR_NATIONAL == 'True') {
            $display_shipping[] = [32 => 'SEURSeurNational'];
          }

          if (CLICSHOPPING_APP_BOXTALE_BX_CORREOS == 'True') {
            $display_shipping[] = [33 => 'CREOCorreosPaq72'];
          }

          if (CLICSHOPPING_APP_BOXTALE_BX_CORREOS == 'True') {
            $display_shipping[] = [33 => 'CREOCorreosPaq72'];
          }

          if (CLICSHOPPING_APP_BOXTALE_BX_TNT_EXPRESS_NATIONAL == 'True') {
            $display_shipping[] = [34 => 'TNTEExpressNational'];
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

            $caracteristic = '<button type="button" id="btn-popover_' . $offre['service']['code'] . '" class="btn btn-info btn-sm" data-toggle="popover" title="info" data-content="' . $offre['delivery']['label'] . ' ' . $offre['delivery']['date'] . '">' . $this->app->getDef('module_boxtale_bx_text_more_info') . '</button>';

            if (CLICSHOPPING_APP_BOXTALE_BX_LOGO == 'True') {
              $logo = HTML::image($offre['operator']['logo'], HTML::outputProtected($offre['service']['label']), 80, 80);
            }

            $methods[] = ['id' => $offre['service']['code'],
              'title' => $offre['service']['label'] . ' ' . $offre['delivery']['label'] . ' (' . $shipping_weight . ' Kg) <br />' . $logo . ' ' . $caracteristic,
              'cost' => $offre['price']['tax-exclusive']
            ];

          }

          $this->quotes = ['id' => $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code,
            'module' => $this->public_title,
            'methods' => $methods
          ];


          if ($this->tax_class > 0) {
            $this->quotes['tax'] = $CLICSHOPPING_Tax->getTaxRate($this->tax_class, $CLICSHOPPING_Order->delivery['country']['id'], $CLICSHOPPING_Order->delivery['zone_id']);
          }

          if (!empty(CLICSHOPPING_APP_BOXTALE_BX_LOGO)) {
            $this->icon = $CLICSHOPPING_Template->getDirectoryTemplateImages() . 'logos/shipping/' . CLICSHOPPING_APP_BOXTALE_BX_LOGO;
            $this->icon = HTML::image($this->icon, $this->title);
          } else {
            $this->icon = '';
          }

          if (!is_null($this->icon)) $this->quotes['icon'] = '&nbsp;&nbsp;&nbsp;' . $this->icon;

          return $this->quotes;
        } else {
          $email_text_subject = 'API error Boxtal Shipping ' . STORE_NAME;
          print_r($lib, true);


          if (is_array($lib->offers) && !empty($lib->offers)) {
            $offer[] = $lib->offers;
          } else {
            $offer[] = '';
          }
          /*
                    $error = array_merge($lib->getApiParam(), array('API response :' => $offer)) . '<br />' . print_r($lib, true);
          
                    $body = 'Error has been detected, please test your website in checkout shipping process : ' . CLICSHOPPING::getConfig('http_server', 'Shop') . ' <br /><br />'. $error;
          
                    $CLICSHOPPING_Mail->clicMail(STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, $email_text_subject, $body, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
          */
        }
      }
    }

    public function check()
    {
      return defined('CLICSHOPPING_APP_BOXTALE_BX_STATUS') && (trim(CLICSHOPPING_APP_BOXTALE_BX_STATUS) != '');
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
      return array('CLICSHOPPING_APP_BOXTALE_BX_SORT_ORDER');
    }
  }