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


  namespace ClicShopping\Apps\Shipping\Boxtale\Module\Hooks\ClicShoppingAdmin\Orders;

  use ClicShopping\OM\Registry;
  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\HTML;

  use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

  use ClicShopping\Apps\Orders\Orders\Classes\ClicShoppingAdmin\OrderAdmin;

  use ClicShopping\Apps\Shipping\Boxtale\Boxtale as BoxtaleApp;

  use ClicShopping\Apps\Shipping\Boxtale\Classes\ClicShoppingAdmin\BoxtaleAdmin;

  require_once(CLICSHOPPING::BASE_DIR . 'Apps/Shipping/Boxtale/API/Boxtale/vendor/boxtale/php-library/Emc/WebService.php');
  require_once(CLICSHOPPING::BASE_DIR . 'Apps/Shipping/Boxtale/API/Boxtale/vendor/boxtale/php-library/Emc/Quotation.php');

  use \Emc\Quotation;

  class Update implements \ClicShopping\OM\Modules\HooksInterface
  {
    protected $app;
    protected $result;
    protected $order_id;
    protected $boxtalAdmin;

    protected $height;
    protected $lenght;
    protected $width;
    protected $weight;
    protected $saturday;
    protected $carrier_list;
    protected $content_category;
    protected $colis_description;

    protected $date_collecte;
    protected $disponibilite_hde;
    protected $operatorPickupCode;
    protected $operatorCode;
    protected $service;
    protected $customer_notified;

    public function __construct()
    {

      if (!Registry::exists('Boxtal')) {
        Registry::set('Boxtal', new BoxtaleApp());
      }

      $this->app = Registry::get('Boxtal');
      $this->boxtalAdmin = new BoxtaleAdmin();

      $this->order_id = HTML::sanitize($_GET['oID']);

      if (defined('CLICSHOPPING_APP_BOXTALE_BX_STATUS') && CLICSHOPPING_APP_BOXTALE_BX_STATUS == 'True') {

        $this->height = HTML::sanitize($_POST['create_order_create_height']);
        $this->lenght = HTML::sanitize($_POST['create_order_create_lenght']);
        $this->width = HTML::sanitize($_POST['create_order_create_width']);
        $this->weight = HTML::sanitize($_POST['create_order_create_weight']);

        $this->customer_notified = HTML::sanitize($_POST['customer_notified']);
        $this->saturday = HTML::sanitize($_POST['create_order_create_saturday_delivery']);
        $this->carrier_list = HTML::sanitize($_POST['carrier_list']);
        $this->content_category = HTML::sanitize($_POST['content_category']);
        $this->colis_description = HTML::sanitize($_POST['boxtal_description']);

        $this->date_collecte = HTML::sanitize($_POST['boxtal_date_collecte']);
        $this->disponibilite_hde = $_POST['boxtal_disponibilite_hde'];
        $this->operatorPickupCode = HTML::sanitize($_POST['boxtal_operator']);

        $operator_code = explode('-', $this->operatorPickupCode); //array(2) { [0]=> string(4) "MONR" [1]=> string(5) "03980" }
        $this->operatorCode = $operator_code[0];
        $this->service = $this->boxtalAdmin->getCarriersServicePickup($operator_code[0]); //MONR
      }


      $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');
    }

    public function getMakeOrder()
    {
      $CLICSHOPPING_Mail = Registry::get('Mail');

      define('EMC_MODE', CLICSHOPPING_APP_BOXTALE_SERVER);
      define('EMC_USER', CLICSHOPPING_APP_BOXTALE_USER);
      define('EMC_PASS', CLICSHOPPING_APP_BOXTALE_PASSWORD);
      define('EMC_KEY', CLICSHOPPING_APP_BOXTALE_KEY);

      $Qorders = $this->app->db->get('orders', 'orders_id', ['orders_id' => (int)$this->order_id]);

      if ($Qorders->fetch()) {
        Registry::set('Order', new OrderAdmin($Qorders->valueInt('orders_id')));
        $order = Registry::get('Order');
      }

      $total_order = $order->info['total'];
      preg_match_all('/\d+\.?\d*/', $total_order, $matches);
      $total_order = $matches[0][0];

      $result_explode = explode('|', $this->carrier_list);

      $this->carrier_list_ope_code = $result_explode[0];
      $this->carrier_list_srv_code = $result_explode[1];

      if ($this->lenght < 2) $this->lenght = 2;
      if ($this->width < 2) $this->width = 2;
      if ($this->height < 2) $this->height = 2;

      if ($this->saturday == 'on') {
        $this->saturday = true;
      } else {
        $this->saturday = false;
      }

      if (!empty(CLICSHOPPING_APP_BOXTALE_ISO_STATE)) {
        $state = CLICSHOPPING_APP_BOXTALE_ISO_STATE;
      } else {
        $state = '';
      }

      $from = [
        'country' => CLICSHOPPING_APP_BOXTALE_ISO_COUNTRY,  // must be an ISO code, set get_country example on how to get codes
        'state' => CLICSHOPPING_APP_BOXTALE_ISO_STATE,  //if required, state must be an ISO code as well
        'type' => 'company', // accepted values are "company" or "individual"
        'zipcode' => CLICSHOPPING_APP_BOXTALE_POSTCODE,
        'city' => CLICSHOPPING_APP_BOXTALE_CITY,
        'address' => CLICSHOPPING_APP_BOXTALE_ADDRESS,
        'title' => 'M', // accepted values are "M" (sir) or "Mme" (madam)
        'firstname' => CLICSHOPPING_APP_BOXTALE_FIRST_NAME,
        'lastname' => CLICSHOPPING_APP_BOXTALE_LAST_NAME,
        'societe' => CLICSHOPPING_APP_BOXTALE_COMPANY, // company name
        'email' => CLICSHOPPING_APP_BOXTALE_EMAIL, //$this->customer['email_address']
        'phone' => CLICSHOPPING_APP_BOXTALE_PHONE, //$this->customer['telephone']'0606060606',
//              'infos' => 'Some additional information about this address',
      ];

      $QcountryIsoCode = $this->app->db->prepare('select countries_iso_code_2,
                                                           countries_id
                                                   from :table_countries
                                                   where countries_name = :countries_name
                                                  ');
      $QcountryIsoCode->bindValue(':countries_name', $order->delivery['country']);
      $QcountryIsoCode->execute();


      if (!empty($order->delivery['state'])) {
        $QstateIsoCode = $this->app->db->prepare('select zone_code
                                                   from :table_zones
                                                   where zone_country_id = :zone_country_id
                                                   and zone_name = :zone_name
                                                  ');
        $QstateIsoCode->bindValue(':zone_country_id', $QcountryIsoCode->value('countries_id'));
        $QstateIsoCode->bindValue(':zone_name', $order->delivery['state']);

        $QstateIsoCode->execute();
        $state_iso_code = $QstateIsoCode->value('zone_code');
      } else {
        $state_iso_code = '';
      }

      $to = [
        'country' => $QcountryIsoCode->value('countries_iso_code_2'), //'FR', //$dest_country,  // must be an ISO code, set get_country example on how to get codes
        'state' => $state_iso_code, //$Qorders->delivery['state']  if required, state must be an ISO code as well
        'zipcode' => $order->delivery['postcode'],
        'city' => $order->delivery['city'],
        'address' => $order->delivery['street_address'] . ' ' . $order->delivery['suburb'],
        'type' => 'individual', // accepted values are "company" or "individual"
        'title' => '', // accepted values are "M" (sir) or "Mme" (madam)
        'firstname' => '-', // $Qorders->delivery['first_name'],
        'lastname' => $order->customer['name'], // $Qorders->delivery['lastname'],
        'email' => $order->customer['email_address'], //$Qorders->delivery['email'],
        'phone' => $order->customer['telephone'], // $Qorders->delivery['phone'],
//            'infos' => ''
      ];


      /* Parcel information */
      $parcels = [
        'type' => 'colis', // your shipment type: "encombrant" (bulky parcel), "colis" (parcel), "palette" (pallet), "pli" (envelope)
        'dimensions' => [
          1 => [
            'poids' => $this->weight, // parcel weight
            'longueur' => $this->lenght, // parcel length
            'largeur' => $this->width, // parcel width
            'hauteur' => $this->height // parcel height
          ]
        ]
      ];

      /*
       * $additionalParams contains all additional parameters for your request, it includes filters or offer's options
       * A list of all possible parameters is available here : http://ecommerce.envoimoinscher.com/api/documentation/commandes/
       * For an order, you have to provide at least all offer's mandatory parameters returned by the quotation
       * You can also find all optional parameters (filter not included) in the same quotation
       */

      if ($this->operatorPickupCode != '0') {
        $additionalParams = [
          'collection_date' => $this->date_collecte,
          'delay' => 'aucun',
          'content_code' => $this->content_category,  // List of the available codes at samples/get_categories.php > List of contents
          'colis.description' => $this->colis_description,
          'assurance.selection' => false, // whether you want an extra insurance or not
          'saturdaydelivery.selection' => $this->saturday, // set this to true if you want to select a saturday delivery offer (paying option not available for all carriers)
          'url_push' => CLICSHOPPING::getConfig('http_server', 'Shop') . '?order=' . $this->order_id . '&key=' . $this->order_id,
          'depot.pointrelais' => $this->operatorCode . '-POST', // if not a parcel-point use {operator code}-POST like "CHRP-POST"
          'retrait.pointrelais' => $this->operatorPickupCode, // if not a parcel-point use {operator code}-POST like "CHRP-POST"
          'operator' => $this->operatorCode,
          'service' => $this->service, //'CpourToi',
          'colis.valeur' => $total_order, // prefixed with your shipment type: "encombrant" (bulky parcel), "colis" (parcel), "palette" (pallet), "pli" (envelope)
          'disponibilite_hde' => $this->disponibilite_hde
        ];


      } else {
        $additionalParams = ['collection_date' => $this->date_collecte, //date('Y-m-d'),
          'delay' => 'aucun',
          'content_code' => $this->content_category,  // List of the available codes at samples/get_categories.php > List of contents
          'colis.description' => $this->colis_description,
          'assurance.selection' => false, // whether you want an extra insurance or not
          'saturdaydelivery.selection' => $this->saturday, // set this to true if you want to select a saturday delivery offer (paying option not available for all carriers)
          'url_push' => CLICSHOPPING::getConfig('http_server', 'Shop') . '?order=' . $this->order_id . '&key=' . $this->order_id, //www.mon-site.com?order=number&key=number
          'depot.pointrelais' => $this->carrier_list_ope_code . '-POST', // if not a parcel-point use {operator code}-POST like "CHRP-POST"
          'retrait.pointrelais' => $this->carrier_list_ope_code . '-POST', // if not a parcel-point use {operator code}-POST like "CHRP-POST"
          'operator' => $this->carrier_list_ope_code,
          'service' => $this->carrier_list_srv_code, //'CpourToi',
          'colis.valeur' => $total_order, // prefixed with your shipment type: "encombrant" (bulky parcel), "colis" (parcel), "palette" (pallet), "pli" (envelope)
          'disponibilite_hde' => $this->disponibilite_hde
          // for insurance params, see http://ecommerce.envoimoinscher.com/api/documentation/commandes/
        ];
      }


// Prepare and execute the request
      $lib = new Quotation();

      $lib->setLogin(EMC_USER);
      $lib->setPassword(EMC_PASS);
      $lib->setKey(EMC_KEY);
      $lib->setEnv(EMC_MODE);
      $lib->setLocale('fr-FR'); //en-EN

//Type : pli, colis, encombrant, palette.
//  weight, length, width and c.
      $dimensions = [1 => ['weight' => $this->weight,
        'length' => $this->lenght,
        'width' => $this->width,
        'height' => $this->height,
      ]
      ];

      $lib->setType('colis', $dimensions);

      $orderPassed = $lib->makeOrder($from, $to, $parcels, $additionalParams);

      if (!$lib->curl_error && !$lib->resp_error) {
        if ($orderPassed) {
          $this->result = $lib->order['ref'];
        } else {
          $email_text_subject = 'Your order has been refused ' . STORE_NAME;

          $body = 'Your order has been refused by Boxtal API<br /> Please retry or make a manual order' . STORE_NAME . ' : ' . CLICSHOPPING::getConfig('http_server', 'Shop') . ' <br />';

          $CLICSHOPPING_Mail->clicMail(STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, $email_text_subject, $body, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

          $this->result = false;
        }
      } else {
        $email_text_subject = 'API error Boxtal Shipping ' . STORE_NAME;

        $error = array_merge($lib->getApiParam(), array('API response :' => $lib->offers)) . '<br />' . print_r($lib, true);
        $body = 'Error has been detected, please test your website in checkout shipping process : ' . CLICSHOPPING::getConfig('http_server', 'Shop') . ' <br /><br />' . $error;

        $CLICSHOPPING_Mail->clicMail(STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, $email_text_subject, $body, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

        $this->result = false;
      }

      return $this->result;
    }


    public function execute()
    {

      if (!defined('CLICSHOPPING_APP_BOXTALE_BX_STATUS') || CLICSHOPPING_APP_BOXTALE_BX_STATUS == 'False') {
        return false;
      }

      if (isset($_GET['Orders']) && isset($_GET['Update']) && isset($_GET['Boxtal'])) {
        $result = $this->getMakeOrder();

        if ($result !== false) {
          $QOrdersStatusHistory = $this->app->db->prepare('select orders_status_id
                                                          from :table_orders_status_history
                                                          where orders_id = :orders_id
                                                          order by orders_status_history_id desc
                                                          limit 1
                                                        ');
          $QOrdersStatusHistory->bindInt('orders_id', $this->order_id);
          $QOrdersStatusHistory->execute();

          if (empty($this->operatorPickupCode)) {
            $carrier_list = $this->carrier_list;
          }

          if (!empty($this->operatorPickupCode)) {
            $operatorPickupCode = $this->operatorPickupCode;
          }

          $data_array = ['order_id' => (int)$this->order_id,
            'boxtal_id' => $result,
            'date_added' => 'now()',
            'admin_user_name' => AdministratorAdmin::getUserAdmin(),
            'orders_status_id' => (int)$QOrdersStatusHistory->valueInt('orders_status_id'),
            'product_height' => (float)$this->height,
            'product_width' => (float)$this->width,
            'product_lenght' => (float)$this->lenght,
            'product_weight' => (float)$this->weight,
            'saturday_delivery' => (int)$this->delivery,
            'content_category' => $this->content_category,
            'carrier_list' => $carrier_list,
            'short_description' => $this->colis_description,
            'date_collecte' => $this->date_collecte,
            'disponibilite_hde' => $this->disponibilite_hde,
            'pickup_operator_list' => $operatorPickupCode,
          ];

          $this->app->db->save('boxtal_shipping', $data_array);
        }
      }

      CLICSHOPPING::redirect(null, 'A&Orders\Orders&Edit&oID=' . $this->order_id);
    }
  }