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


  namespace ClicShopping\Apps\Shipping\Boxtale\Sites\ClicShoppingAdmin\Pages\Home\Actions\Configure;

  use ClicShopping\Apps\Orders\Orders\Module\ClicShoppingAdmin\Config\OD\Params\status;
  use ClicShopping\OM\Registry;

  use ClicShopping\OM\Cache;
  use ClicShopping\OM\CLICSHOPPING;

  class Install extends \ClicShopping\OM\PagesActionsAbstract
  {

    public function execute()
    {

      $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
      $CLICSHOPPING_Boxtale = Registry::get('Boxtale');

      $current_module = $this->page->data['current_module'];

      $CLICSHOPPING_Boxtale->loadDefinitions('Sites/ClicShoppingAdmin/install');

      $m = Registry::get('BoxtaleAdminConfig' . $current_module);
      $m->install();

      static::installDbMenuAdministration();
      static::installDb();

      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Boxtale->getDef('alert_module_install_success'), 'success', 'Boxtale');

      $CLICSHOPPING_Boxtale->redirect('Configure&module=' . $current_module);
    }

    private static function installDbMenuAdministration()
    {
      $CLICSHOPPING_Db = Registry::get('Db');
      $CLICSHOPPING_Boxtale = Registry::get('Boxtale');
      $CLICSHOPPING_Language = Registry::get('Language');
      $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_shipping_boxtale']);

      if ($Qcheck->fetch() === false) {

        $sql_data_array = ['sort_order' => 4,
          'link' => 'index.php?A&Shipping\Boxtale&Configure&module=BX',
          'image' => 'modules_shipping.gif',
          'b2b_menu' => 0,
          'access' => 1,
          'app_code' => 'app_shipping_boxtale'
        ];

        $insert_sql_data = ['parent_id' => 449];

        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

        $id = $CLICSHOPPING_Db->lastInsertId();

        $languages = $CLICSHOPPING_Language->getLanguages();

        for ($i = 0, $n = count($languages); $i < $n; $i++) {

          $language_id = $languages[$i]['id'];

          $sql_data_array = ['label' => $CLICSHOPPING_Boxtale->getDef('title_menu')];

          $insert_sql_data = ['id' => (int)$id,
            'language_id' => (int)$language_id
          ];

          $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

          $CLICSHOPPING_Db->save('administrator_menu_description', $sql_data_array);

        }

        Cache::clear('menu-administrator');
      }
    }

    private static function installDb()
    {
      $CLICSHOPPING_Db = Registry::get('Db');

      $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_boxtal_shipping"');

      if ($Qcheck->fetch() === false) {
        $sql = <<<EOD
      CREATE TABLE :table_boxtal_shipping (
        id int(11) NOT NULL,
        order_id int(11) NOT NULL,
        boxtal_id varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
        date_added datetime NOT NULL,
        admin_user_name varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        orders_status_id int(11) NOT NULL,
        product_height decimal(15,4) NOT NULL,
        product_width decimal(15,4) NOT NULL,
        product_lenght decimal(15,4) NOT NULL,
        product_weight decimal(15,4) NOT NULL,
        saturday_delivery char(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
        content_category int(255) NOT NULL,
        carrier_list varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
        short_description varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
        date_collecte datetime NOT NULL,
        disponibilite_hde varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
        pickup_operator_list varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL        
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

      ALTER TABLE :table_boxtal_shipping ADD PRIMARY KEY (id);
      ALTER TABLE :table_boxtal_shipping MODIFY id int(11) NOT NULL AUTO_INCREMENT;
EOD;

        $CLICSHOPPING_Db->exec($sql);

      }
    }
  }
