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

  namespace ClicShopping\Apps\Shipping\Boxtale\Classes\Shop;

  use ClicShopping\OM\Registry;
  use ClicShopping\OM\CLICSHOPPING;

  require_once(CLICSHOPPING::BASE_DIR . 'Apps/Shipping/Boxtale/API/Boxtale/vendor/boxtale/php-library/Emc/WebService.php');
  require_once(CLICSHOPPING::BASE_DIR . 'Apps/Shipping/Boxtale/API/Boxtale/vendor/boxtale/php-library/Emc/ListPoints.php');


  use \Emc\ListPoints;

  class BoxtaleShop
  {
    protected $lisPoint;

    public function __construct()
    {

      if (defined('CLICSHOPPING_APP_BOXTALE_SERVER')) {
        define('EMC_MODE', CLICSHOPPING_APP_BOXTALE_SERVER);
        define('EMC_USER', CLICSHOPPING_APP_BOXTALE_USER);
        define('EMC_PASS', CLICSHOPPING_APP_BOXTALE_PASSWORD);
        define('EMC_KEY', CLICSHOPPING_APP_BOXTALE_KEY);
      }
    }

    public function getListPoint($country, $code_postal, $city, $operator_code, $collecte = 'dest')
    {
      if (defined('CLICSHOPPING_APP_BOXTALE_SERVER') || defined(EMC_MODE)) {
        $lib = new ListPoints();

        $params = ['pays' => $country,
          'cp' => $code_postal,
          'ville' => $city,
          'collecte' => $collecte
        ];

        $relais = [$operator_code];

        $lib->getListPoints($relais, $params);


        // Display the parcel points
        if (!$lib->curl_error && !$lib->resp_error) {
          $week_days = array(1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday');
        } else {
          $lib = print_r(array_merge($lib->getApiParam(), array('API response :' => $lib->list_points)));
        }

        return $lib;
      }
    }
  }
