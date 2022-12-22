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


  namespace ClicShopping\Apps\Shipping\Boxtale\Module\ClicShoppingAdmin\Config\MR\Params;

  use ClicShopping\OM\HTML;

  class pour_toi_domicile extends \ClicShopping\Apps\Shipping\Boxtale\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
  {
    public $default = 'False';
    public $sort_order = 132;

    protected function init()
    {
      $this->title = $this->app->getDef('cfg_boxtale_mr_pour_toi_domicile_title');
      $this->description = $this->app->getDef('cfg_boxtale_mr_pour_toi_domicile_description');
    }

    public function getInputField()
    {
      $value = $this->getInputValue();

      $input = HTML::radioField($this->key, 'True', $value, 'id="' . $this->key . '1" autocomplete="off"') . $this->app->getDef('cfg_boxtale_mr_pour_toi_domicile_true') . ' ';
      $input .= HTML::radioField($this->key, 'False', $value, 'id="' . $this->key . '2" autocomplete="off"') . $this->app->getDef('cfg_boxtale_mr_pour_toi_domicile_false');

      return $input;
    }
  }