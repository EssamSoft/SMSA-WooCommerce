<?php
/**
 * SMSA Settings Tab Class
 *
 * Handles WooCommerce admin settings for SMSA plugin
 *
 * @package SMSA_WooCommerce
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMSA_Settings
 *
 * Creates and manages the SMSA settings tab in WooCommerce admin
 */
class SMSA_Settings {

    /**
     * Initialize the settings class
     *
     * @return void
     */
    public static function init() {
        add_filter('woocommerce_settings_tabs_array', array(__CLASS__, 'add_settings_tab'), 50);
        add_action('woocommerce_settings_tabs_samsa_settings_tab', array(__CLASS__, 'settings_tab'));
        add_action('woocommerce_update_options_samsa_settings_tab', array(__CLASS__, 'update_settings'));
    }

    /**
     * Add SMSA settings tab to WooCommerce settings
     *
     * @param array $settings_tabs Existing settings tabs
     * @return array Modified settings tabs
     */
    public static function add_settings_tab($settings_tabs) {
        $settings_tabs['samsa_settings_tab'] = __('SMSA Settings', 'smsa-woocommerce');
        return $settings_tabs;
    }

    /**
     * Display the settings tab content
     *
     * @return void
     */
    public static function settings_tab() {
        woocommerce_admin_fields(self::get_settings());
    }

    /**
     * Save settings when form is submitted
     *
     * @return void
     */
    public static function update_settings() {
        woocommerce_update_options(self::get_settings());
    }

    /**
     * Get all settings fields for the SMSA tab
     *
     * @return array Settings fields configuration
     */
    public static function get_settings() {
        $settings = array(
            'section_title' => array(
                'name' => __('SMSA Express API Settings', 'smsa-woocommerce'),
                'type' => 'title',
                'desc' => __('Configure your SMSA Express API credentials and store information.', 'smsa-woocommerce'),
                'id'   => 'wc_samsa_settings_tab_section_title'
            ),
            'status' => array(
                'name'    => __('Status', 'smsa-woocommerce'),
                'type'    => 'select',
                'desc'    => __('Enable or disable SMSA Express integration.', 'smsa-woocommerce'),
                'options' => array(
                    'option1' => __('Enable', 'smsa-woocommerce'),
                    'option2' => __('Disable', 'smsa-woocommerce'),
                ),
                'id'      => 'wc_samsa_settings_tab_status',
                'default' => 'option2'
            ),
            'passkey' => array(
                'name' => __('API Passkey', 'smsa-woocommerce'),
                'type' => 'text',
                'desc' => __('Enter your SMSA Express API passkey.', 'smsa-woocommerce'),
                'id'   => 'wc_samsa_settings_tab_passkey'
            ),
            'name' => array(
                'name' => __('Contact Name', 'smsa-woocommerce'),
                'type' => 'text',
                'desc' => __('Store contact name for shipments.', 'smsa-woocommerce'),
                'id'   => 'wc_samsa_settings_tab_name'
            ),
            'telephone' => array(
                'name' => __('Telephone', 'smsa-woocommerce'),
                'type' => 'text',
                'desc' => __('Store contact telephone number.', 'smsa-woocommerce'),
                'id'   => 'wc_samsa_settings_tab_telephone'
            ),
            'address1' => array(
                'name' => __('Address Line 1', 'smsa-woocommerce'),
                'type' => 'text',
                'desc' => __('Primary store address.', 'smsa-woocommerce'),
                'id'   => 'wc_samsa_settings_tab_address1'
            ),
            'address2' => array(
                'name' => __('Address Line 2', 'smsa-woocommerce'),
                'type' => 'text',
                'desc' => __('Secondary store address (optional).', 'smsa-woocommerce'),
                'id'   => 'wc_samsa_settings_tab_address2'
            ),
            'city' => array(
                'name' => __('City', 'smsa-woocommerce'),
                'type' => 'text',
                'desc' => __('Store city.', 'smsa-woocommerce'),
                'id'   => 'wc_samsa_settings_tab_city'
            ),
            'pincode' => array(
                'name' => __('Postal Code', 'smsa-woocommerce'),
                'type' => 'text',
                'desc' => __('Store postal code.', 'smsa-woocommerce'),
                'id'   => 'wc_samsa_settings_tab_pincode'
            ),
            'country' => array(
                'name' => __('Country', 'smsa-woocommerce'),
                'type' => 'text',
                'desc' => __('Store country (e.g., KSA).', 'smsa-woocommerce'),
                'id'   => 'wc_samsa_settings_tab_country'
            ),
            'section_end' => array(
                'type' => 'sectionend',
                'id'   => 'wc_samsa_settings_tab_section_end'
            )
        );

        return apply_filters('wc_samsa_settings_tab_settings', $settings);
    }
}
