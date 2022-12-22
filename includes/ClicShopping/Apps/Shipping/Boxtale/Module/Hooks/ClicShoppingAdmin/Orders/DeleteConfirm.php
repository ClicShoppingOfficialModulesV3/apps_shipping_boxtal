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
  use ClicShopping\OM\HTML;

  use ClicShopping\Apps\Shipping\Boxtale\Boxtale as BoxtaleApp;

  class DeleteConfirm implements \ClicShopping\OM\Modules\HooksInterface
  {
    protected $app;
    protected $result;
    protected $order_id;

    public function __construct()
    {

      if (!Registry::exists('Boxtal')) {
        Registry::set('Boxtal', new BoxtaleApp());
      }

      $this->app = Registry::get('Boxtal');
      $this->order_id = HTML::sanitize($_GET['oID']);
    }

    public function execute()
    {

      if (!defined('CLICSHOPPING_APP_BOXTALE_BX_STATUS') || CLICSHOPPING_APP_BOXTALE_BX_STATUS == 'False') {
        return false;
      }

      if (isset($_GET['Orders']) && isset($_GET['DeleteConfirm']) && $this->order_id && isset($_GET['Boxtal'])) {
        $id = HTML::sanitize($_GET['Id']);
        $this->app->db->delete('boxtal_shipping', ['id' => (int)$id]);
      } else {
        if (isset($_GET['Orders']) && isset($_GET['DeleteConfirm']) && $this->order_id && isset($_GET['Boxtal'])) {
          $this->app->db->delete('boxtal_shipping', ['order_id' => (int)$this->order_id]);
        }
      }

      CLICSHOPPING::redirect(null, 'A&Orders\Orders&Edit&oID=' . $this->order_id);
    }
  }