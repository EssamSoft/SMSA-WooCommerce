<?php
class WC_Samsa_Settings_Tab {

    /**
     * Bootstraps the class and hooks required actions & filters.
     *
     */
    public static function init() {
        add_filter( 'woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 50 );
        add_action( 'woocommerce_settings_tabs_samsa_settings_tab', __CLASS__ . '::settings_tab' );
        add_action( 'woocommerce_update_options_samsa_settings_tab', __CLASS__ . '::update_settings' );
    }


    /**
     * Add a new settings tab to the WooCommerce settings tabs array.
     *
     * @param array $settings_tabs Array of WooCommerce setting tabs & their labels, excluding the Subscription tab.
     * @return array $settings_tabs Array of WooCommerce setting tabs & their labels, including the Subscription tab.
     */
    public static function add_settings_tab( $settings_tabs ) {
        $settings_tabs['samsa_settings_tab'] = __( 'SAMSA Settings Tab', 'woocommerce-samsa-settings-tab' );
        return $settings_tabs;
    }


    /**
     * Uses the WooCommerce admin fields API to output settings via the @see woocommerce_admin_fields() function.
     *
     * @uses woocommerce_admin_fields()
     * @uses self::get_settings()
     */
    public static function settings_tab() {
        woocommerce_admin_fields( self::get_settings() );
    }


    /**
     * Uses the WooCommerce options API to save settings via the @see woocommerce_update_options() function.
     *
     * @uses woocommerce_update_options()
     * @uses self::get_settings()
     */
    public static function update_settings() {
        woocommerce_update_options( self::get_settings() );
    }


    /**
     * Get all the settings for this plugin for @see woocommerce_admin_fields() function.
     *
     * @return array Array of settings for @see woocommerce_admin_fields() function.
     */
    public static function get_settings() {

        $settings = array(
            'section_title' => array(
                'name'     => __( 'Section Title', 'woocommerce-samsa-settings-tab' ),
                'type'     => 'title',
                'desc'     => '',
                'id'       => 'wc_samsa_settings_tab_section_title'
            ),
            'status' => array(
                'name' => __( 'Status', 'woocommerce-samsa-settings-tab' ),
                'type' => 'select',
                'options' => array(
                    'option1' => 'Enable',
                    'option2' => 'Disable',
                ),
                'id'   => 'wc_samsa_settings_tab_status',
                'default' => array()
            ),
            'passkey' => array(
                'name' => __( 'Passkey', 'woocommerce-samsa-settings-tab' ),
                'type' => 'text',
                'id'   => 'wc_samsa_settings_tab_passkey'
            ),
             'name' => array(
                'name' => __( 'Name', 'woocommerce-samsa-settings-tab' ),
                'type' => 'text',
                'id'   => 'wc_samsa_settings_tab_name'
            ),
              'telephone' => array(
                'name' => __( 'Telephone', 'woocommerce-samsa-settings-tab' ),
                'type' => 'text',
                'id'   => 'wc_samsa_settings_tab_Telephone'
            ),
               'address1' => array(
                'name' => __( 'Address1', 'woocommerce-samsa-settings-tab' ),
                'type' => 'text',
                'id'   => 'wc_samsa_settings_tab_address1'
            ),
                'address2' => array(
                'name' => __( 'Address2', 'woocommerce-samsa-settings-tab' ),
                'type' => 'text',
                'id'   => 'wc_samsa_settings_tab_address2'
            ),
                 'city' => array(
                'name' => __( 'City', 'woocommerce-samsa-settings-tab' ),
                'type' => 'text',
                'id'   => 'wc_samsa_settings_tab_city'
            ),
                  'pincode' => array(
                'name' => __( 'Pincode', 'woocommerce-samsa-settings-tab' ),
                'type' => 'text',
                'id'   => 'wc_samsa_settings_tab_pincode'
            ),
                   'country' => array(
                'name' => __( 'Country', 'woocommerce-samsa-settings-tab' ),
                'type' => 'text',
                'id'   => 'wc_samsa_settings_tab_country'
            ),
            'section_end' => array(
                 'type' => 'sectionend',
                 'id' => 'wc_samsa_settings_tab_section_end'
            )
        );

        return apply_filters( 'wc_samsa_settings_tab_settings', $settings );
    }

}

WC_Samsa_Settings_Tab::init();