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
  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\DateTime;

  use ClicShopping\Apps\Shipping\Boxtale\Boxtale as BoxtaleApp;

  use ClicShopping\Apps\Shipping\Boxtale\Classes\ClicShoppingAdmin\BoxtaleAdmin;
  use ClicShopping\Apps\Shipping\Boxtale\Classes\ClicShoppingAdmin\BoxtalWeight;
  use ClicShopping\Apps\Shipping\Boxtale\Classes\Shop\BoxtaleShop;


  class PageTab implements \ClicShopping\OM\Modules\HooksInterface
  {
    protected $app;
    protected $boxtaleShop;
    protected $boxtalAdmin;

    public function __construct()
    {
      if (!Registry::exists('Boxtal')) {
        Registry::set('Boxtal', new BoxtaleApp());
      }

      $this->app = Registry::get('Boxtal');
      $this->boxtalAdmin = new BoxtaleAdmin();
      $this->boxtaleShop = new BoxtaleShop();
    }


    public function display()
    {
      global $order;

      if (!defined('CLICSHOPPING_APP_BOXTALE_BX_STATUS') || CLICSHOPPING_APP_BOXTALE_BX_STATUS == 'False') {
        return false;
      }

      $CLICSHOPPING_Weight = new BoxtalWeight();

      $oID = HTML::sanitize($_GET['oID']);

      $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Orders/page_tab');

      $QBoxtal = $this->app->db->prepare('select id,
                                                  order_id,
                                                  boxtal_id,
                                                  date_added,
                                                  admin_user_name,
                                                  orders_status_id,
                                                  product_height,
                                                  product_width,
                                                  product_lenght,
                                                  product_weight,
                                                  saturday_delivery,
                                                  content_category,
                                                  carrier_list,
                                                  short_description,
                                                  date_collecte,
                                                  disponibilite_hde,
                                                  pickup_operator_list
                                           from :table_boxtal_shipping
                                           where order_id = :order_id     
                                        ');

      $QBoxtal->bindInt(':order_id', $oID);
      $QBoxtal->execute();

      if ($QBoxtal->fetch() === false) {
        $products_width = 0;
        $products_height = 0;
        $products_weight = 0;
        $products_depth = 0;
        $date_collecte = null;
        foreach ($order->products as $value) {
          $products_id = $value['products_id'];

          $QProduct = $this->app->db->prepare('select products_weight,
                                                      products_dimension_width,
                                                      products_dimension_height,
                                                      products_dimension_depth
                                               from :table_products
                                               where products_id = :products_id
                                              ');
          $QProduct->bindInt(':products_id', $products_id);
          $QProduct->execute();

          $products_width += $QProduct->value('products_dimension_width');
          $products_height += $QProduct->value('products_dimension_height');
          $products_depth += $QProduct->value('products_dimension_depth');

          $products_weight += $CLICSHOPPING_Weight->convert($QProduct->value('products_weight'), 2, SHIPPING_WEIGHT_UNIT);
        }

        if ($products_width == 0) $products_width = 1;
        if ($products_height == 0) $products_height = 1;
        if ($products_depth == 0) $products_depth = 1;

      } else {
        $boxtalid = $QBoxtal->valueInt('id');
        $products_width = $QBoxtal->valueDecimal('product_width');
        $products_height = $QBoxtal->valueDecimal('product_height');
        $products_weight = $QBoxtal->valueDecimal('product_weight');
        $products_depth = $QBoxtal->valueDecimal('product_lenght');
        $date_time = $QBoxtal->value('date_added');

        $boxtal_id = $QBoxtal->value('boxtal_id');
        $saturday_delivery = $QBoxtal->valueInt('saturday_delivery');
        $content_category = $QBoxtal->valueInt('content_category');
        $carrier_list = $QBoxtal->value('carrier_list');
        $short_description = $QBoxtal->value('short_description');

        if (!empty($QBoxtal->value('date_collecte'))) {
          $date_collecte = DateTime::toShortWithoutFormat($QBoxtal->value('date_collecte'));
        } else {
          $date_collecte = null;
        }

        $disponibilite_hde = $QBoxtal->value('disponibilite_hde');
        $pickup_operator_list = $QBoxtal->value('pickup_operator_list');
      }

      $carriers_list = $this->boxtalAdmin->getCarriersList();
      $code_array = $this->boxtalAdmin->getHomeCarrier($carriers_list);

//carrier drop down
      $dest_country = $order->delivery['country'];

      $QCountryIso = $this->app->db->get('countries', 'countries_iso_code_2', ['countries_name' => $dest_country]);

      if ($QCountryIso->fetch()) {
        $country_iso = $QCountryIso->value('countries_iso_code_2');
      }

      $code_postal = $order->delivery['postcode'];
      $city = $order->delivery['city'];


      $boxtal_button = '<div class="row">';
      $boxtal_button .= '<div class="col-md-12 text-end" id="boxtalButton">';
      $boxtal_button .= '<span class="col-md-2"><a href="https://www.boxtal.com" target="_blank" class="btn btn-info btn-sm" role="button">' . $this->app->getDef('text_boxtal_manual_order_button') . '</a></span>';
      $boxtal_button .= '</div>';
      $boxtal_button .= '</div>';

      $content = '<!-- boxtal start -->';

      if (empty($boxtal_id)) {
        $result_carrier_code = $this->boxtalAdmin->getPickUpList($country_iso, $code_postal, $city);

        $content .= HTML::form('boxtal_status', CLICSHOPPING::link(NULL, 'A&Orders\Orders&Orders&Update&Boxtal&oID=' . $oID));

        $content .= '<div class="separator"></div>';
        $content .= '<div class="col-md-12">';

        $content .= '<div class="row">';
        $content .= '<span class="col-md-3" id="boxtalDelivery">';
        $content .= $this->app->getDef('text_confirm_create_saturday_delivery') . ' ' . HTML::checkboxField('create_order_create_saturday_delivery') . '<br />';
        $content .= '</span>';

        $content .= '<span class="col-md-6" id="boxtalContentCategory">' . $this->app->getDef('text_confirm_content_categorie') . ' ' . $this->boxtalAdmin->getContentCategory() . '</span>';
        $content .= '<span class="col-md-3" id="boxtalDateCollecte">';
        $content .= $this->app->getDef('text_date_collecte') . ' ' . HTML::inputField('boxtal_date_collecte', $date_collecte, 'required aria-required="true" placeholder="' . $this->app->getDef('text_date_collecte') . '"', 'date');
        $content .= ' <br />' . $this->app->getDef('text_hour_available_collecte') . ' ' . $this->boxtalAdmin->getDropDownHour('boxtal_disponibilite_hde', $disponibilite_hde, 'id="sort"');
        $content .= '</span>';
        $content .= '</div>';

        $content .= '<div class="row">' . $this->app->getDef('text_info_delivery_pickup') . '</div>';
        $content .= '<div class="separator"></div>';
        $content .= '<div class="row">';

        $content .= '
            <div class="card-deck">
              <div class="card col-md-6">
                <div class="card-body">
                  <h4 class="card-title">' . $this->app->getDef('text_delivery_pickup') . '</h4>
                  <span class="card-title">' . $this->app->getDef('text_relais_pickup') . '</span>
                  <span class="card-text">' . HTML::selectField('boxtal_operator', $result_carrier_code, null, 'id="sortDropDownListByText"') . '</span>
                </div>
              </div>
              ';

        $content .= '              
              <div class="card col-md-6">
                <div class="card-body">
                  <h4 class="card-title">' . $this->app->getDef('text_delivery_home') . '</h4>
                  <span class="card-title">' . $this->app->getDef('text_confirm_carrier_list') . '</span>
                  <span class="card-text">' . HTML::selectField('carrier_list', $code_array, null, 'id="sortDropDownListByText"') . '</span>
                </div>
              </div>
            </div>
         ';

        $content .= '</div>';

        $content .= '<div class="separator"></div>';
        $content .= '<div class="row">';
        $content .= '<span class="col-md-6" id="boxtalInfosProducts">' . $this->app->getDef('text_confirm_infos_products') . '</span>';
        $content .= '</div>';
        $content .= '<div class="separator"></div>';
        $content .= '<div class="row">';
        $content .= '<span class="col-md-6" id="boxtalHeight">' . $this->app->getDef('text_confirm_create_height') . ' ' . HTML::inputField('create_order_create_height', $products_height, 'id="create_order_create_saturday_delivery"') . '</span>';
        $content .= '<span class="col-md-6" id="boxtalLenghr">' . $this->app->getDef('text_confirm_create_lenght') . ' ' . HTML::inputField('create_order_create_lenght', $products_depth, 'id="create_order_create_saturday_delivery"') . '</span>';
        $content .= '</div>';

        $content .= '<div class="row">';
        $content .= '<span class="col-md-6" id="boxtalWidth">' . $this->app->getDef('text_confirm_create_width') . ' ' . HTML::inputField('create_order_create_width', $products_width, 'id="create_order_create_saturday_delivery"') . '</span>';
        $content .= '<span class="col-md-6" id="boxtalWeight">' . $this->app->getDef('text_confirm_create_weight') . ' ' . HTML::inputField('create_order_create_weight', $products_weight, 'id="create_order_create_saturday_delivery"') . '</span>';
        $content .= '</div>';

        $content .= '<div class="separator"></div>';
        $content .= '<div class="row">';
        $content .= '<span class="col-md-4" id="boxtalDescription">' . $this->app->getDef('text_confirm_short_description') . ' ' . HTML::inputField('boxtal_description', $short_description, 'required aria-required="true" placeholder="' . $this->app->getDef('text_confirm_short_description') . '"') . '</span>';
        $content .= '</div>';

        $content .= '<div class="row">';
        $content .= '<div class="col-md-12 text-end" id="boxtalConfirm">';
        $content .= '<span class="col-md-2">' . HTML::button($this->app->getDef('text_boxtal_create_order_button'), null, null, 'danger') . '<span>';
        $content .= '</div>';


        $content .= '</div>';
        $content .= '</form>';
      }

      if (!empty($boxtal_id)) {
        $result_order_status = $this->boxtalAdmin->getStatus($boxtal_id);

        if ($result_order_status !== false) {
          $content .= '<div class="separator"></div>';
          $content .= '<div class="alert alert-info" role="alert">';
          $content .= '<span class="text-center"><strong>' . $this->app->getDef('text_boxtal_info_order') . '</strong></span>';
          $content .= '<br /><br />';
          $content .= $this->app->getDef('text_boxtal_orders_status') . '<br />';
          $content .= $this->app->getDef('text_boxtal_info_order_ref') . ' ' . $result_order_status['emcRef'] . '<br />';
          $content .= $this->app->getDef('text_boxtal_info_order_state') . ' ' . $result_order_status['state'] . '<br />';
          $content .= '<div class="separator"></div>';

          if ($saturday_delivery == 1) {
            $saturday_delivery = $this->app->getDef('text_boxtal_yes');
          } else {
            $saturday_delivery = $this->app->getDef('text_boxtal_no');
          }

          $content .= $this->app->getDef('text_boxtal_orders_info_statut_delivery') . ' ' . $saturday_delivery . '<br />';
          $content .= $this->app->getDef('text_boxtal_orders_info_content_categories') . ' ' . $content_category . '<br />';

          if (is_null($pickup_operator_list)) {
            $content .= $this->app->getDef('text_boxtal_orders_info_carrier_list') . ' ' . $carrier_list . '<br />';
          }

          $content .= $this->app->getDef('text_boxtal_orders_info_short_description') . ' ' . $short_description . '<br />';

          if ($result_order_status['labelAvailable'] == 1) {
            $content .= $this->app->getDef('text_boxtal_info_order_label_url') . ' ' . '<a href="' . $result_order_status['labelUrl'] . '" target="_blank" rel="noopener">' . $result_order_status['labelUrl'] . '</a><br />';
          }

          $content .= $this->app->getDef('text_date_collecte') . ' ' . $date_collecte . '<br />';
          $content .= $this->app->getDef('text_disponibilite_hde') . ' ' . $disponibilite_hde . '<br />';

          if (!is_null($pickup_operator_list)) {
            $pickup_point = $this->boxtalAdmin->getParcelPoint($pickup_operator_list);
            $content .= $this->app->getDef('text_relais_pickup') . ' ' . $pickup_operator_list . '  - ' . $pickup_point . '<br />';
          }

          $content .= $this->app->getDef('text_boxtal_tracking') . ' ' . '<a href="https://www.boxtal.com/fr/en/app/mon-suivi?reference=' . $result_order_status['emcRef'] . '" target="_blank" rel="noopener">https://www.boxtal.com/fr/en/app/mon-suivi?reference=' . $result_order_status['emcRef'] . '</a><br />';

          $content .= '</div>';

        } else {

          $content .= '<div class="separator"></div>';
          $content .= '<div class="alert alert-warning" role="alert">';
          $content .= $this->app->getDef('text_boxtal_orders_status_alert') . '<br />';
          $content .= '<div class="separator"></div>';
          $content .= $this->app->getDef('text_boxtal_orders_error_info') . '<br />';
          $content .= '<div class="separator"></div>';
          $content .= $this->app->getDef('text_boxtal_orders_info_ref') . ' ' . $boxtal_id . '<br />';
          $content .= $this->app->getDef('text_boxtal_orders_info_ref') . ' ' . $date_time . '<br />';

          if ($saturday_delivery == 1) {
            $saturday_delivery = $this->app->getDef('text_boxtal_yes');
          } else {
            $saturday_delivery = $this->app->getDef('text_boxtal_no');
          }

          $content .= $this->app->getDef('text_boxtal_orders_info_statut_delivery') . ' ' . $saturday_delivery . '<br />';
          $content .= $this->app->getDef('text_boxtal_orders_info_content_categories') . ' ' . $content_category . '<br />';
          $content .= $this->app->getDef('text_boxtal_orders_info_carrier_list') . ' ' . $carrier_list . '<br />';
          $content .= $this->app->getDef('text_boxtal_orders_info_short_description') . ' ' . $short_description . '<br />';
          $content .= '<div class="separator"></div>';

          $content .= HTML::form('boxtal_status', CLICSHOPPING::link(NULL, 'A&Orders\Orders&Orders&DeleteConfirm&Boxtal&Id=' . $boxtalid));
          $content .= '<div class="row">';
          $content .= '<div class="col-md-12 text-end" id="boxtalDeleteOrder">';
          $content .= '<span class="col-md-2">' . HTML::button($this->app->getDef('text_boxtal_delete_order_button'), null, null, 'danger') . '</span>';
          $content .= '</div>';
          $content .= '</div>';
          $content .= '</form>';
          $content .= '</div>';
        }
      }

      $content .= '</div>';
      $content .= '<div class="separator"></div>';
      $content .= '<div>';

      $family = [
        '1' => '<span class="label label-warning">Economy</span>',
        '2' => '<span class="label label-danger">Express</span>'
      ];

      $zone = [
        '1' => '<span class="label label-success">FR</span>',
        '2' => '<span class="label label-primary">INTER</span>',
        '3' => '<span class="label label-info">EU</span>',
        'zone_fr' => ' <span class="label label-primary">FR</span>',
        'zone_eu' => ' <span class="label label-info">EU</span>',
        'zone_int' => ' <span class="label label-default">INTER</span>'
      ];

      if ($carriers_list !== false) {
        $content .= ' <div class="mainTitle"><strong>' . $this->app->getDef('text_carrier_list') . '</strong></div>';
        $content .= '<div class="adminformTitle">';

        $content .= '<div class="separator"></div>';
        $content .= '
                  <div class="row">
                    <table class="table table-hover table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>' . $this->app->getDef('text_boxtal_operator') . '</th>
                                <th>' . $this->app->getDef('text_boxtal_service') . '</th>
                                <th>' . $this->app->getDef('text_boxtal_delivery_time') . '</th>
                                <th widh="150">' . $this->app->getDef('text_boxtal_specification') . '</ths>
                                <th>' . $this->app->getDef('text_boxtal_family') . '</th>
                                <th widh="150">' . $this->app->getDef('text_boxtal_zone') . '</th>
                                <th>' . $this->app->getDef('text_boxtal_drop_off') . '</th>
                                <th>' . $this->app->getDef('text_boxtal_pick_up') . 'p</th>
                            </tr>
                        </thead>
                    ';

        $border = '';

        foreach ($carriers_list as $carrier) {
          if (!isset($tmpCarrier)) {
            $tmpCarrier = $carrier['ope_code'];
            $border = 'blDefault';
          } elseif ($tmpCarrier != $carrier['ope_code']) {
            $border = ($border == 'blDefault' ? 'blActive' : 'blDefault');
            $tmpCarrier = $carrier['ope_code'];
          }


          if (!is_null($carrier['details'])) {
            $carriers_details = implode('<br/>- ', $carrier['details']);
          }

          $content .= '
                        <tr>
                          <td class="strong  ' . $border . '">' . $carrier['ope_name'] . ' / ' . $carrier['ope_code'] . '</td>
                          <td>' . $carrier['srv_name_bo'] . ' / ' . $carrier['srv_code'] . ' </td>
                          <td>' . $carrier['description'] . ' / ' . $carrier['delivery_due_time'] . '</td>
                          <td>' . $carriers_details . '</td>
                          <td>' . $family[$carrier['family']] . '</td>
                        ';

          $allZones = ($carrier['zone_fr'] == '1' ? $zone['zone_fr'] : '') . '<br />';
          $allZones .= ($carrier['zone_eu'] == '1' ? $zone['zone_eu'] : '') . '<br />';
          $allZones .= ($carrier['zone_int'] == '1' ? $zone['zone_int'] : '') . '<br />';
          $content .= '<td>';

          $content .= $allZones;

          if (!empty($carrier['zone_restriction'])) {
            $content .= ' / Restriction : ' . $carrier['zone_restriction'];
          }

          $content .= '</td>';
          $content .= ' <td>' . $carrier['parcel_dropoff_point'] . '  ' . $carrier['dropoff_place'] . '</td>
                        <td>' . $carrier['parcel_pickup_point'] . ' ' . $carrier['pickup_place'] . '</td>
                      ';
          $content .= '</tr>';
        }

        $content .= '</table>';
        $content .= '</div>';
        $content .= '</div>';
      }

      $content .= '<!-- boxtal end -->';

      $script = '<script src="' . CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/sort_drop_down_list.js') . '"></script>';

      $tab_title = $this->app->getDef('tab_boxtal');

      $output = <<<EOD
<div class="tab-pane" id="section_BoxtalApp_content">
  <div class="mainTitle"></div>
  <div class="adminformTitle">
    <div class="separator"></div>
{$boxtal_button}
{$content}
  </div>
</div>


<script>
$('#section_BoxtalApp_content').appendTo('#orderTabs .tab-content');
$('#orderTabs .nav-tabs').append('    <li class="nav-item"><a data-target="#section_BoxtalApp_content" role="tab" data-toggle="tab" class="nav-link">{$tab_title}</a></li>');
</script>

<script>var map = {}; $('select option').each(function () { if (map[this.value]) { $(this).remove() } map[this.value] = true;})</script>
{$script}
EOD;

      return $output;
    }
  }