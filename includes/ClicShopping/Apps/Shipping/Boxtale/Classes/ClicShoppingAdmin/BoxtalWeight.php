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

  use ClicShopping\Apps\Configuration\Weight\Classes\Shop\Weight;

  class BoxtalWeight extends \ClicShopping\Apps\Configuration\Weight\Classes\Shop\Weight
  {

    public function __construct()
    {
      $this->weight = new Weight();
    }

    public function convert($value, $unit_from, $unit_to)
    {
      $convert = $this->weight->convert($value, $unit_from, $unit_to);

      return $convert;
    }

  }
