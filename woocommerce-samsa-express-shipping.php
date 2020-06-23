<?php
/*
  Plugin Name: Woocommerce SMSA Express Shipping
  Plugin URI: http://www.jem-products.com/plugins.html
  Description: Provides shipping for Woocommerce based upon a table of weights. Unlimited countries.
  Version: 2.0.8
  Author: Krishna Mishra
  Author URI: http://www.jem-products.com
  Requires at least: 4.0
  Tested up to: 5.3.2
  WC requires at least: 3.0
  WC tested up to: 4.0.1
 */

//test
if (!defined('ABSPATH'))
    exit; // Exit if accessed directly


//lets define some constants
define('SES_DOMAIN', 'ses-smsa-express-shipping-for-woocommerce');
define('SES_URL', plugin_dir_url(__FILE__));  // Plugin URL

/* include files for rating and deactivate message */
include_once plugin_dir_path(__FILE__).'wc-samsa-settings-tab.php'; // Plugin deactivation functionality
include_once plugin_dir_path(__FILE__).'samsa_express_shipping_model.php'; // Plugin deactivation functionality
// include_once plugin_dir_path(__FILE__).'woocommerce-samsa-express-plugin_functions.php'; // Plugin deactivation functionality
include_once plugin_dir_path(__FILE__).'woocommerce-samsa-express-shipment.php'; // Plugin deactivation functionality
include_once plugin_dir_path(__FILE__).'samsa_track.php'; // Plugin deactivation functionality

