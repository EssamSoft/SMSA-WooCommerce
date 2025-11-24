<?php
/**
 * SMSA Express Shipping Method Class
 *
 * Extends WooCommerce shipping method to provide SMSA Express shipping
 *
 * @package SMSA_WooCommerce
 * @subpackage Includes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMSA_Shipping_Method
 *
 * WooCommerce shipping method for SMSA Express
 */
class SMSA_Shipping_Method extends WC_Shipping_Method {

    /**
     * Shipping method order option name
     *
     * @var string
     */
    private $ses_shipping_method_order_option;

    /**
     * Zones settings key
     *
     * @var string
     */
    private $zones_settings;

    /**
     * Rates settings key
     *
     * @var string
     */
    private $rates_settings;

    /**
     * Option key for saving
     *
     * @var string
     */
    private $option_key;

    /**
     * Shipping methods option name
     *
     * @var string
     */
    private $ses_shipping_methods_option;

    /**
     * Condition options array
     *
     * @var array
     */
    private $condition_array;

    /**
     * Saved options
     *
     * @var array
     */
    private $options;

    /**
     * Country options array
     *
     * @var array
     */
    private $country_array;

    /**
     * Counter for unique IDs
     *
     * @var int
     */
    private $counter;

    /**
     * Constructor
     *
     * @param int $instance_id Instance ID
     */
    public function __construct($instance_id = 0) {
        $this->instance_id = absint($instance_id);
        $this->id = 'ses_samsa_express';
        $this->method_title = __('SMSA Express', 'smsa-woocommerce');
        $this->method_description = __('SMSA Express lets you define shipping based on a table of values', 'smsa-woocommerce');
        $this->ses_shipping_method_order_option = 'ses_samsa_express_shipping_method_order_' . $this->instance_id;
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
        );
        $this->zones_settings = $this->id . 'zones_settings';
        $this->rates_settings = $this->id . 'rates_settings';
        $this->enabled = 'yes';
        $this->title = 'SMSA Express';
        $this->option_key = $this->id . '_samsa_expresss';
        $this->ses_shipping_methods_option = 'ses_samsa_express_shipping_methods_' . $this->instance_id;
        $this->options = array();
        $this->condition_array = array();
        $this->country_array = array();
        $this->counter = 0;

