<?php
/**
 * Plugin Name: WooCommerce SMSA Express Shipping
 * Plugin URI: https://github.com/EssamSoft/SMSA-WooCommerce
 * Description: Integrates SMSA Express shipping services with WooCommerce, providing weight-based shipping rates, shipment creation, tracking, and label generation.
 * Version: 2.1.0
 * Author: Krishna Mishra
 * Author URI: http://www.jem-products.com
 * Requires at least: 4.0
 * Tested up to: 6.4
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 * Text Domain: smsa-woocommerce
 * Domain Path: /languages
 *
 * @package SMSA_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Define plugin constants
 */
define('SMSA_VERSION', '2.1.0');
define('SMSA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SMSA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SMSA_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main SMSA WooCommerce Plugin Class
 */
final class SMSA_WooCommerce {

    /**
     * Single instance of the class
     *
     * @var SMSA_WooCommerce
     */
    private static $instance = null;

    /**
     * Get single instance of SMSA_WooCommerce
     *
     * @return SMSA_WooCommerce
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files
     *
     * @return void
     */
    private function includes() {
        // Core includes
        require_once SMSA_PLUGIN_DIR . 'includes/class-smsa-api.php';
        require_once SMSA_PLUGIN_DIR . 'includes/class-smsa-model.php';
        require_once SMSA_PLUGIN_DIR . 'includes/class-smsa-tracking.php';

        // Admin includes
        require_once SMSA_PLUGIN_DIR . 'admin/class-smsa-settings.php';
        require_once SMSA_PLUGIN_DIR . 'admin/class-smsa-shipment.php';
    }

    /**
     * Initialize hooks
     *
     * @return void
     */
    private function init_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
     * Plugin activation
     *
     * @return void
     */
    public function activate() {
        SMSA_Model::create_tables();
    }

    /**
     * Plugin deactivation
     *
     * @return void
     */
    public function deactivate() {
        SMSA_Model::delete_tables();
    }

    /**
     * Initialize plugin when WordPress is ready
     *
     * @return void
     */
    public function init() {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Initialize settings
        SMSA_Settings::init();

        // Initialize shipment functionality
        SMSA_Shipment::init();

        // Initialize shipping method
        add_action('woocommerce_shipping_init', array($this, 'init_shipping_method'));
        add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));

        // Admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('admin_footer', array($this, 'admin_footer_scripts'));

        // Plugin settings link
        add_filter('plugin_action_links_' . SMSA_PLUGIN_BASENAME, array($this, 'plugin_action_links'));
    }

    /**
     * Check if WooCommerce is active
     *
     * @return bool
     */
    private function is_woocommerce_active() {
        return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
    }

    /**
     * Show notice if WooCommerce is not active
     *
     * @return void
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e('SMSA Express Shipping requires WooCommerce to be installed and active.', 'smsa-woocommerce'); ?></p>
        </div>
        <?php
    }

    /**
     * Initialize shipping method class
     *
     * @return void
     */
    public function init_shipping_method() {
        if (!class_exists('SMSA_Shipping_Method')) {
            require_once SMSA_PLUGIN_DIR . 'includes/class-smsa-shipping-method.php';
        }
    }

    /**
     * Add shipping method to WooCommerce
     *
     * @param array $methods Existing shipping methods
     * @return array Modified shipping methods
     */
    public function add_shipping_method($methods) {
        $methods['ses_samsa_express'] = 'SMSA_Shipping_Method';
        return $methods;
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page
     * @return void
     */
    public function admin_scripts($hook) {
        if ($hook == 'woocommerce_page_wc-settings') {
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-core');
        }
        wp_enqueue_style('smsa-admin-css', SMSA_PLUGIN_URL . 'assets/css/custom.css', array(), SMSA_VERSION);
    }

    /**
     * Admin footer scripts
     *
     * @return void
     */
    public function admin_footer_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#checkall-checkbox-id').change(function() {
                var checked = $(this).is(':checked');
                if (checked) {
                    $('.chkItems').each(function() {
                        $(this).prop('checked', 'checked');
                        $('#ses_samsa_express_remove_selected_method').prop('disabled', false);
                    });
                } else {
                    $('.chkItems').each(function() {
                        $(this).prop('checked', false);
                        $('#ses_samsa_express_remove_selected_method').prop('disabled', true);
                    });
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Add plugin action links
     *
     * @param array $links Existing links
     * @return array Modified links
     */
    public function plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=samsa_settings_tab') . '">' . __('Settings', 'smsa-woocommerce') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

/**
 * Initialize the plugin
 *
 * @return SMSA_WooCommerce
 */
function smsa_woocommerce() {
    return SMSA_WooCommerce::instance();
}

// Initialize plugin
smsa_woocommerce();

/**
 * Legacy function for backwards compatibility
 *
 * @param int $order_id Order ID
 * @return array Consignment data
 */
function getConsignment($order_id) {
    return SMSA_Model::get_consignment($order_id);
}

/**
 * Legacy function for backwards compatibility
 *
 * @param string $awb_number AWB number
 * @return void
 */
function get_shipment_status($awb_number) {
    SMSA_Tracking::display_shipment_status($awb_number);
}

/**
 * Legacy function for backwards compatibility
 *
 * @param string $soap_action SOAP action
 * @param string $method Method name
 * @param array $variables Variables
 * @return array XML data
 */
function createXml($soap_action, $method, $variables) {
    return SMSA_API::create_xml($soap_action, $method, $variables);
}

/**
 * Legacy function for backwards compatibility
 *
 * @param array $args Arguments
 * @return array Response
 */
function send($args = array()) {
    return SMSA_API::send($args);
}