/**
 * Check if WooCommerce is active
 */
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    function ses_samsa_express_init() {
        if (!class_exists('Ses_Samsa_Express_Shipping_Method')) {

            class Ses_Samsa_Express_Shipping_Method extends WC_Shipping_Method {

                //Field declarations
                private $ses_shipping_method_order_option;
                private $zones_settings;
                private $rates_settings;
                private $option_key;
                private $ses_shipping_methods_option;
                private $condition_array;
                private $options;
                private $country_array;
                private $counter;

                /**
                 * Constructor for your shipping class

                 */
                public function __construct($instance_id = 0) {
                    $this->instance_id = absint($instance_id);
                    $this->id = 'ses_samsa_express';      // Id for your shipping method. Should be uunique.
                    $this->method_title = __('SMSA Express', 'SES_DOMAIN');  // Title shown in admin
                    $this->method_description = __('SMSA Express lets you define shipping based on a table of values', 'SES_DOMAIN'); // Description shown in admin
                    $this->ses_shipping_method_order_option = 'ses_samsa_express_shipping_method_order_' . $this->instance_id;
                    $this->supports = array(
                        'shipping-zones',
                        'instance-settings',
                    );
                    $this->zones_settings = $this->id . 'zones_settings';
                    $this->rates_settings = $this->id . 'rates_settings';
                    $this->enabled = "yes";         // This can be added as an setting but for this example its forced enabled
                    $this->title = "SMSA Express";     // This can be added as an setting but for this example its forced.

                    $this->option_key = $this->id . '_samsa_expresss';   //The key for wordpress options
                    $this->ses_shipping_methods_option = 'ses_samsa_express_shipping_methods_' . $this->instance_id;
                    $this->options = array();         //the actual tabel rate options saved
                    $this->condition_array = array();    //holds an array of CONDITIONS for the select
                    $this->country_array = array();     //holds an array of COUNTRIES for the select
                    $this->counter = 0;         //we use this to keep unique names for the rows


                    $this->title = $this->get_option('title');

                    $this->init();
                    $this->enabled = $this->get_option('enabled');
                    $this->title = $this->get_option('title');

                    $this->get_options();           //load the options
                }

                /**
                 * Init your settings
                 *
                 * @access public
                 * @return void
                 */

                function init() {
                    $this->instance_form_fields = array(
                        'enabled' => array(
                            'title' => __('Enable/Disable', 'SES_DOMAIN'),
                            'type' => 'checkbox',
                            'label' => __('Enable this shipping method', 'SES_DOMAIN'),
                            'default' => 'no'
                        ),
                        'title' => array(
                            'title' => __('Checkout Title', 'SES_DOMAIN'),
                            'description' => __('This controls the title which the user sees during checkout.', 'SES_DOMAIN'),
                            'type' => 'text',
                            'default' => 'SMSA Express',
                            'desc_tip' => true
                        ),
                        'handling_fee' => array(
                            'title' => __('Handling Fee', 'SES_DOMAIN'),
                            'description' => __('Enter an amount for the handling fee - leave BLANK to disable.', 'SES_DOMAIN'),
                            'type' => 'hidden',
                            'default' => ''
                        ),
                        'tax_status' => array(
                            'title' => __('Tax Status', 'SES_DOMAIN'),
                            'type' => 'select',
                            'default' => 'taxable',
                            'options' => array(
                                'taxable' => __('Taxable', 'SES_DOMAIN'),
                                'notax' => __('Not Taxable', 'SES_DOMAIN'),
                            )
                        ),
                        'shipping_list' => array(
                            'type' => 'shipping_list'
                        )
                    );
                    // Load the settings API
                    $this->init_form_fields();  // This is part of the settings API. Override the method to add your own settings
                    $this->init_settings();  // This is part of the settings API. Loads settings you previously init.


                    //set up the select arrays
                    $this->create_select_arrays();

                    // Save settings in admin if you have any defined
                    add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
                    //And save our options
                    add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_custom_settings'));
                }

                /**
                 * * This initialises the form field
                 */
                function init_form_fields() {

                    $this->form_fields = array(
                        'shipping_list' => array(
                            'title' => __('Shipping Methods', 'SES_DOMAIN'),
                            'type' => 'shipping_list',
                            'description' => '',
                        )
                    );
                }


                /**
                 * admin_options
                 * These generates the HTML for all the options
                 */
                public function generate_samsa_expresss_table_html($key, $data) {
                    ob_start();
                    if (isset($_GET['action'])) {
                        $get_action_name = $_GET['action'];
                    }
                    ?>



                    <script>
						jQuery(document).ready(function(){
							//add shipping box on page load by default. // removes an ability to click on "Add New Shipping Zone" button
							if( jQuery('.rate-row').length == 0 ){
								var zoneID = "#" + pluginID + "_settings";
								//ok lets add a row!
								var id = "#" + pluginID + "_settings table tbody tr:last";
								//create empty row
								var row = {};
								row.key = "";
								row.min = [];
								row.rates = [];
								row.condition = [];
								row.countries = [];
								jQuery(id).before(create_zone_row(row));
							}
						});

                    </script>

                    <!--  end email -->
                    <tr>
                        <th scope="row" class="titledesc"><?php _e('SMSA Expresss', 'SES_DOMAIN'); ?></th>
                        <td id="<?php echo $this->id; ?>_settings">
                            <table class="shippingrows widefat">
                                <col style="width:0%">
                                <col style="width:0%">
                                <col style="width:0%">
                                <col style="width:100%;">
                                <tbody style="border: 1px solid black;">
                                    <tr style="border: 1px solid black;">
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <?php
                    $zone = WC_Shipping_Zones::get_zone_by('instance_id', $_GET['instance_id']);
                    $get_shipping_method_by_instance_id = WC_Shipping_Zones::get_shipping_method($_GET['instance_id']);
                    $link_content = '<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping') . '">' . __('Shipping Zones', 'woocommerce') . '</a> &gt ';
                    $link_content .= '<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&zone_id=' . absint($zone->get_id())) . '">' . esc_html($zone->get_zone_name()) . '</a> &gt ';
                    $link_content .= '<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&instance_id=' . $_GET['instance_id']) . '">' . esc_html($get_shipping_method_by_instance_id->get_title()) . '</a>';
//                                        <!--check action is new or edit-->
                    if ($get_action_name == 'new') {
                        $link_content .= ' &gt ';
                        $link_content .= __('Add New', 'flexible-shipping');
                        ?>
                        <script>
                            jQuery("#mainform h2").first().replaceWith('<h2>' + '<?php echo $link_content; ?>' + '</h2>');
                            var options = <?php echo json_encode($this->create_dropdown_options()); ?>;

                            var country_array = <?php echo json_encode($this->country_array); ?>;
                            var condition_array = <?php echo json_encode($this->condition_array); ?>;
                            var pluginID = <?php echo json_encode($this->id); ?>;
                            console.log('test NISL 1');
                            var lastID = 0;

                        <?php
                        //
                        foreach ($this->options as $key => $value) {
                            global $row;
                            //add the key back into the json object
                            $value['key'] = $key;
                            $row = json_encode($value);
                            echo "jQuery('#{$this->id}_settings table tbody tr:last').before(create_zone_row({$row}));\n";
                        }
                        ?>





                            /**
                             * This creates a new ZONE row
                             */
                            function create_zone_row(row) {

                                //lets get the ID of the last one

                                var el = '#' + pluginID + '_settings .jem-zone-row';
                                lastID = jQuery(el).last().attr('id');

                                //Handle no rows
                                if (typeof lastID == 'undefined' || lastID == "") {
                                    lastID = 1;
                                } else {
                                    lastID = Number(lastID) + 1;
                                }

                                var html = '\
                                                        <tr style="display:none;" id="' + lastID + '" class="jem-zone-row" >\
                                                                <input type="hidden" value="' + lastID + '" name="key[' + lastID + ']"></input>\
                                                                <td><input type="hidden" size="30" name="zone-name[' + lastID + ']"/></td>\
                                                        </tr>\
                                        ';

                                //This is the expandable/collapsable row for that holds the rates
                                html += '\
                                                <tr class="jem-rate-holder">\
                                                        <td colspan="3">\
                                                                <table class="jem-rate-table shippingrows widefat" id="' + lastID + '_rates">\
                                                                        <thead>\
                                                                                <tr>\
                                                                                        <th></th>\
																						<th style="width: 30%">Condition</th>\
                                                                                        <th style="width: 30%">Min Value</th>\
                                                                                        <th style="width: 30%">Max Value</th>\
                                                                                        <th style="width: 40%">Shipping Rate</th>\
                                                                                </tr>\
                                                                        </thead>\
                                                                        ' + create_rate_row(lastID, row) + '\
                                                                        <tr>\
                                                                                <td colspan="4" class="add-rate-buttons">\
                                                                                        <a href="#" class="add button" name="key_' + lastID + '">Add New Rate</a>\
                                                                                        <a href="#" class="delete button">Delete Selected Rates</a>\
                                                                                </td>\
                                                                        </tr>\
                                                                </table>\
                                                        </td>\
                                                </tr>\
                                        ';

                                return html;
                            }

                            /**
                             * This creates a new RATE row
                             * The container Table is passed in and this row is added to it
                             */
                            function create_rate_row(lastID, row) {


                                if (row == null || row.rates.length == 0) {
                                    //lets manufacture a rows
                                    //create dummy row
                                    var row = {};
                                    row.key = "";
                                    row.condition = [""];
                                    row.countries = [];
                                    row.rates = [];
                                    row.rates.push([]);
                                    row.rates[0].min = "";
                                    row.rates[0].max = "";
                                    row.rates[0].shipping = "";
                                }
                                //loop thru all the rate data and create rows

                                //handles if there are no rate rows yet
                                if (typeof (row.min) == 'undefined' || row.min == null) {
                                    row.min = [];
                                }

                                var html = '';
                                for (var i = 0; i < 1; i++) {
                                    html += '\
                                                        <tr>\
                                                                <td>\
                                                                        <input type="checkbox" class="jem-rate-checkbox" id="' + lastID + '"></input>\
                                                                </td>\
																<td>\
                                                                        <select name="conditions[' + lastID + '][]">\
                                                                        ' + generate_condition_html() + '\
                                                                        </select>\
                                                                </td>\
                                                                <td>\
                                                                        <input type="text" size="20" placeholder="" name="min[' + lastID + '][]"></input>\
                                                                </td>\
                                                                <td>\
                                                                        <input type="text" size="20" placeholder="" name="max[' + lastID + '][]"></input>\
                                                                </td>\
                                                                <td>\
                                                                        <input type="text" size="10" placeholder="" name="shipping[' + lastID + '][]"></input>\
                                                                </td>\
                                                        </tr>\
                                                ';



                                }


                                return html;
                            }

                            /**
                             * Handles the expansion contraction of the rate table for the zone
                             */
                            function expand_contract() {

                                var row = jQuery(this).parent('td').parent('tr').next();

                                if (jQuery(row).hasClass('jem-hidden-row')) {
                                    jQuery(row).removeClass('jem-hidden-row').addClass('jem-show-row');
                                    jQuery(this).removeClass('expand-icon').addClass('collapse-icon');
                                } else {
                                    jQuery(row).removeClass('jem-show-row').addClass('jem-hidden-row');
                                    jQuery(this).removeClass('collapse-icon').addClass('expand-icon');
                                }



                            }


                            //**************************************
                            // Generates the HTML for the country
                            // select. Uses an array of keys to
                            // determine which ones are selected
                            //**************************************
                            function generate_country_html(keys) {

                                html = "";

                                for (var key in country_array) {

                                    html += '<option value="' + key + '">' + country_array[key] + '</option>';

                                }

                                return html;
                            }


                            //**************************************
                            // Generates the HTML for the CONDITION
                            // select. Uses an array of keys to
                            // determine which ones are selected
                            //**************************************
                            function generate_condition_html(keys) {

                                html = "";

                                for (var key in condition_array) {

                                    html += '<option value="' + key + '">' + condition_array[key] + '</option>';
                                }

                                return html;
                            }

                            //***************************
                            // Handle add/delete clicks
                            //***************************

                            //ZONE TABLE


                            /*
                             * add new ZONE row
                             */
                            var zoneID = "#" + pluginID + "_settings";

                            jQuery(zoneID).on('click', '.add-zone-buttons a.add', function () {

                                //ok lets add a row!


                                var id = "#" + pluginID + "_settings table tbody tr:last";
                                //create empty row
                                var row = {};
                                row.key = "";
                                row.min = [];
                                row.rates = [];
                                row.condition = [];
                                row.countries = [];
                                jQuery(id).before(create_zone_row(row));

                                //turn on select2 for our row
                                if (jQuery().chosen) {
                                    jQuery("select.chosen_select").chosen({
                                        width: '350px',
                                        disable_search_threshold: 5
                                    });
                                } else {
                                    jQuery("select.chosen_select").select2();
                                }


                                return false;
                            });

                            /**
                             * Delete ZONE row
                             */
                            jQuery(zoneID).on('click', '.add-zone-buttons a.delete', function () {

                                //loop thru and see what is checked - if it is zap it!
                                var rowsToDelete = jQuery(this).closest('table').find('.jem-zone-checkbox:checked');

                                jQuery.each(rowsToDelete, function () {

                                    var thisRow = jQuery(this).closest('tr');
                                    //first lets get the next sibl;ing to this row
                                    var nextRow = jQuery(thisRow).next();

                                    //it should be a rate row
                                    if (jQuery(nextRow).hasClass('jem-rate-holder')) {
                                        //remove it!
                                        jQuery(nextRow).remove();
                                    } else {
                                        //trouble at mill
                                        return;
                                    }

                                    jQuery(thisRow).remove();
                                });

                                //TODO - need to delete associated RATES

                                return false;
                            });


                            //RATE TABLES

                            /**
                             * ADD RATE BUTTON
                             */
                            jQuery(zoneID).on('click', '.add-rate-buttons a.add', function () {

                                //we need to get the key of this zone - it's in the name of of the button
                                var name = jQuery(this).attr('name');
                                name = name.substring(4);

                                //remove key_
                                //ok lets add a row!


                                var row = create_rate_row(name, null);
                                jQuery(this).closest('tr').before(row);

                                return false;
                            });

                            /**
                             * Delete RATE roe
                             */
                            jQuery(zoneID).on('click', '.add-rate-buttons a.delete', function () {

                                //loop thru and see what is checked - if it is zap it!
                                var rowsToDelete = jQuery(this).closest('table').find('.jem-rate-checkbox:checked');

                                jQuery.each(rowsToDelete, function () {
                                    jQuery(this).closest('tr').remove();
                                });


                                return false;
                            });

                            //These handle building the select arras


                        <?php
                        echo "jQuery('#{$this->id}_settings').on('click', '.jem-expansion', expand_contract) ;\n";
                        ?>
                        </script>
                        <?php
                    } else {
                        $method_id = $_GET['method_id'];
                        $get_shipping_methods_options = get_option($this->ses_shipping_methods_option, array());
                        $shipping_method_array = $get_shipping_methods_options[$method_id];
                        $get_selected_method_title = $shipping_method_array['method_title'];
                        if (isset($shipping_method_array['method_title']) && $shipping_method_array['method_title'] != '') {
                            $link_content .= ' &gt ';
                            $link_content .= esc_html($shipping_method_array['method_title']);
                        }
                        ?>
                        <script>
                            jQuery('#mainform h2').first().replaceWith('<h2>' + '<?php echo $link_content; ?>' + '</h2>');
                            var options = <?php echo json_encode($this->create_dropdown_options()); ?>;

                            var country_array = <?php echo json_encode($this->country_array); ?>;
                            var condition_array = <?php echo json_encode($this->condition_array); ?>;
                            var pluginID = <?php echo json_encode($this->id); ?>;
                            var lastID = 0;

                        <?php
                        $shipping_method_key = $this->option_key . '_' . $method_id;
                        if (isset($data['default'])) {
                            foreach ($data['default'] as $key => $value) {
                                global $row;
                                //add the key back into the json object
                                $value['key'] = $key;
                                $row = json_encode($value);
                                echo "jQuery('#{$this->id}_settings table tbody tr:last').before(create_zone_row({$row}));\n";
                            }
                        }
                        ?>





                            /**
                             * This creates a new ZONE row
                             */
                            function create_zone_row(row) {

                                //lets get the ID of the last one

                                var el = '#' + pluginID + '_settings .jem-zone-row';
                                lastID = jQuery(el).last().attr('id');

                                //Handle no rows
                                if (typeof lastID == 'undefined' || lastID == "") {
                                    lastID = 1;
                                } else {
                                    lastID = Number(lastID) + 1;
                                }

                                var html = '\
                                                        <tr style="display:none;" id="' + lastID + '" class="jem-zone-row" >\
                                                                <input type="hidden" value="' + lastID + '" name="key[' + lastID + ']"></input>\
                                                                <td><input type="hidden" size="30" value="zone-' + lastID + '"  name="zone-name[' + lastID + ']"/></td>\
                                                        </tr>\
                                        ';

                                //This is the expandable/collapsable row for that holds the rates
                                html += '\
                                                <tr class="jem-rate-holder">\
                                                        <td colspan="3">\
                                                                <table class="jem-rate-table shippingrows widefat" id="' + lastID + '_rates">\
                                                                        <thead>\
                                                                                <tr>\
                                                                                        <th></th>\
																						<th style="width: 25%">Condition</th>\
                                                                                        <th style="width: 25%">Min Value</th>\
                                                                                        <th style="width: 25%">Max Value</th>\
                                                                                        <th style="width: 25%">Shipping Rate</th>\
                                                                                </tr>\
                                                                        </thead>\
                                                                        ' + create_rate_row(lastID, row) + '\
                                                                        <tr>\
                                                                                <td colspan="5" class="add-rate-buttons">\
                                                                                        <a href="#" class="add button" name="key_' + lastID + '">Add New Rate</a>\
                                                                                        <a href="#" class="delete button">Delete Selected Rates</a>\
                                                                                </td>\
                                                                        </tr>\
                                                                </table>\
                                                        </td>\
                                                </tr>\
                                        ';

                                return html;
                            }

                            /**
                             * This creates a new RATE row
                             * The container Table is passed in and this row is added to it
                             */
                            function create_rate_row(lastID, row) {

                                if (row == null || row.rates.length == 0) {
                                    //lets manufacture a rows
                                    //create dummy row
                                    var row = {};
                                    row.key = "";
                                    row.condition = [""];
                                    // row.countries = [];
                                    row.rates = [];
                                    row.rates.push([]);
                                    row.rates[0].condition = "";
                                    row.rates[0].min = "";
                                    row.rates[0].max = "";
                                    row.rates[0].shipping = "";
                                }
                                //loop thru all the rate data and create rows

                                //handles if there are no rate rows yet
                                if (typeof (row.min) == 'undefined' || row.min == null) {
                                    row.min = [];
                                }

                                var html = '';
                                for (var i = 0; i < row.rates.length; i++) {
                                    html += '\
                                                        <tr class="rate-row">\
                                                                <td>\
                                                                        <input type="checkbox" class="jem-rate-checkbox" id="' + lastID + '"></input>\
                                                                </td>\
																<td>\
                                                                        <select class="'+ row.rates[i].condition +'" name="conditions[' + lastID + '][]">\
                                                                        ' + generate_condition_html(row.rates[i].condition) + '\
                                                                        </select>\
                                                                </td>\
                                                                <td>\
                                                                        <input type="text" size="20" placeholder="" name="min[' + lastID + '][]" value="' + row.rates[i].min + '"/>\
                                                                </td>\
                                                                <td>\
                                                                        <input type="text" size="20" placeholder="" name="max[' + lastID + '][]" value="' + row.rates[i].max + '"></input>\
                                                                </td>\
                                                                <td>\
                                                                        <input type="text" size="10" placeholder="" name="shipping[' + lastID + '][]" value="' + row.rates[i].shipping + '"></input>\
                                                                </td>\
                                                        </tr>\
                                                ';



                                }


                                return html;
                            }

                            /**
                             * Handles the expansion contraction of the rate table for the zone
                             */
                            function expand_contract() {

                                var row = jQuery(this).parent('td').parent('tr').next();

                                if (jQuery(row).hasClass('jem-hidden-row')) {
                                    jQuery(row).removeClass('jem-hidden-row').addClass('jem-show-row');
                                    jQuery(this).removeClass('expand-icon').addClass('collapse-icon');
                                } else {
                                    jQuery(row).removeClass('jem-show-row').addClass('jem-hidden-row');
                                    jQuery(this).removeClass('collapse-icon').addClass('expand-icon');
                                }



                            }


                            //TODO - these seem to be copies of the functions above - test commenting them out
                            //**************************************
                            // Generates the HTML for the country
                            // select. Uses an array of keys to
                            // determine which ones are selected
                            //**************************************
                            function generate_country_html(keys) {

                                html = "";

                                for (var key in country_array) {

                                    if (keys.indexOf(key) != -1) {
                                        //we have a match
                                        html += '<option value="' + key + '" selected="selected">' + country_array[key] + '</option>';
                                    } else {
                                        html += '<option value="' + key + '">' + country_array[key] + '</option>';

                                    }
                                }

                                return html;
                            }


                            //**************************************
                            // Generates the HTML for the CONDITION
                            // select. Uses an array of keys to
                            // determine which ones are selected
                            //**************************************
                            function generate_condition_html(keys) {

                                html = "";

                                for (var key in condition_array) {

                                    if (keys.indexOf(key) != -1) {
                                        //we have a match
                                        html += '<option value="' + key + '" selected="selected">' + condition_array[key] + '</option>';
                                    } else {
                                        html += '<option value="' + key + '">' + condition_array[key] + '</option>';

                                    }
                                }

                                return html;
                            }

                            //***************************
                            // Handle add/delete clicks
                            //***************************

                            //ZONE TABLE


                            /*
                             * add new ZONE row
                             */
                            var zoneID = "#" + pluginID + "_settings";

                            jQuery(zoneID).on('click', '.add-zone-buttons a.add', function () {

                                //ok lets add a row!


                                var id = "#" + pluginID + "_settings table tbody tr:last";
                                //create empty row
                                var row = {};
                                row.key = "";
                                row.min = [];
                                row.rates = [];
                                row.condition = [];
                                row.countries = [];
                                jQuery(id).before(create_zone_row(row));

                                //turn on select2 for our row
                                if (jQuery().chosen) {
                                    jQuery("select.chosen_select").chosen({
                                        width: '350px',
                                        disable_search_threshold: 5
                                    });
                                } else {
                                    jQuery("select.chosen_select").select2();
                                }


                                return false;
                            });

                            /**
                             * Delete ZONE row
                             */
                            jQuery(zoneID).on('click', '.add-zone-buttons a.delete', function () {

                                //loop thru and see what is checked - if it is zap it!
                                var rowsToDelete = jQuery(this).closest('table').find('.jem-zone-checkbox:checked');

                                jQuery.each(rowsToDelete, function () {

                                    var thisRow = jQuery(this).closest('tr');
                                    //first lets get the next sibl;ing to this row
                                    var nextRow = jQuery(thisRow).next();

                                    //it should be a rate row
                                    if (jQuery(nextRow).hasClass('jem-rate-holder')) {
                                        //remove it!
                                        jQuery(nextRow).remove();
                                    } else {
                                        //trouble at mill
                                        return;
                                    }

                                    jQuery(thisRow).remove();
                                });

                                //TODO - need to delete associated RATES

                                return false;
                            });


                            //RATE TABLES

                            /**
                             * ADD RATE BUTTON
                             */
                            jQuery(zoneID).on('click', '.add-rate-buttons a.add', function () {

                                //we need to get the key of this zone - it's in the name of of the button
                                var name = jQuery(this).attr('name');
                                name = name.substring(4);

                                //remove key_
                                //ok lets add a row!


                                var row = create_rate_row(name, null);
                                jQuery(this).closest('tr').before(row);

                                return false;
                            });

                            /**
                             * Delete RATE roe
                             */
                            jQuery(zoneID).on('click', '.add-rate-buttons a.delete', function () {

                                //loop thru and see what is checked - if it is zap it!
                                var rowsToDelete = jQuery(this).closest('table').find('.jem-rate-checkbox:checked');

                                jQuery.each(rowsToDelete, function () {
                                    jQuery(this).closest('tr').remove();
                                });


                                return false;
                            });

                            //These handle building the select arras


                        <?php
                        echo "jQuery('#{$this->id}_settings').on('click', '.jem-expansion', expand_contract) ;\n";
                        ?>
                        </script>
                        <?php
                    }
                    //NIPL

                    return ob_get_clean();
                }

                public function generate_shipping_list_html() {
                    ob_start();
                    ?>
                    </table>


                    <h3 class="add_shipping_method" id="shiping_methods_h3">List of shipping methods
                        <a href="<?php echo remove_query_arg('shipping_methods_id', add_query_arg('action', 'new')); ?>" class="child_shipping_method"><?php echo __('Add New', 'SES_DOMAIN'); ?></a>
                    </h3>
                    <table class="form-table">
                        <tr valign="top">
                            <td>
                                <table class="ses_samsa_express_shipping_methods_class widefat wc_shipping wp-list-table" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th class="sort" style="width: 1%;">&nbsp;</th>
                                            <th class="method_title" style="width: 30%;"><?php _e('Title', 'SES_DOMAIN'); ?></th>
                                            <th class="method_status" style="width: 1%;text-align: center;"><?php _e('Enabled', 'SES_DOMAIN'); ?></th>
                                            <th class="method_select" style="width: 0%;"><input type="checkbox" class="tips checkbox-select-all" data-tip="<?php _e('Select all', 'SES_DOMAIN'); ?> " class="checkall-checkbox-class" id="checkall-checkbox-id" /></th>
                                        </tr>
                                    </thead>
                                    <!--get option for saved methods details-->
                    <?php
                    $get_shipping_methods_options = get_option($this->ses_shipping_methods_option, array());
                    $get_shipping_method_order = get_option( $this->ses_shipping_method_order_option, array() );
                    $shipping_methods_options_array = array();
                    if (is_array($get_shipping_method_order)) {
                        foreach ($get_shipping_method_order as $method_id) {
                            if (isset($get_shipping_methods_options[$method_id])){
                                $shipping_methods_options_array[$method_id] = $get_shipping_methods_options[$method_id];
                            }
                        }
                    }
                    ?>
                    <!--display shipping method data-->
                                    <tbody>
                    <?php foreach ($shipping_methods_options_array as $shipping_method_options) {
                        ?>
                                            <tr id="shipping_method_id_<?php echo $shipping_method_options['method_id']; ?>" class="<?php //echo $tr_class; ?>">
                                                <td class="sort">
                                                    <input type="hidden" name="method_order[<?php echo esc_attr( $shipping_method_options['method_id'] ); ?>]" value="<?php echo esc_attr( $shipping_method_options['method_id'] ); ?>" />
                                                </td>
                                                <td class="method-title">
                                                    <a href="<?php echo remove_query_arg('shipping_methods_id', add_query_arg('method_id', $shipping_method_options['method_id'], add_query_arg('action', 'edit'))); ?>">
                                                        <strong><?php echo esc_html($shipping_method_options['method_title']); ?></strong>
                                                    </a>
                                                </td>
                                                <td class="method-status" style="width: 524px;display: -moz-stack;">
                        <?php if (isset($shipping_method_options['method_enabled']) && 'yes' === $shipping_method_options['method_enabled']) : ?>
                                                        <span class="status-enabled tips" data-tip="<?php _e('yes', SES_DOMAIN); ?>"><?php _e('yes', 'SES_DOMAIN'); ?></span>
                        <?php else : ?>
                                                        <span class="na">-</span>
                        <?php endif; ?>
                                                </td>
                                                <td class="method-select" style="width: 2% !important;text-align: center;" nowrap>
                                                    <input type="checkbox" class="tips checkbox-select chkItems" value="<?php echo esc_attr($shipping_method_options['method_id']); ?>" data-tip="<?php echo esc_html($shipping_method_options['method_title']); ?>" />
                                                </td>
                                            </tr>
                                                    <?php
                                                }
//                                                        }
                                                ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th>&nbsp;</th>
                                            <th colspan="8"><span class="description"><?php _e('Drag and drop the above shipment methods to control their display order. Confirm by clicking Save changes button below.', 'SES_DOMAIN'); ?></span></th>
                                        </tr>
                                        <tr>
                                            <th>&nbsp;</th>
                                            <th colspan="8">
                                                <button id="ses_samsa_express_remove_selected_method" class="button" disabled><?php _e('Remove selected Method', 'SES_DOMAIN'); ?></button>

                                                <div style="clear:both;"></div>
                                            </th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </td>
                        </tr>
                    </table>
                    <script type="text/javascript">
                        jQuery('.ses_samsa_express_shipping_methods_class input[type="checkbox"]').click(function () {
                            jQuery('#ses_samsa_express_remove_selected_method').attr('disabled', !jQuery('.ses_samsa_express_shipping_methods_class td input[type="checkbox"]').is(':checked'));
                        });

                        jQuery('#ses_samsa_express_remove_selected_method').click(function () {
                            var url = '<?php echo add_query_arg('shipping_methods_id', '', add_query_arg('action', 'delete')); ?>';
                            var first = true;
                            jQuery('input.checkbox-select').each(function () {
                                if (jQuery(this).is(':checked')) {
                                    if (!first) {
                                        url = url + ',';
                                    } else {
                                        url = url + '=';
                                    }
                                    url = url + jQuery(this).val();
                                    first = false;
                                }
                            })
                            if (first) {
                                alert('<?php _e('Please select shipping methods to remove', 'SES_DOMAIN'); ?>');
                                return false;
                            }
                            if (url != '<?php echo add_query_arg('method_id', '', add_query_arg('action', 'delete')); ?>') {
                                jQuery('#ses_samsa_express_remove_selected_method').prop('disabled', true);
                                jQuery('.woocommerce-save-button').prop('disabled', true);
                                window.location.href = url;
                            }
                            return false;
                        })
                    </script>
                    <?php
                    return ob_get_clean();
                }

                public function get_add_new_shipping_method_form($shipping_method_array) {
                    $this->form_fields = array(
                        'method_enabled' => array(
                            'title' => __('Enable/Disable', 'SES_DOMAIN'),
                            'type' => 'checkbox',
                            'label' => __('Enable this shipping method', 'SES_DOMAIN'),
                            'default' => $shipping_method_array['method_enabled']
                        ),
                        'method_title' => array(
                            'title' => __('Method Title', 'SES_DOMAIN'),
                            'description' => __('This controls the title which the user sees during checkout.', 'SES_DOMAIN'),
                            'type' => 'text',
                            'default' => $shipping_method_array['method_title'],
                            'desc_tip' => true
                        ),
                        /*'method_handling_fee' => array(
                            'title' => __('Handling Fee', 'SES_DOMAIN'),
                            'description' => __('Enter an amount for the handling fee - leave BLANK to disable.', 'SES_DOMAIN'),
                            'type' => 'hidden',
                            'default' => $shipping_method_array['method_handling_fee']
                        ),*/
                        'method_tax_status' => array(
                            'title' => __('Tax Status', 'SES_DOMAIN'),
                            'type' => 'select',
                            'default' => $shipping_method_array['method_tax_status'],
                            'options' => array(
                                'taxable' => __('Taxable', 'SES_DOMAIN'),
                                'notax' => __('Not Taxable', 'SES_DOMAIN'),
                            )
                        ),
                        'samsa_expresss_table' => array(
                            'title' => __('Shipping Methods', 'SES_DOMAIN'),
                            'type' => 'samsa_expresss_table',
                            'default' => isset($shipping_method_array['method_samsa_expresss']) ? $shipping_method_array['method_samsa_expresss'] : array(),
                            'description' => '',
                        )
                    );
                }

                /**
                 * Generates HTML for samsa_express settings table.
                 * this gets called automagically!
                 */
                function admin_options() {
                    ?>
                    <h2><?php _e('SMSA Express Shipping Options', 'woocommerce'); ?></h2>
                    <table class="form-table">
                    <?php
                    $shipping_method_action = false;
                    if (isset($_GET['action'])) {
                        $shipping_method_action = $_GET['action'];
                    }
                    if ($shipping_method_action == 'new' || $shipping_method_action == 'edit') {
                        $get_shipping_methods_options = get_option($this->ses_shipping_methods_option, array());

                        $shipping_method_array = array(
                            'method_title' => '',
                            'method_enabled' => 'no',
                            'method_handling_fee' => '',
                            'method_tax_status' => 'taxable',
                            'method_samsa_expresss' => ''
                        );
                        $method_id = '';
                        if ($shipping_method_action == 'edit') {
                            $method_id = $_GET['method_id'];
                            $shipping_method_array = $get_shipping_methods_options[$method_id];
                            $method_id_for_shipping = $this->id . '_' . $this->instance_id . '_' . sanitize_title($shipping_method_array['method_title']);
                            if (isset($shipping_method_array['method_id_for_shipping']) && $shipping_method_array['method_id_for_shipping'] != '') {
                                $method_id_for_shipping = $shipping_method_array['method_id_for_shipping'];
                            }
                            $method_id_for_shipping = $method_id_for_shipping;
                        } else {
                            $method_id_for_shipping = '';
                        }
                        ?>
                            <input type="hidden" name="shipping_method_action" value="<?php echo $shipping_method_action; ?>" />
                            <input type="hidden" name="shipping_method_id" value="<?php echo $method_id; ?>" />
                            <input type="hidden" name="method_id_for_shipping" value="<?php echo $method_id_for_shipping; ?>" />
                            <?php
                            $shipping_method['woocommerce_method_instance_id'] = $this->instance_id;
                            $this->generate_settings_html($this->get_add_new_shipping_method_form($shipping_method_array));
                        } else if ($shipping_method_action == 'delete') {
                            $selected_shipping_methods_id = '';
                            // get selected methods id and explode it with ','
                            if (isset($_GET['shipping_methods_id'])) {
                                $selected_shipping_methods_id = explode(',', $_GET['shipping_methods_id']);
                            }
                            // get all shipping methods options for delete
                            $get_shipping_methods_options_for_delete = get_option($this->ses_shipping_methods_option, array()); //
                            // get all shipping methods order for delete
                            $get_shipping_methods_order_for_delete = get_option( $this->shipping_method_order_option, array() );
                            foreach ($selected_shipping_methods_id as $removed_method_id) {
                                if (isset($get_shipping_methods_options_for_delete[$removed_method_id])) {
                                    if (isset($get_shipping_methods_order_for_delete[$removed_method_id])) {
                                        unset($get_shipping_methods_order_for_delete[$removed_method_id]);
                                    }
                                    $shipping_method = $get_shipping_methods_options_for_delete[$removed_method_id];
                                    unset($get_shipping_methods_options_for_delete[$removed_method_id]);
                                    // Update all shipping methods options after delete
                                    update_option($this->ses_shipping_methods_option, $get_shipping_methods_options_for_delete);
                                    // Update all shipping methods order after delete
                                    update_option( $this->shipping_method_order_option, $get_shipping_methods_order_for_delete );
                                }
                            }
                            $this->generate_settings_html();
                        } else {
                            $this->generate_settings_html();
                        }
                        ?>
                    </table>
                        <?php
                    }

                    /**
                     * Returns the latest counter
                     */
                    function get_counter() {
                        $this->counter = $this->counter + 1;
                        return $this->counter;
                    }

                    //*********************
                    // PHP functions
                    //***********************

                    function create_select_arrays() {

                        //first the CONDITION html
                        $this->condition_array = array();
                        $this->condition_array['weight'] = sprintf(__('Weight (%s)', 'MHTR_DOMAIN'), get_option('woocommerce_weight_unit'));
                        // $this->condition_array['total'] = sprintf(__('Total Price (%s)', 'MHTR_DOMAIN'), get_woocommerce_currency_symbol());


                        //Now the countries
                        $this->country_array = array();

                        // Get the country list from Woo....
                        foreach (WC()->countries->get_shipping_countries() as $id => $value) :
                            $this->country_array[esc_attr($id)] = esc_js($value);
                        endforeach;
                    }

                    //TODO - do we need this function?
                    /**
                     * This generates the select option HTML for teh zones & rates tables
                     */
                    function create_select_html() {
                        //first the CONDITION html
                        $arr = array();
                        $arr['weight'] = sprintf(__('Weight (%s)', 'MHTR_DOMAIN'), get_option('woocommerce_weight_unit'));
                        $arr['total'] = sprintf(__('Total Price (%s)', 'MHTR_DOMAIN'), get_woocommerce_currency_symbol());

                        //now create the html from the array
                        $html = '';
                        foreach ($arr as $key => $value) {
                            $html .= '<option value=">' . $key . '">' . $value . '</option>';
                        }

                        $this->condition_html = $html;

                        $html = '';
                        $arr = array();
                        //Now the countries
                        // Get the country list from Woo....
                        foreach (WC()->countries->get_shipping_countries() as $id => $value) :
                            $arr[esc_attr($id)] = esc_js($value);
                        endforeach;

                        //And create the HTML
                        foreach ($arr as $key => $value) {
                            $html .= '<option value=">' . $key . '">' . $value . '</option>';
                        }

                        $this->country_html = $html;
                    }

                    //Creates the HTML options for the selected

                    function create_dropdown_html($arr) {

                        $arr = array();



                        $this->condition_html = html;
                    }

                    /**
                     * Create dropdown options
                     */
                    function create_dropdown_options() {

                        $options = array();


                        // Get the country list from Woo....
                        foreach (WC()->countries->get_shipping_countries() as $id => $value) :
                            $options['country'][esc_attr($id)] = esc_js($value);
                        endforeach;

                        // Now the conditions - cater for language & woo
                        $option['condition']['weight'] = sprintf(__('Weight (%s)', 'SES_DOMAIN'), get_option('woocommerce_weight_unit'));
                        // $option['condition']['price'] = sprintf(__('Total (%s)', 'SES_DOMAIN'), get_woocommerce_currency_symbol());

                        return $options;
                    }

                    /**
                     * This saves all of our custom table settings
                     */
                    function process_admin_options() {

                        $shipping_method_action = false;
                        if (isset($_POST['shipping_method_action'])) {
                            $shipping_method_action = $_POST['shipping_method_action'];
                        }
                        if ($shipping_method_action == 'new' || $shipping_method_action == 'edit') {
                            //Arrays to hold the clean POST vars
                            $keys = array();
                            $zone_name = array();
                            $condition = array();
                            $countries = array();
                            $min = array();
                            $max = array();
                            $shipping = array();

                            //Take the POST vars, clean em up and put thme in nice arrays
                            if (isset($_POST['key']))
                                $keys = array_map('wc_clean', $_POST['key']);
                            if (isset($_POST['zone-name']))
                                $zone_name = array_map('wc_clean', $_POST['zone-name']);
                            // if (isset($_POST['condition']))
                                // $condition = array_map('wc_clean', $_POST['condition']);
                            //no wc_clean as multi-D arrays
                            if (isset($_POST['countries']))
                                $countries = $_POST['countries'];
                            if (isset($_POST['conditions']))
                                $conditions = $_POST['conditions'];
							if (isset($_POST['min']))
                                $min = $_POST['min'];
                            if (isset($_POST['max']))
                                $max = $_POST['max'];
                            if (isset($_POST['shipping']))
                                $shipping = $_POST['shipping'];

                            //todo - need to add soem validation here and some error messages???
                            //Master var of options - we keep it in one big bad boy
                            $options = array();

                            //OK we need to loop thru all of them - the keys will help us here - process by key
                            foreach ($keys as $key => $value) {

                                //we only process it if all the fields are set
                               /*  if (
                                        empty($zone_name[$key]) ||
                                        // empty($condition[$key]) ||
                                        empty($countries[$key])
                                ) {
                                    //something is empty so don't save it
                                    continue;
                                } */

                                //Get the zone name - this is our main key
                                $name = $zone_name[$key];

                                //Going to add the rates now.
                                //before we do that check if we have any empty rows and delete them
                                $obj = array();
								if( !empty($min) ){
									foreach ($min[$key] as $k => $val) {
										if (
												empty($conditions[$key][$k]) &&
												empty($min[$key][$k]) &&
												empty($max[$key][$k]) &&
												empty($shipping[$key][$k])
										) {
											unset($conditions[$key][$k]);
											unset($min[$key][$k]);
											unset($max[$key][$k]);
											unset($shipping[$key][$k]);
										} else {
											//add it to the object array
											$obj[] = array("condition" => $conditions[$key][$k] , "min" => $min[$key][$k], "max" => $max[$key][$k], "shipping" => $shipping[$key][$k]);
										}
									}
								}
                                //OK now lets sort or array of objects!!
                                usort($obj, 'self::cmp');

                                //create the array to hold the data
                                $options[$name] = array();
                                $options[$name]['method_handling_fee'] = (isset($_POST['woocommerce_' . $this->id . '_method_handling_fee'])) ? $_POST['woocommerce_' . $this->id . '_method_handling_fee'] : '' ;
                                // $options[$name]['condition'] = $condition[$key];
                                // $options[$name]['countries'] = $countries[$key];
                                $options[$name]['min'] = $min[$key];
                                $options[$name]['max'] = $max[$key];
                                $options[$name]['shipping'] = $shipping[$key];
                                $options[$name]['rates'] = $obj;   //This is the sorted rates object!
                            }
                            $get_shipping_methods_options = get_option($this->ses_shipping_methods_option, array());
                            $get_shipping_method_order = get_option( $this->ses_shipping_method_order_option, array() );
                            $shipping_method_array = array();
                            if ($shipping_method_action == 'new') {
                                $get_shipping_methods_options = get_option($this->ses_shipping_methods_option, array());
                                $method_id = get_option('ses_samsa_express_sub_shipping_method_id', 0);
                                foreach ($get_shipping_methods_options as $shipping_method_array) {
                                    if (intval($shipping_method_array['method_id']) > $method_id)
                                        $method_id = intval($shipping_method_array['method_id']);
                                }
                                $method_id++;
                                update_option('ses_samsa_express_sub_shipping_method_id', $method_id);
                                $method_id_for_shipping = $this->id . '_' . $this->instance_id . '_' . $method_id;
                            }
                            else {
                                $method_id = $_POST['shipping_method_id'];
                                $method_id_for_shipping = $_POST['method_id_for_shipping'];
                            }

                            $shipping_method_array['method_id'] = $method_id;
                            $shipping_method['method_id_for_shipping'] = $method_id_for_shipping;
                            if (isset($_POST['woocommerce_' . $this->id . '_method_enabled']) && $_POST['woocommerce_' . $this->id . '_method_enabled'] == 1) {
                                $shipping_method_array['method_enabled'] = 'yes';
                            } else {
                                $shipping_method_array['method_enabled'] = 'no';
                            }
                            $shipping_method_array['method_title'] = $_POST['woocommerce_' . $this->id . '_method_title'];
                            $shipping_method_array['method_handling_fee'] = (isset($_POST['woocommerce_' . $this->id . '_method_handling_fee'])) ? $_POST['woocommerce_' . $this->id . '_method_handling_fee'] : '' ;
                            $shipping_method_array['method_tax_status'] = $_POST['woocommerce_' . $this->id . '_method_tax_status'];

                            //SAVE IT
                            $shipping_method_array['method_samsa_expresss'] = $options;
                            $get_shipping_methods_options[$method_id] = $shipping_method_array;
                            update_option($this->ses_shipping_methods_option, $get_shipping_methods_options);
                            if (isset($_GET['action'])) {
                                $shipping_method_action = $_GET['action'];
                            }

                            if ($shipping_method_action == 'new') {
                                $get_shipping_method_order[$method_id] = $method_id;
                                update_option($this->ses_shipping_method_order_option, $get_shipping_method_order);
                                $redirect = add_query_arg(array('action' => 'edit', 'method_id' => $method_id));
                                if (1 == 1 && headers_sent()) {
                                    ?>
                                <script>
                                    parent.location.replace('<?php echo $redirect; ?>');
                                </script>
                                <?php
                            } else {
                                wp_safe_redirect($redirect);
                            }
                            exit;
                        }
                    }
                    else{
                        if (isset($_POST['method_order'])) {
                            update_option($this->ses_shipping_method_order_option, $_POST['method_order']);
                        }
                    }
                }

                //Comparision function for usort of associative arrays
                function cmp($a, $b) {
                    return $a['min'] - $b['min'];
                }

                /**
                 * This RETIEVES  all of our custom table settings

                 */
                function get_options() {

                    //Retrieve the zones & rates
                    $this->options = array_filter((array) get_option($this->option_key));

                    $x = 5;
                }

                /**
                 * calculate_shipping function. Woo calls this automagically
                 *
                 */
                public function calculate_shipping($package = Array()) {

                    $get_shipping_methods_options = get_option($this->ses_shipping_methods_option, array());
					$get_shipping_method_order = get_option( $this->ses_shipping_method_order_option, array() );
					$method_rate_id = $this->id.':'.$this->instance_id;
					$zone_id = $this->get_shipping_zone_from_method_rate_id( $method_rate_id );
					$delivery_zones = WC_Shipping_Zones::get_zones();
					$zone_countries = array();


					foreach ((array) $delivery_zones[$zone_id]['zone_locations'] as $zlocation ) {
						$zone_countries[] = $zlocation->code;
					}

                    $shipping_methods_options_array = array();

                    //TODO - need to work out what this array is holding??
                    if ( is_array( $get_shipping_method_order ) ) {
						foreach ( $get_shipping_method_order as $method_id ) {
							if ( isset( $get_shipping_methods_options[$method_id] ) ) $shipping_methods_options_array[$method_id] = $get_shipping_methods_options[$method_id];
						}
					}

                    //And what is this
                    foreach ($get_shipping_methods_options as $shipping_method) {
                        if (!isset($shipping_methods_options_array[$shipping_method['method_id']]))
                            $shipping_methods_options_array[$shipping_method['method_id']] = $shipping_method;
                    }

                    //TODO = can we check for this earlier rather than do a seperate loop???
                    // Remove samsa expresss if shipping method is disable
                    foreach ($shipping_methods_options_array as $key => $shipping_method) {
                        if (isset($shipping_method['method_enabled']) && 'yes' != $shipping_method['method_enabled'])
                            unset($shipping_methods_options_array[$key]);
                    }

                    $shipping_methods_options = $shipping_methods_options_array;

                    //@simon Nov 18 - can't see why this is here so getting rid of it'
                    //$loop_count = 0;
                    foreach ($shipping_methods_options as $shipping_method_option) {

                        $handling_charge = (isset($shipping_method_option['method_handling_fee'])) ? $shipping_method_option['method_handling_fee'] : 0 ;;

                        $handling_charge = (!empty($handling_charge) && is_numeric($handling_charge) && $handling_charge > 0)?$handling_charge:0;

                        foreach ($shipping_method_option['method_samsa_expresss'] as $method_rule) {

                            //SE - Added in to stop the error
                            $cost = 0;

                            //@simon Nov '18
                            //Need a field to show we have or have not found a match
                            //It's always showing up as zero
                            $found = false;


                            //what is the tax status
                            if ($shipping_method_option['method_tax_status'] == 'notax') {
                                $taxes = false;
                            } else {
                                $taxes = '';
                            }

                            //ok first lets get the country that this order is for
                            // check destination country is available in rule
                            $dest_country = $package['destination']['country'];
                            if (!in_array($dest_country, $zone_countries)) {
                                $found = false;
                            }

							// NISL custom code based on rates and conditions set for each row set.
								foreach( $method_rule['rates'] as $rates ){
									if( $rates['condition'] == 'total' ){

                                        //@simon Nov 18 - need to include taxes IF the they are included in the product
                                        //Cater for taxes
                                        $tax_display = get_option( 'woocommerce_tax_display_cart' );

                                        if( "incl" == $tax_display ){
                                            $total =  WC()->cart->get_cart_contents_total() + WC()->cart->get_cart_contents_tax();

                                        } else {
                                            $total =  WC()->cart->get_cart_contents_total() ;

                                        }
										//$costs = $this->find_matching_rate_custom(WC()->cart->cart_contents_total, $rates);
                                        $costs = $this->find_matching_rate_custom($total, $rates);

                                        //@simon Nov '18
                                        if($costs == null){
                                            continue;
                                        }

										$cost = $cost + $costs;
                                        $found = true;
									}
									else if($rates['condition'] == 'weight' ){
										$costs = $this->find_matching_rate_custom(WC()->cart->cart_contents_weight, $rates);


                                        //@simon Nov '18
                                        if($costs == null){
                                            continue;
                                        }

										$cost = $cost + $costs;
                                        $found = true;
									}
								}
							// END NISL custom code



                            $method_id = $this->id . '_' . $this->instance_id . '_' . sanitize_title($shipping_method_option['method_title']);
                            if (isset($shipping_method_option['method_id_for_shipping']) && $shipping_method_option['method_id_for_shipping'] != '') {
                                $method_id = $shipping_method_option['method_id_for_shipping'];
                            }

                            //$method_id = $method_id;

                            /*shipping handling fee: Add it to total Shipping cost*/

                            $cost =  $cost + $handling_charge;

                            //If it's free shipping append the Woo value)
                            if($cost === 0){
                                $shipping_method_option['method_title'] =$shipping_method_option['method_title'] . " (" . __("Free Shipping", 'woocommerce') . ")";
                            }

                            if ( $found ) {
                                    $rate = array(
                                        'id' => $method_id,
                                        'label' => $shipping_method_option['method_title'],
                                        'cost' => $cost,
                                        'taxes' => $taxes,
                                        'calc_tax' => 'per_order'
                                    );


                                // Register the rate
                                $this->add_rate($rate);

                            }


                            //$loop_count = $loop_count + 1;
                        }

                    }
                }

                function get_rates_for_country($country) {

//                                    //Loop thru and see if we can find one
                    $get_shipping_methods_options = get_option($this->ses_shipping_methods_option, array());

                    $shipping_methods_options_array = array();
                    foreach ($get_shipping_methods_options as $shipping_method) {
                        if (!isset($shipping_methods_options_array[$shipping_method['method_id']]))
                            $shipping_methods_options_array[$shipping_method['method_id']] = $shipping_method;
                    }
                    //                                pr($shipping_method);
                    // Remove samsa expresss if shipping method is disable
                    foreach ($shipping_methods_options_array as $key => $shipping_method) {
                        if (isset($shipping_method['method_enabled']) && 'yes' != $shipping_method['method_enabled'])
                            unset($shipping_methods_options_array[$key]);
                    }
                    $shipping_methods_options = $shipping_methods_options_array;
                    $ret = array();
//					$get_shipping_methods_options = get_option( $this->ses_shipping_methods_option, array() );
                    foreach ($shipping_methods_options as $shipping_methods_option) {

                        foreach ($shipping_methods_option['method_samsa_expresss'] as $rate) {
                            if (in_array($country, $rate['countries'])) {
                                $ret[] = $rate;
                            }
                        }
                    }

                    //if we found something return it, otherwise a null.
                    if (count($ret) > 0) {
                        return $ret;
                    } else {
                        return null;
                    }
                }

                //Here we find the matching rate
                function find_matching_rate($value, $zones) {
                    $zone = $zones;
                    foreach ($zone as $zones_array) {
                        //inside each zone will be the arrays of min max & shipping
                        //TODO - should probably make this a better data structure - array of objects, next version
//						pr($zone['max']);
                        // remember * means infinity!
                        for ($i = 0; $i < 1; $i++) {
                            if ($zone['max'][$i] == '*') {
                                if ($value >= $zone['min'][$i]) {
                                    $handling_fee = $zone['method_handling_fee'];
                                    $total_fee = $zone['shipping'][$i] + $handling_fee;
                                    return $total_fee;
                                }
                            } else {
                                if ($value >= $zone['min'][$i] && $value <= $zone['max'][$i]) {
                                    $handling_fee = $zone['method_handling_fee'];
                                    $total_fee = $zone['shipping'][$i] + $handling_fee;
                                    return $total_fee;
                                }
                            }
                        }

                        //OK if we got all the way to here, then we have NO match
                        return null;
                    }
                }

                //This finds which one of the rules matches the value
                //It uses an asterisk for infinite
				function find_matching_rate_custom($value, $rates) {
					$rate = $rates;
					if ($rate['max'] == '*') {
						if ($value >= $rate['min']) {
							$total_fee = $rate['shipping'];
                            //  $per_rate = $rate['shipping']/$rate['min'];
                            // $total_fee = $value*$per_rate;
							return $total_fee;
						}
					} else {
						if ($value >= $rate['min'] && $value <= $rate['max']) {
                             // $per_rate = $rate['shipping']/$rate['min'];
							// $total_fee = $value*$per_rate;
                            $total_fee = $rate['shipping'];

							return $total_fee;
						}
					}
					//OK if we got all the way to here, then we have NO match
					return null;
				}
				function get_shipping_zone_from_method_rate_id( $method_rate_id ){
					global $wpdb;

					$data = explode( ':', $method_rate_id );
					$method_id = $data[0];
					$instance_id = $data[1];

					// The first SQL query
					$zone_id = $wpdb->get_col( "
						SELECT wszm.zone_id
						FROM {$wpdb->prefix}woocommerce_shipping_zone_methods as wszm
						WHERE wszm.instance_id = '$instance_id'
						AND wszm.method_id LIKE '$method_id'
					" );
					$zone_id = reset($zone_id); // converting to string

					// 1. Wrong Shipping method rate id
					if( empty($zone_id) )
					{
						return __("Error! doesn't exist…");
					}
					// 2. Default WC Zone name
					elseif( $zone_id == 0 )
					{
						return __("All Other countries");
					}
					// 3. Created Zone name
					else
					{
						/* // The 2nd SQL query
						$zone_name = $wpdb->get_col( "
							SELECT wsz.zone_name
							FROM {$wpdb->prefix}woocommerce_shipping_zones as wsz
							WHERE wsz.zone_id = '$zone_id'
						" );
						return reset($zone_name); // converting to string and returning the value */
						return $zone_id;
					}
				}

            } // END of class definition

        } // END of if class exists
    }
    add_action('woocommerce_shipping_init', 'ses_samsa_express_init');

    function add_ses_samsa_express($methods) {
        $methods['ses_samsa_express'] = 'Ses_Samsa_Express_Shipping_Method';
        return $methods;
    }
    add_filter('woocommerce_shipping_methods', 'add_ses_samsa_express');





}

