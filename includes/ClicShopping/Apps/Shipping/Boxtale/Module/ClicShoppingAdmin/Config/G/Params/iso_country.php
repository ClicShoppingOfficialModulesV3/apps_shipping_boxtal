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

  class iso_country extends \ClicShopping\Apps\Shipping\Boxtale\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
  {
    public $default = 'FR';
    public $sort_order = 60;

    protected function init()
    {
      $this->title = $this->app->getDef('cfg_boxtale_g_iso_country_title');
      $this->description = $this->app->getDef('cfg_boxtale_g_iso_country_desc');
    }
  }
