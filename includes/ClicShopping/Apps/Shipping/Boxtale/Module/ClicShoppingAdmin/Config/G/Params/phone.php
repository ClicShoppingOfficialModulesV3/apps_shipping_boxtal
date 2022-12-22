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

  namespace ClicShopping\Apps\Shipping\Boxtale\Module\ClicShoppingAdmin\Config\G\Params;

  class phone extends \ClicShopping\Apps\Shipping\Boxtale\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
  {
    public $default = '';
    public $sort_order = 150;

    protected function init()
    {
      $this->title = $this->app->getDef('cfg_boxtale_g_phone_title');
      $this->description = $this->app->getDef('cfg_boxtale_g_phone_desc');
    }
  }