/**
 * Load admin scripts
 */
function ses_samsa_express_admin_scripts($hook) {
    global $wptr_settings_page, $post_type;

    // Use minified libraries if SCRIPT_DEBUG is turned off
    $suffix = ( defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ) ? '' : '.min';



    //Load the styles & scripts we need
    if ($hook == 'woocommerce_page_wc-settings') {

        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
    }
    wp_enqueue_style('ses-smsa-express-css', plugin_dir_url(__FILE__) . 'assets/css/custom.css');
}
add_action('admin_enqueue_scripts', 'ses_samsa_express_admin_scripts', 100);

function jem_smas_load_pr($pr_data) {
    ?>
    <script>
        jQuery(document).ready(function () {
            jQuery("#checkall-checkbox-id").change(function () {
                var checked = jQuery(this).is(':checked'); // Checkbox state
                // Select all
                if (checked) {
                    jQuery('.chkItems').each(function () {
                        jQuery(this).prop('checked', 'checked');
                        jQuery('#ses_samsa_express_remove_selected_method').prop('disabled', false);
                    });
                } else {
                    // Deselect All
                    jQuery('.chkItems').each(function () {
                        jQuery(this).prop('checked', false);
                        jQuery('#ses_samsa_express_remove_selected_method').prop('disabled', true);
                    });
                }

            });
        });

        jQuery(document).on("click",".wc-shipping-zone-method-settings",function(){
            var a_href = jQuery('.wc-shipping-zone-method-settings').attr('href');
            var templateUrl = '<?= admin_url(); ?>'+ a_href;
            jQuery(".wc-shipping-zone-method-settings").attr("target","_blank");
            alert(templateUrl);

        });
    </script><?php
    echo "<pre>";
    print_r($pr_data);
    echo "</pre>";
}
//add_action('admin_head', 'pr');
add_action('admin_footer', 'jem_smas_load_pr');



 /*create tables at the time of plugin installation*/
    function create_plugin_database_table()

    {
        global $table_prefix, $wpdb;

        $tblname = 'smsa';
        $tblname1 = 'smsa_city';
        $wp_samsa_table = $table_prefix . "$tblname";
        $wp_samsa_city_table = $table_prefix . "$tblname1";


        if($wpdb->get_var( "show tables like '$wp_samsa_table'" ) != $wp_samsa_table)
        {


            $sql = "CREATE TABLE `". $wp_samsa_table . "` ( ";

            $sql .= " `consignment_id` int(11) NOT NULL AUTO_INCREMENT, ";
            $sql .= " `order_id` int(11) NOT NULL, ";
            $sql .= " `awb_number` varchar(32) NOT NULL, ";
            $sql .= " `reference_number` varchar(32) NOT NULL, ";
            $sql .= " `pickup_date` datetime NOT NULL, ";
            $sql .= " `shipment_label` text, ";
            $sql .= " `status` varchar(32) NOT NULL, ";
            $sql .= " `date_added` datetime NOT NULL, ";
            $sql .= "  PRIMARY KEY (`consignment_id`) ";
            $sql .= ") ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ; ";
            require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
            dbDelta($sql);
        }


        if($wpdb->get_var( "show tables like '$wp_samsa_city_table'" ) != $wp_samsa_city_table)
        {
            $sql1 = "CREATE TABLE `". $wp_samsa_city_table . "` ( ";
            $sql1 .= "  `city_id`  int(11)   NOT NULL, ";
            $sql1 .= "  `language_id`  int(11)   NOT NULL, ";
            $sql1 .= " `name` varchar(32) NOT NULL ";
            $sql1 .= ") ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ; ";
            require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
            dbDelta($sql1);

        $wpdb->query($wpdb->prepare("INSERT INTO `". $wp_samsa_city_table . "` (`city_id`, `language_id`, `name`) VALUES (3, 1, 'Aqiq'),(3, 2, 'العقيق'),(4, 1, 'Atawlah'),(4, 2, 'الأطاولة '),(5, 1, 'Baha'),(5, 2, 'الباحة'),(6, 1, 'Baljurashi'),(6, 2, 'بلجرشي'),(7, 1, 'Mandaq'),(7, 2, 'المندق'),(8, 2, 'المظيلف'),(8, 1, 'Mudhaylif'),(9, 1, 'Mukhwah'),(9, 2, 'المخواة'),(10, 1, 'Qilwah'),(10, 2, 'قلوة'),(11, 1, 'Qunfudhah'),(11, 2, 'القنفذة'),(12, 2, 'الجوف'),(12, 1, 'Al Jouf'),(13, 1, 'Dawmat Al Jandal'),(13, 2, 'دومة الجندل'),(14, 1, 'Skakah'),(14, 2, 'سكاكا'),(15, 1, 'Bashayer'),(15, 2, 'البشائر'),(16, 1, 'Bellasmar'),(16, 2, 'بللسمر'),(17, 1, 'Namas'),(17, 2, 'النماص'),(18, 1, 'Sapt Al Alaya'),(18, 2, 'سبت العلايا'),(19, 1, 'Tanumah'),(19, 2, 'تنومة'),(20, 1, 'Ain Dar'),(20, 2, 'عين دار'),(21, 1, 'Anak'),(21, 2, 'عنك'),(22, 1, 'Buqaiq'),(22, 2, 'بقيق'),(23, 1, 'Dammam'),(23, 2, 'الدمام'),(24, 1, 'Dammam Airport'),(24, 2, 'مطار الدمام'),(25, 1, 'Dhahran'),(25, 2, 'الظهران'),(26, 1, 'Jubail'),(26, 2, 'الجبيل'),(27, 1, 'Khafji'),(27, 2, 'الخفجي'),(28, 1, 'Khubar'),(28, 2, 'الخبر'),(29, 1, 'Nairiyah'),(29, 2, 'النعيرية'),(30, 1, 'Qarya Al Uliya'),(30, 2, 'قرية العليا'),(31, 1, 'Qatif'),(31, 2, 'القطيف'),(32, 1, 'Rahima'),(32, 2, 'رحيمة'),(33, 1, 'Ras Tannurah'),(33, 2, 'رأس تنورة'),(34, 1, 'Safwa'),(34, 2, 'صفوى'),(35, 1, 'Saira'),(35, 2, 'سايرة'),(36, 1, 'Sayhat'),(36, 2, 'سيهات'),(37, 1, 'Shedgum'),(37, 2, 'شدقم'),(38, 1, 'Tanajib'),(38, 2, 'تناجيب'),(39, 2, 'تاروت (دارين)'),(39, 1, 'Tarut (Darin)'),(40, 1, 'Thqbah'),(40, 2, 'الثقبة'),(41, 1, 'Udhayliyah'),(41, 2, 'العضيلية '),(42, 1, 'Uthmaniyah'),(42, 2, 'العثمانية'),(43, 1, 'Najran'),(43, 2, 'نجران '),(44, 1, 'Sharourah'),(44, 2, 'شرورة'),(45, 1, 'Wadi Al-Dawasir'),(45, 2, 'وادي الدواسر'),(46, 1, 'Badaya '),(46, 2, 'البدائع'),(47, 1, 'Bukayriyah'),(47, 2, 'البكيرية '),(48, 1, 'Buraydah'),(48, 2, 'بريدة'),(49, 1, 'Dukhnah'),(49, 2, 'دخنة'),(50, 1, 'Khabra'),(50, 2, 'الخبراء'),(51, 1, 'Midhnab'),(51, 2, 'المذنب'),(52, 1, 'Nabhaniah'),(52, 2, 'النبهانية '),(53, 1, 'Qaseem Airport'),(53, 2, 'مطار القصيم '),(54, 1, 'Rafayaa Al Gimsh'),(54, 2, 'رفايع الجمش '),(55, 1, 'Rass'),(55, 2, 'الرس'),(56, 1, 'Riyadh Al Khabra'),(56, 2, 'رياض الخبراء '),(57, 1, 'Sajir'),(57, 2, 'ساجر'),(58, 1, 'Unayzah'),(58, 2, 'عنيزة'),(59, 1, 'Uqlat As Suqur'),(59, 2, 'عقلة الصقر'),(60, 1, 'Jeddah'),(60, 2, 'جدة'),(61, 1, 'Uyun Al Jiwa'),(61, 2, 'عيون الجواء '),(62, 1, 'Abu Arish'),(62, 2, 'أبو عريش'),(63, 1, 'Ahad Al Masarhah'),(63, 2, 'أحد المسارحة'),(64, 1, 'Al Dayer'),(64, 2, 'الدائر '),(65, 1, 'At Tuwal'),(65, 2, 'طوال'),(66, 1, 'Bani Malek '),(66, 2, 'بني مالك'),(67, 1, 'Baysh'),(67, 2, 'بيش'),(68, 1, 'Darb'),(68, 2, 'درب'),(69, 1, 'Dhamad'),(69, 2, 'ضمد'),(70, 1, 'Farasan'),(70, 2, 'فرسان'),(71, 1, 'Jazan'),(71, 2, 'جازان'),(72, 1, 'Sabya'),(72, 2, 'صبيا'),(73, 1, 'Samtah'),(73, 2, 'صامطة'),(74, 1, 'Shuqayq'),(74, 2, 'الشقيق'),(75, 2, 'حائل'),(75, 1, 'Hail'),(76, 1, 'Al Ruqi'),(76, 2, 'الرقعي'),(77, 1, 'Hafar Al Baten'),(77, 2, 'حفر الباطن'),(78, 1, 'King Khalid City'),(78, 2, 'مدينة الملك خالد العسكرية '),(79, 1, 'Qaysumah'),(79, 2, 'القيصومة'),(80, 1, 'Rafha'),(80, 2, 'رفحاء'),(81, 1, 'Sarrar'),(81, 2, 'السرار'),(82, 1, 'Al Ahsa'),(82, 2, 'الإحساء'),(83, 1, 'Al Ayun'),(83, 2, 'العيون'),(84, 1, 'Al Jafr'),(84, 2, 'الجفر'),(85, 1, 'Batha'),(85, 2, 'البطحاء'),(86, 1, 'Hufuf'),(86, 2, 'الهفوف'),(87, 1, 'Mubarraz'),(87, 2, 'المبرز'),(88, 1, 'Salwa'),(88, 2, 'سلوى'),(89, 1, 'Badr'),(89, 2, 'بدر'),(90, 1, 'Bahrah'),(90, 2, 'بحرة'),(91, 1, 'Jeddah'),(91, 2, 'جدة'),(92, 1, 'Jeddah Airport'),(92, 2, 'جدة مطار الملك عبد العزيز '),(93, 1, 'Kamil'),(93, 2, 'كامل'),(94, 1, 'Khulais'),(94, 2, 'خليص'),(95, 1, 'Lith'),(95, 2, 'الليث'),(96, 1, 'Masturah'),(96, 2, 'مستورة'),(97, 1, 'Rabigh'),(97, 2, 'رابغ'),(98, 1, 'Shaibah'),(98, 2, 'الشعيبة'),(99, 1, 'Thuwal'),(99, 2, 'ثول'),(100, 1, 'Abha'),(100, 2, 'أبها'),(101, 1, 'Ahad Rafidah'),(101, 2, 'أحد الرفيدة '),(102, 2, 'بارق'),(102, 1, 'Bariq'),(103, 1, 'Bishah'),(103, 2, 'بيشة'),(104, 1, 'Dhahran Al Janoub'),(104, 2, 'ظهران الجنوب'),(105, 1, 'Jash'),(105, 2, 'جاش'),(106, 1, 'Khamis Mushayt'),(106, 2, 'خميس مشيط'),(107, 1, 'Majardah'),(107, 2, 'مجاردة'),(108, 1, 'Muhayil '),(108, 2, 'محايل'),(109, 1, 'Nakeea'),(109, 2, 'النقيع '),(110, 1, 'Rijal Almaa'),(110, 2, 'رجال ألمع'),(111, 1, 'Sarat Abida'),(111, 2, 'سراة عبيدة '),(112, 1, 'Tarib'),(112, 2, 'طريب'),(113, 1, 'Tathlith'),(113, 2, 'تثليث'),(114, 1, 'Jamoum'),(114, 2, 'الجموم'),(115, 1, 'Makkah'),(115, 2, 'مكة المكرمة '),(116, 2, 'الطائف'),(116, 1, 'Taif'),(117, 1, 'Hanakiyah'),(117, 2, 'الحناكية '),(118, 1, 'Khayber'),(118, 2, 'خيبر'),(119, 1, 'Madinah'),(119, 2, 'المدينة المنورة'),(120, 1, 'Mahd Ad Dhahab'),(120, 2, 'مهد الذهب'),(121, 1, 'Ula'),(121, 2, 'العلا'),(122, 1, 'Afif'),(122, 2, 'عفيف'),(123, 1, 'Artawiyah'),(123, 2, 'الأرطاوية'),(124, 1, 'Bijadiyah'),(124, 2, 'البجادية'),(125, 1, 'Duwadimi'),(125, 2, 'الدوادمي'),(126, 1, 'Ghat'),(126, 2, 'الغاط'),(127, 1, 'Hawtat Sudayr '),(127, 2, 'حوطة سدير'),(128, 1, 'Majmaah'),(128, 2, 'المجمعة'),(129, 1, 'Shaqra'),(129, 2, 'شقراء'),(130, 1, 'Zulfi'),(130, 2, 'الزلفي '),(131, 1, 'Arar'),(131, 2, 'عرعر'),(132, 1, 'Jadidah Arar'),(132, 2, 'جديدة عرعر'),(133, 1, 'Al Aflaj (Layla)'),(133, 2, 'الأفلاج'),(134, 1, 'Dhurma'),(134, 2, 'ضرما'),(135, 1, 'Dilam'),(135, 2, 'الدلم'),(136, 1, 'Diriyah'),(136, 2, 'الدرعية'),(137, 1, 'Hawtat Bani Tamim'),(137, 2, 'حوطة بني تميم'),(138, 1, 'Hayer'),(138, 2, 'الحائر'),(139, 1, 'Huraymila'),(139, 2, 'حريملاء'),(140, 1, 'Kharj'),(140, 2, 'الخرج'),(141, 1, 'Muzahmiyah'),(141, 2, 'المزاحمية'),(142, 1, 'Quwayiyah'),(142, 2, 'القويعية'),(143, 1, 'Rayn'),(143, 2, 'الرين'),(144, 1, 'Riyadh'),(144, 2, 'الرياض'),(145, 1, 'Riyadh Airport'),(145, 2, 'مطار الملك خالد الرياض'),(146, 1, 'Rumah'),(146, 2, 'رماح'),(147, 1, 'Ruwaidah'),(147, 2, 'الرويضة'),(148, 1, 'Dhalim'),(148, 2, 'ظلم'),(149, 1, 'Khurmah'),(149, 2, 'الخرمة'),(150, 1, 'Muwayh'),(150, 2, 'المويه'),(151, 1, 'Ranyah'),(151, 2, 'رينة'),(152, 1, 'Sayl Al Kabir'),(152, 2, 'السيل الكبير '),(153, 1, 'Turbah'),(153, 2, 'تربة'),(154, 1, 'Turbah (Makkah)'),(154, 2, 'تربة مكة'),(155, 1, 'Dhuba'),(155, 2, 'ضبا'),(156, 1, 'Halit Ammar'),(156, 2, 'حالة عمار'),(157, 1, 'Haql'),(157, 2, 'حقل '),(158, 1, 'Tabuk'),(158, 2, 'تبوك'),(159, 1, 'Taima'),(159, 2, 'تيماء '),(160, 2, 'منفذ الحديثة'),(160, 1, 'Haditha'),(161, 2, 'القريات'),(161, 1, 'Qurayyat'),(162, 1, 'Tabarjal'),(162, 2, 'طبرجل'),(163, 1, 'Turayf'),(163, 2, 'طريف'),(164, 1, 'Khamasin'),(164, 2, 'الخماسين'),(165, 1, 'Sulayyil'),(165, 2, 'السليل'),(166, 1, 'Badar Hunain'),(166, 2, 'بدر حنين'),(167, 1, 'Ummlujj'),(167, 2, 'أملج'),(168, 1, 'Wajh'),(168, 2, 'الوجة'),(169, 1, 'Yanbu'),(169, 2, 'ينبع')"));


        }

        $directoryName = '../awb/';
        if(!is_dir($directoryName)){
            mkdir($directoryName, 0755, true);
        }


    }

    register_activation_hook( __FILE__, 'create_plugin_database_table' );

    /*delecte table at the time of plugin uninstalltaion*/
    function delete_samsa_tables() {

        global $table_prefix, $wpdb;

        $tblname = 'smsa';
        $tblname1 = 'smsa_city';
        $wp_samsa_table = $table_prefix . "$tblname";
        $wp_samsa_city_table = $table_prefix . "$tblname1";

        $sql = "DROP TABLE IF EXISTS ".$wp_samsa_table;
        $sql1 = "DROP TABLE IF EXISTS ".$wp_samsa_city_table;
        $wpdb->query($sql);
        $wpdb->query($sql1);
        delete_option("my_plugin_db_version");
    }
    register_deactivation_hook( __FILE__, 'delete_samsa_tables' );


    function plugin_add_settings_link( $links ) {
        $settings_link = '<a target="_blank" href="admin.php?page=wc-settings&tab=samsa_settings_tab">' . __( 'Settings' ) . '</a>';
        array_push( $links, $settings_link );
        return $links;
    }

    $plugin = plugin_basename( __FILE__ );
    add_filter( "plugin_action_links_$plugin", 'plugin_add_settings_link' );