        $this->title = $this->get_option('title');
        $this->init();
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->get_options();
    }

    /**
     * Initialize settings
     *
     * @return void
     */
    public function init() {
        $this->instance_form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'smsa-woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('Enable this shipping method', 'smsa-woocommerce'),
                'default' => 'no'
            ),
            'title' => array(
                'title'       => __('Checkout Title', 'smsa-woocommerce'),
                'description' => __('This controls the title which the user sees during checkout.', 'smsa-woocommerce'),
                'type'        => 'text',
                'default'     => 'SMSA Express',
                'desc_tip'    => true
            ),
            'handling_fee' => array(
                'title'       => __('Handling Fee', 'smsa-woocommerce'),
                'description' => __('Enter an amount for the handling fee - leave BLANK to disable.', 'smsa-woocommerce'),
                'type'        => 'hidden',
                'default'     => ''
            ),
            'tax_status' => array(
                'title'   => __('Tax Status', 'smsa-woocommerce'),
                'type'    => 'select',
                'default' => 'taxable',
                'options' => array(
                    'taxable' => __('Taxable', 'smsa-woocommerce'),
                    'notax'   => __('Not Taxable', 'smsa-woocommerce'),
                )
            ),
            'shipping_list' => array(
                'type' => 'shipping_list'
            )
        );

        $this->init_form_fields();
        $this->init_settings();
        $this->create_select_arrays();

        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_custom_settings'));
    }

    /**
     * Initialize form fields
     *
     * @return void
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'shipping_list' => array(
                'title'       => __('Shipping Methods', 'smsa-woocommerce'),
                'type'        => 'shipping_list',
                'description' => '',
            )
        );
    }

    /**
     * Generate SMSA Express table HTML
     *
     * @param string $key Field key
     * @param array $data Field data
     * @return string HTML output
     */
    public function generate_samsa_expresss_table_html($key, $data) {
        ob_start();

        $get_action_name = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        ?>

        <script>
        jQuery(document).ready(function(){
            if (jQuery('.rate-row').length == 0) {
                var zoneID = "#" + pluginID + "_settings";
                var id = "#" + pluginID + "_settings table tbody tr:last";
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

        <tr>
            <th scope="row" class="titledesc"><?php _e('SMSA Express', 'smsa-woocommerce'); ?></th>
            <td id="<?php echo esc_attr($this->id); ?>_settings">
                <table class="shippingrows widefat">
                    <col style="width:0%">
                    <col style="width:0%">
                    <col style="width:0%">
                    <col style="width:100%;">
                    <tbody style="border: 1px solid black;">
                        <tr style="border: 1px solid black;"></tr>
                    </tbody>
                </table>
            </td>
        </tr>

        <?php
        if (isset($_GET['instance_id'])) {
            $zone = WC_Shipping_Zones::get_zone_by('instance_id', intval($_GET['instance_id']));
            $get_shipping_method_by_instance_id = WC_Shipping_Zones::get_shipping_method(intval($_GET['instance_id']));
            $link_content = '<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping') . '">' . __('Shipping Zones', 'woocommerce') . '</a> &gt ';
            $link_content .= '<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&zone_id=' . absint($zone->get_id())) . '">' . esc_html($zone->get_zone_name()) . '</a> &gt ';
            $link_content .= '<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&instance_id=' . intval($_GET['instance_id'])) . '">' . esc_html($get_shipping_method_by_instance_id->get_title()) . '</a>';

            if ($get_action_name == 'new') {
                $link_content .= ' &gt ' . __('Add New', 'smsa-woocommerce');
                $this->render_new_method_scripts($link_content);
            } else {
                $this->render_edit_method_scripts($link_content, $data);
            }
        }

        return ob_get_clean();
    }

    /**
     * Render scripts for new method
     *
     * @param string $link_content Breadcrumb HTML
     * @return void
     */
    private function render_new_method_scripts($link_content) {
        ?>
        <script>
            jQuery("#mainform h2").first().replaceWith('<h2>' + '<?php echo $link_content; ?>' + '</h2>');
            var options = <?php echo json_encode($this->create_dropdown_options()); ?>;
            var country_array = <?php echo json_encode($this->country_array); ?>;
            var condition_array = <?php echo json_encode($this->condition_array); ?>;
            var pluginID = <?php echo json_encode($this->id); ?>;
            var lastID = 0;

            <?php
            foreach ($this->options as $key => $value) {
                $value['key'] = $key;
                $row = json_encode($value);
                echo "jQuery('#{$this->id}_settings table tbody tr:last').before(create_zone_row({$row}));\n";
            }
            ?>

            <?php $this->render_zone_rate_scripts(); ?>
        </script>
        <?php
    }

    /**
     * Render scripts for edit method
     *
     * @param string $link_content Breadcrumb HTML
     * @param array $data Field data
     * @return void
     */
    private function render_edit_method_scripts($link_content, $data) {
        $method_id = isset($_GET['method_id']) ? sanitize_text_field($_GET['method_id']) : '';
        $get_shipping_methods_options = get_option($this->ses_shipping_methods_option, array());

        if (isset($get_shipping_methods_options[$method_id]['method_title']) && $get_shipping_methods_options[$method_id]['method_title'] != '') {
            $link_content .= ' &gt ' . esc_html($get_shipping_methods_options[$method_id]['method_title']);
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
            if (isset($data['default'])) {
                foreach ($data['default'] as $key => $value) {
                    $value['key'] = $key;
                    $row = json_encode($value);
                    echo "jQuery('#{$this->id}_settings table tbody tr:last').before(create_zone_row({$row}));\n";
                }
            }
            ?>

            <?php $this->render_zone_rate_scripts(); ?>
        </script>
        <?php
    }

    /**
     * Render zone and rate management scripts
     *
     * @return void
     */
    private function render_zone_rate_scripts() {
        ?>
        function create_zone_row(row) {
            var el = '#' + pluginID + '_settings .jem-zone-row';
            lastID = jQuery(el).last().attr('id');

            if (typeof lastID == 'undefined' || lastID == "") {
                lastID = 1;
            } else {
                lastID = Number(lastID) + 1;
            }

            var html = '<tr style="display:none;" id="' + lastID + '" class="jem-zone-row">' +
                '<input type="hidden" value="' + lastID + '" name="key[' + lastID + ']">' +
                '<td><input type="hidden" size="30" value="zone-' + lastID + '" name="zone-name[' + lastID + ']"/></td>' +
                '</tr>';

            html += '<tr class="jem-rate-holder"><td colspan="3">' +
                '<table class="jem-rate-table shippingrows widefat" id="' + lastID + '_rates">' +
                '<thead><tr><th></th><th style="width: 25%">Condition</th>' +
                '<th style="width: 25%">Min Value</th><th style="width: 25%">Max Value</th>' +
                '<th style="width: 25%">Shipping Rate</th></tr></thead>' +
                create_rate_row(lastID, row) +
                '<tr><td colspan="5" class="add-rate-buttons">' +
                '<a href="#" class="add button" name="key_' + lastID + '">Add New Rate</a>' +
                '<a href="#" class="delete button">Delete Selected Rates</a></td></tr>' +
                '</table></td></tr>';

            return html;
        }

        function create_rate_row(lastID, row) {
            if (row == null || row.rates.length == 0) {
                row = {};
                row.key = "";
                row.condition = [""];
                row.rates = [];
                row.rates.push([]);
                row.rates[0].condition = "";
                row.rates[0].min = "";
                row.rates[0].max = "";
                row.rates[0].shipping = "";
            }

            if (typeof(row.min) == 'undefined' || row.min == null) {
                row.min = [];
            }

            var html = '';
            for (var i = 0; i < row.rates.length; i++) {
                html += '<tr class="rate-row"><td>' +
                    '<input type="checkbox" class="jem-rate-checkbox" id="' + lastID + '">' +
                    '</td><td><select class="' + row.rates[i].condition + '" name="conditions[' + lastID + '][]">' +
                    generate_condition_html(row.rates[i].condition) + '</select></td>' +
                    '<td><input type="text" size="20" name="min[' + lastID + '][]" value="' + row.rates[i].min + '"/></td>' +
                    '<td><input type="text" size="20" name="max[' + lastID + '][]" value="' + row.rates[i].max + '"></td>' +
                    '<td><input type="text" size="10" name="shipping[' + lastID + '][]" value="' + row.rates[i].shipping + '"></td></tr>';
            }

            return html;
        }

        function generate_condition_html(keys) {
            var html = "";
            for (var key in condition_array) {
                if (keys && keys.indexOf(key) != -1) {
                    html += '<option value="' + key + '" selected="selected">' + condition_array[key] + '</option>';
                } else {
                    html += '<option value="' + key + '">' + condition_array[key] + '</option>';
                }
            }
            return html;
        }

        function generate_country_html(keys) {
            var html = "";
            for (var key in country_array) {
                if (keys && keys.indexOf(key) != -1) {
                    html += '<option value="' + key + '" selected="selected">' + country_array[key] + '</option>';
                } else {
                    html += '<option value="' + key + '">' + country_array[key] + '</option>';
                }
            }
            return html;
        }

        var zoneID = "#" + pluginID + "_settings";

        jQuery(zoneID).on('click', '.add-zone-buttons a.add', function() {
            var id = "#" + pluginID + "_settings table tbody tr:last";
            var row = {key: "", min: [], rates: [], condition: [], countries: []};
            jQuery(id).before(create_zone_row(row));

            if (jQuery().chosen) {
                jQuery("select.chosen_select").chosen({width: '350px', disable_search_threshold: 5});
            } else {
                jQuery("select.chosen_select").select2();
            }
            return false;
        });

        jQuery(zoneID).on('click', '.add-zone-buttons a.delete', function() {
            var rowsToDelete = jQuery(this).closest('table').find('.jem-zone-checkbox:checked');
            jQuery.each(rowsToDelete, function() {
                var thisRow = jQuery(this).closest('tr');
                var nextRow = jQuery(thisRow).next();
                if (jQuery(nextRow).hasClass('jem-rate-holder')) {
                    jQuery(nextRow).remove();
                }
                jQuery(thisRow).remove();
            });
            return false;
        });

        jQuery(zoneID).on('click', '.add-rate-buttons a.add', function() {
            var name = jQuery(this).attr('name').substring(4);
            var row = create_rate_row(name, null);
            jQuery(this).closest('tr').before(row);
            return false;
        });

        jQuery(zoneID).on('click', '.add-rate-buttons a.delete', function() {
            var rowsToDelete = jQuery(this).closest('table').find('.jem-rate-checkbox:checked');
            jQuery.each(rowsToDelete, function() {
                jQuery(this).closest('tr').remove();
            });
            return false;
        });
        <?php
    }

    /**
     * Generate shipping list HTML
     *
     * @return string HTML output
     */
    public function generate_shipping_list_html() {
        ob_start();
        ?>
        </table>

        <h3 class="add_shipping_method" id="shiping_methods_h3">
            <?php _e('List of shipping methods', 'smsa-woocommerce'); ?>
            <a href="<?php echo esc_url(remove_query_arg('shipping_methods_id', add_query_arg('action', 'new'))); ?>" class="child_shipping_method">
                <?php _e('Add New', 'smsa-woocommerce'); ?>
            </a>
        </h3>

        <table class="form-table">
            <tr valign="top">
                <td>
                    <table class="ses_samsa_express_shipping_methods_class widefat wc_shipping wp-list-table" cellspacing="0">
                        <thead>
                            <tr>
                                <th class="sort" style="width: 1%;">&nbsp;</th>
                                <th class="method_title" style="width: 30%;"><?php _e('Title', 'smsa-woocommerce'); ?></th>
                                <th class="method_status" style="width: 1%;text-align: center;"><?php _e('Enabled', 'smsa-woocommerce'); ?></th>
                                <th class="method_select" style="width: 0%;">
                                    <input type="checkbox" class="tips checkbox-select-all" data-tip="<?php _e('Select all', 'smsa-woocommerce'); ?>" id="checkall-checkbox-id" />
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $this->render_shipping_methods_list(); ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>&nbsp;</th>
                                <th colspan="8">
                                    <span class="description"><?php _e('Drag and drop the above shipment methods to control their display order. Confirm by clicking Save changes button below.', 'smsa-woocommerce'); ?></span>
                                </th>
                            </tr>
                            <tr>
                                <th>&nbsp;</th>
                                <th colspan="8">
                                    <button id="ses_samsa_express_remove_selected_method" class="button" disabled>
                                        <?php _e('Remove selected Method', 'smsa-woocommerce'); ?>
                                    </button>
                                    <div style="clear:both;"></div>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </td>
            </tr>
        </table>

        <?php $this->render_shipping_list_scripts(); ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Render shipping methods list
     *
     * @return void
     */
    private function render_shipping_methods_list() {
        $get_shipping_methods_options = get_option($this->ses_shipping_methods_option, array());
        $get_shipping_method_order = get_option($this->ses_shipping_method_order_option, array());
        $shipping_methods_options_array = array();

        if (is_array($get_shipping_method_order)) {
            foreach ($get_shipping_method_order as $method_id) {
                if (isset($get_shipping_methods_options[$method_id])) {
                    $shipping_methods_options_array[$method_id] = $get_shipping_methods_options[$method_id];
                }
            }
        }

        foreach ($shipping_methods_options_array as $shipping_method_options) :
            ?>
            <tr id="shipping_method_id_<?php echo esc_attr($shipping_method_options['method_id']); ?>">
                <td class="sort">
                    <input type="hidden" name="method_order[<?php echo esc_attr($shipping_method_options['method_id']); ?>]" value="<?php echo esc_attr($shipping_method_options['method_id']); ?>" />
                </td>
                <td class="method-title">
                    <a href="<?php echo esc_url(remove_query_arg('shipping_methods_id', add_query_arg('method_id', $shipping_method_options['method_id'], add_query_arg('action', 'edit')))); ?>">
                        <strong><?php echo esc_html($shipping_method_options['method_title']); ?></strong>
                    </a>
                </td>
                <td class="method-status" style="width: 524px;display: -moz-stack;">
                    <?php if (isset($shipping_method_options['method_enabled']) && 'yes' === $shipping_method_options['method_enabled']) : ?>
                        <span class="status-enabled tips" data-tip="<?php esc_attr_e('yes', 'smsa-woocommerce'); ?>"><?php _e('yes', 'smsa-woocommerce'); ?></span>
                    <?php else : ?>
                        <span class="na">-</span>
                    <?php endif; ?>
                </td>
                <td class="method-select" style="width: 2% !important;text-align: center;" nowrap>
                    <input type="checkbox" class="tips checkbox-select chkItems" value="<?php echo esc_attr($shipping_method_options['method_id']); ?>" data-tip="<?php echo esc_attr($shipping_method_options['method_title']); ?>" />
                </td>
            </tr>
        <?php
        endforeach;
    }

    /**
     * Render shipping list scripts
     *
     * @return void
     */
    private function render_shipping_list_scripts() {
        ?>
        <script type="text/javascript">
        jQuery('.ses_samsa_express_shipping_methods_class input[type="checkbox"]').click(function() {
            jQuery('#ses_samsa_express_remove_selected_method').attr('disabled', !jQuery('.ses_samsa_express_shipping_methods_class td input[type="checkbox"]').is(':checked'));
        });

        jQuery('#ses_samsa_express_remove_selected_method').click(function() {
            var url = '<?php echo add_query_arg('shipping_methods_id', '', add_query_arg('action', 'delete')); ?>';
            var first = true;
            jQuery('input.checkbox-select').each(function() {
                if (jQuery(this).is(':checked')) {
                    url = url + (first ? '=' : ',') + jQuery(this).val();
                    first = false;
                }
            });
            if (first) {
                alert('<?php _e('Please select shipping methods to remove', 'smsa-woocommerce'); ?>');
                return false;
            }
            jQuery('#ses_samsa_express_remove_selected_method').prop('disabled', true);
            jQuery('.woocommerce-save-button').prop('disabled', true);
            window.location.href = url;
            return false;
        });
        </script>
        <?php
    }

    /**
     * Get add new shipping method form
     *
     * @param array $shipping_method_array Method data
     * @return void
     */
    public function get_add_new_shipping_method_form($shipping_method_array) {
        $this->form_fields = array(
            'method_enabled' => array(
                'title'   => __('Enable/Disable', 'smsa-woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('Enable this shipping method', 'smsa-woocommerce'),
                'default' => $shipping_method_array['method_enabled']
            ),
            'method_title' => array(
                'title'       => __('Method Title', 'smsa-woocommerce'),
                'description' => __('This controls the title which the user sees during checkout.', 'smsa-woocommerce'),
                'type'        => 'text',
                'default'     => $shipping_method_array['method_title'],
                'desc_tip'    => true
            ),
            'method_tax_status' => array(
                'title'   => __('Tax Status', 'smsa-woocommerce'),
                'type'    => 'select',
                'default' => $shipping_method_array['method_tax_status'],
                'options' => array(
                    'taxable' => __('Taxable', 'smsa-woocommerce'),
                    'notax'   => __('Not Taxable', 'smsa-woocommerce'),
                )
            ),
            'samsa_expresss_table' => array(
                'title'       => __('Shipping Methods', 'smsa-woocommerce'),
                'type'        => 'samsa_expresss_table',
                'default'     => isset($shipping_method_array['method_samsa_expresss']) ? $shipping_method_array['method_samsa_expresss'] : array(),
                'description' => '',
            )
        );
    }

    /**
     * Admin options HTML
     *
     * @return void
     */
    public function admin_options() {
        ?>
        <h2><?php _e('SMSA Express Shipping Options', 'smsa-woocommerce'); ?></h2>
        <table class="form-table">
        <?php
        $shipping_method_action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : false;

        if ($shipping_method_action == 'new' || $shipping_method_action == 'edit') {
            $get_shipping_methods_options = get_option($this->ses_shipping_methods_option, array());

            $shipping_method_array = array(
                'method_title'         => '',
                'method_enabled'       => 'no',
                'method_handling_fee'  => '',
                'method_tax_status'    => 'taxable',
                'method_samsa_expresss' => ''
            );

            $method_id = '';
            $method_id_for_shipping = '';

            if ($shipping_method_action == 'edit') {
                $method_id = sanitize_text_field($_GET['method_id']);
                $shipping_method_array = $get_shipping_methods_options[$method_id];
                $method_id_for_shipping = $this->id . '_' . $this->instance_id . '_' . sanitize_title($shipping_method_array['method_title']);
                if (isset($shipping_method_array['method_id_for_shipping']) && $shipping_method_array['method_id_for_shipping'] != '') {
                    $method_id_for_shipping = $shipping_method_array['method_id_for_shipping'];
                }
            }
            ?>
            <input type="hidden" name="shipping_method_action" value="<?php echo esc_attr($shipping_method_action); ?>" />
            <input type="hidden" name="shipping_method_id" value="<?php echo esc_attr($method_id); ?>" />
            <input type="hidden" name="method_id_for_shipping" value="<?php echo esc_attr($method_id_for_shipping); ?>" />
            <?php
            $this->generate_settings_html($this->get_add_new_shipping_method_form($shipping_method_array));
        } elseif ($shipping_method_action == 'delete') {
            $this->handle_delete_action();
            $this->generate_settings_html();
        } else {
            $this->generate_settings_html();
        }
        ?>
        </table>
        <?php
    }

    /**
     * Handle delete action
     *
     * @return void
     */
    private function handle_delete_action() {
        $selected_shipping_methods_id = isset($_GET['shipping_methods_id']) ? explode(',', sanitize_text_field($_GET['shipping_methods_id'])) : array();
        $get_shipping_methods_options_for_delete = get_option($this->ses_shipping_methods_option, array());
        $get_shipping_methods_order_for_delete = get_option($this->ses_shipping_method_order_option, array());

        foreach ($selected_shipping_methods_id as $removed_method_id) {
            if (isset($get_shipping_methods_options_for_delete[$removed_method_id])) {
                if (isset($get_shipping_methods_order_for_delete[$removed_method_id])) {
                    unset($get_shipping_methods_order_for_delete[$removed_method_id]);
                }
                unset($get_shipping_methods_options_for_delete[$removed_method_id]);
                update_option($this->ses_shipping_methods_option, $get_shipping_methods_options_for_delete);
                update_option($this->ses_shipping_method_order_option, $get_shipping_methods_order_for_delete);
            }
        }
    }

    /**
     * Get counter value
     *
     * @return int Counter value
     */
    public function get_counter() {
        $this->counter = $this->counter + 1;
        return $this->counter;
    }

    /**
     * Create select arrays for conditions and countries
     *
     * @return void
     */
    public function create_select_arrays() {
        $this->condition_array = array();
        $this->condition_array['weight'] = sprintf(__('Weight (%s)', 'smsa-woocommerce'), get_option('woocommerce_weight_unit'));

        $this->country_array = array();
        foreach (WC()->countries->get_shipping_countries() as $id => $value) {
            $this->country_array[esc_attr($id)] = esc_js($value);
        }
    }

    /**
     * Create dropdown options
     *
     * @return array Options
     */
    public function create_dropdown_options() {
        $options = array();

        foreach (WC()->countries->get_shipping_countries() as $id => $value) {
            $options['country'][esc_attr($id)] = esc_js($value);
        }

        $options['condition']['weight'] = sprintf(__('Weight (%s)', 'smsa-woocommerce'), get_option('woocommerce_weight_unit'));

        return $options;
    }

    /**
     * Process admin options
     *
     * @return void
     */
    public function process_admin_options() {
        $shipping_method_action = isset($_POST['shipping_method_action']) ? sanitize_text_field($_POST['shipping_method_action']) : false;

        if ($shipping_method_action == 'new' || $shipping_method_action == 'edit') {
            $this->process_save_method();
        } else {
            if (isset($_POST['method_order'])) {
                update_option($this->ses_shipping_method_order_option, array_map('sanitize_text_field', $_POST['method_order']));
            }
        }
    }

    /**
     * Process save method
     *
     * @return void
     */
    private function process_save_method() {
        $keys = isset($_POST['key']) ? array_map('wc_clean', $_POST['key']) : array();
        $zone_name = isset($_POST['zone-name']) ? array_map('wc_clean', $_POST['zone-name']) : array();
        $conditions = isset($_POST['conditions']) ? $_POST['conditions'] : array();
        $min = isset($_POST['min']) ? $_POST['min'] : array();
        $max = isset($_POST['max']) ? $_POST['max'] : array();
        $shipping = isset($_POST['shipping']) ? $_POST['shipping'] : array();

        $options = array();

        foreach ($keys as $key => $value) {
            $name = $zone_name[$key];
            $obj = array();

            if (!empty($min) && isset($min[$key])) {
                foreach ($min[$key] as $k => $val) {
                    if (empty($conditions[$key][$k]) && empty($min[$key][$k]) && empty($max[$key][$k]) && empty($shipping[$key][$k])) {
                        continue;
                    }
                    $obj[] = array(
                        'condition' => sanitize_text_field($conditions[$key][$k]),
                        'min'       => sanitize_text_field($min[$key][$k]),
                        'max'       => sanitize_text_field($max[$key][$k]),
                        'shipping'  => sanitize_text_field($shipping[$key][$k])
                    );
                }
            }

            usort($obj, array($this, 'cmp'));

            $options[$name] = array();
            $options[$name]['method_handling_fee'] = isset($_POST['woocommerce_' . $this->id . '_method_handling_fee']) ? sanitize_text_field($_POST['woocommerce_' . $this->id . '_method_handling_fee']) : '';
            $options[$name]['min'] = isset($min[$key]) ? array_map('sanitize_text_field', $min[$key]) : array();
            $options[$name]['max'] = isset($max[$key]) ? array_map('sanitize_text_field', $max[$key]) : array();
            $options[$name]['shipping'] = isset($shipping[$key]) ? array_map('sanitize_text_field', $shipping[$key]) : array();
            $options[$name]['rates'] = $obj;
        }

        $this->save_shipping_method($options);
    }

    /**
     * Save shipping method
     *
     * @param array $options Options to save
     * @return void
     */
    private function save_shipping_method($options) {
        $get_shipping_methods_options = get_option($this->ses_shipping_methods_option, array());
        $get_shipping_method_order = get_option($this->ses_shipping_method_order_option, array());
        $shipping_method_action = isset($_POST['shipping_method_action']) ? sanitize_text_field($_POST['shipping_method_action']) : '';

        if ($shipping_method_action == 'new') {
            $method_id = get_option('ses_samsa_express_sub_shipping_method_id', 0);
            foreach ($get_shipping_methods_options as $shipping_method_array) {
                if (intval($shipping_method_array['method_id']) > $method_id) {
                    $method_id = intval($shipping_method_array['method_id']);
                }
            }
            $method_id++;
            update_option('ses_samsa_express_sub_shipping_method_id', $method_id);
            $method_id_for_shipping = $this->id . '_' . $this->instance_id . '_' . $method_id;
        } else {
            $method_id = sanitize_text_field($_POST['shipping_method_id']);
            $method_id_for_shipping = sanitize_text_field($_POST['method_id_for_shipping']);
        }

        $shipping_method_array = array();
        $shipping_method_array['method_id'] = $method_id;
        $shipping_method_array['method_id_for_shipping'] = $method_id_for_shipping;
        $shipping_method_array['method_enabled'] = isset($_POST['woocommerce_' . $this->id . '_method_enabled']) ? 'yes' : 'no';
        $shipping_method_array['method_title'] = sanitize_text_field($_POST['woocommerce_' . $this->id . '_method_title']);
        $shipping_method_array['method_handling_fee'] = isset($_POST['woocommerce_' . $this->id . '_method_handling_fee']) ? sanitize_text_field($_POST['woocommerce_' . $this->id . '_method_handling_fee']) : '';
        $shipping_method_array['method_tax_status'] = sanitize_text_field($_POST['woocommerce_' . $this->id . '_method_tax_status']);
        $shipping_method_array['method_samsa_expresss'] = $options;

        $get_shipping_methods_options[$method_id] = $shipping_method_array;
        update_option($this->ses_shipping_methods_option, $get_shipping_methods_options);

        $shipping_method_action_get = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';

        if ($shipping_method_action_get == 'new') {
            $get_shipping_method_order[$method_id] = $method_id;
            update_option($this->ses_shipping_method_order_option, $get_shipping_method_order);
            $redirect = add_query_arg(array('action' => 'edit', 'method_id' => $method_id));

            if (headers_sent()) {
                ?>
                <script>parent.location.replace('<?php echo esc_url($redirect); ?>');</script>
                <?php
            } else {
                wp_safe_redirect($redirect);
            }
            exit;
        }
    }

    /**
     * Comparison function for usort
     *
     * @param array $a First element
     * @param array $b Second element
     * @return int Comparison result
     */
    public function cmp($a, $b) {
        return $a['min'] - $b['min'];
    }

    /**
     * Get saved options
     *
     * @return void
     */
    public function get_options() {
        $this->options = array_filter((array) get_option($this->option_key));
    }

    /**
     * Calculate shipping rates
     *
     * @param array $package Shipping package
     * @return void
     */
    public function calculate_shipping($package = array()) {
        $get_shipping_methods_options = get_option($this->ses_shipping_methods_option, array());
        $get_shipping_method_order = get_option($this->ses_shipping_method_order_option, array());
        $method_rate_id = $this->id . ':' . $this->instance_id;
        $zone_id = $this->get_shipping_zone_from_method_rate_id($method_rate_id);
        $delivery_zones = WC_Shipping_Zones::get_zones();
        $zone_countries = array();

        if (isset($delivery_zones[$zone_id]['zone_locations'])) {
            foreach ((array) $delivery_zones[$zone_id]['zone_locations'] as $zlocation) {
                $zone_countries[] = $zlocation->code;
            }
        }

        $shipping_methods_options_array = array();

        if (is_array($get_shipping_method_order)) {
            foreach ($get_shipping_method_order as $method_id) {
                if (isset($get_shipping_methods_options[$method_id])) {
                    $shipping_methods_options_array[$method_id] = $get_shipping_methods_options[$method_id];
                }
            }
        }

        foreach ($get_shipping_methods_options as $shipping_method) {
            if (!isset($shipping_methods_options_array[$shipping_method['method_id']])) {
                $shipping_methods_options_array[$shipping_method['method_id']] = $shipping_method;
            }
        }

        foreach ($shipping_methods_options_array as $key => $shipping_method) {
            if (isset($shipping_method['method_enabled']) && 'yes' != $shipping_method['method_enabled']) {
                unset($shipping_methods_options_array[$key]);
            }
        }

        foreach ($shipping_methods_options_array as $shipping_method_option) {
            $handling_charge = isset($shipping_method_option['method_handling_fee']) ? $shipping_method_option['method_handling_fee'] : 0;
            $handling_charge = (!empty($handling_charge) && is_numeric($handling_charge) && $handling_charge > 0) ? $handling_charge : 0;

            foreach ($shipping_method_option['method_samsa_expresss'] as $method_rule) {
                $cost = 0;
                $found = false;

                $taxes = ($shipping_method_option['method_tax_status'] == 'notax') ? false : '';

                $dest_country = $package['destination']['country'];
                if (!in_array($dest_country, $zone_countries)) {
                    $found = false;
                }

                foreach ($method_rule['rates'] as $rates) {
                    if ($rates['condition'] == 'total') {
                        $tax_display = get_option('woocommerce_tax_display_cart');
                        $total = ($tax_display == 'incl')
                            ? WC()->cart->get_cart_contents_total() + WC()->cart->get_cart_contents_tax()
                            : WC()->cart->get_cart_contents_total();

                        $costs = $this->find_matching_rate_custom($total, $rates);
                        if ($costs !== null) {
                            $cost = $cost + $costs;
                            $found = true;
                        }
                    } elseif ($rates['condition'] == 'weight') {
                        $costs = $this->find_matching_rate_custom(WC()->cart->cart_contents_weight, $rates);
                        if ($costs !== null) {
                            $cost = $cost + $costs;
                            $found = true;
                        }
                    }
                }

                $method_id = $this->id . '_' . $this->instance_id . '_' . sanitize_title($shipping_method_option['method_title']);
                if (isset($shipping_method_option['method_id_for_shipping']) && $shipping_method_option['method_id_for_shipping'] != '') {
                    $method_id = $shipping_method_option['method_id_for_shipping'];
                }

                $cost = $cost + $handling_charge;

                $label = $shipping_method_option['method_title'];
                if ($cost === 0) {
                    $label .= ' (' . __('Free Shipping', 'woocommerce') . ')';
                }

                if ($found) {
                    $rate = array(
                        'id'       => $method_id,
                        'label'    => $label,
                        'cost'     => $cost,
                        'taxes'    => $taxes,
                        'calc_tax' => 'per_order'
                    );

                    $this->add_rate($rate);
                }
            }
        }
    }

    /**
     * Find matching rate for custom value
     *
     * @param float $value Value to match
     * @param array $rates Rate data
     * @return float|null Shipping cost or null
     */
    public function find_matching_rate_custom($value, $rates) {
        $rate = $rates;
        if ($rate['max'] == '*') {
            if ($value >= $rate['min']) {
                return $rate['shipping'];
            }
        } else {
            if ($value >= $rate['min'] && $value <= $rate['max']) {
                return $rate['shipping'];
            }
        }
        return null;
    }

    /**
     * Get shipping zone from method rate ID
     *
     * @param string $method_rate_id Method rate ID
     * @return int|string Zone ID or error message
     */
    public function get_shipping_zone_from_method_rate_id($method_rate_id) {
        global $wpdb;

        $data = explode(':', $method_rate_id);
        $method_id = $data[0];
        $instance_id = $data[1];

        $zone_id = $wpdb->get_col($wpdb->prepare(
            "SELECT wszm.zone_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods as wszm WHERE wszm.instance_id = %d AND wszm.method_id LIKE %s",
            $instance_id,
            $method_id
        ));
        $zone_id = reset($zone_id);

        if (empty($zone_id)) {
            return __("Error! doesn't exist...", 'smsa-woocommerce');
        } elseif ($zone_id == 0) {
            return __('All Other countries', 'smsa-woocommerce');
        }

        return $zone_id;
    }
}
