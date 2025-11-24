<?php
/**
 * SMSA Shipment Class
 *
 * Handles shipment creation, cancellation, and admin meta boxes
 *
 * @package SMSA_WooCommerce
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMSA_Shipment
 *
 * Manages SMSA shipment operations and admin interface
 */
class SMSA_Shipment {

    /**
     * Initialize the shipment class
     *
     * @return void
     */
    public static function init() {
        // AJAX handlers
        add_action('wp_ajax_smsa_create_shipment', array(__CLASS__, 'ajax_create_shipment'));
        add_action('wp_ajax_nopriv_smsa_create_shipment', array(__CLASS__, 'ajax_create_shipment'));
        add_action('wp_ajax_smsa_cancel_shipment', array(__CLASS__, 'ajax_cancel_shipment'));
        add_action('wp_ajax_nopriv_smsa_cancel_shipment', array(__CLASS__, 'ajax_cancel_shipment'));

        // Legacy AJAX handlers for backwards compatibility
        add_action('wp_ajax_my_unique_action', array(__CLASS__, 'ajax_create_shipment'));
        add_action('wp_ajax_nopriv_my_unique_action', array(__CLASS__, 'ajax_create_shipment'));
        add_action('wp_ajax_my_unique_cancl_action', array(__CLASS__, 'ajax_cancel_shipment'));
        add_action('wp_ajax_nopriv_my_unique_cancl_action', array(__CLASS__, 'ajax_cancel_shipment'));

        // Admin meta box
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_box'));

        // My account order actions
        add_filter('woocommerce_my_account_my_orders_actions', array(__CLASS__, 'add_order_actions'), 10, 2);

        // Checkout fields modification
        add_filter('woocommerce_checkout_fields', array(__CLASS__, 'modify_checkout_city_field'));
    }

    /**
     * AJAX handler for creating shipments
     *
     * @return void
     */
    public static function ajax_create_shipment() {
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $json = array();

        if (!get_option('wc_samsa_settings_tab_status')) {
            $json['error'] = 'SMSA Module Disabled!';
        }

        if (!$order_id) {
            $json['error'] = 'Order ID not found!';
        }

        $order_info = wc_get_order($order_id);

        if (!$json && $order_info) {
            $weight = 0;
            $item_desc = array();
            $order_products = $order_info->get_items();

            foreach ($order_products as $product_item) {
                $item_desc[] = $product_item['name'];
                $quantity = $product_item->get_quantity();
                $product = $product_item->get_product();
                $product_weight = $product->get_weight();
                $weight += floatval($product_weight * $quantity);
            }

            if ($weight == 0) {
                $weight = 1;
            }

            $cod_amount = 0;
            $shipping_city = $order_info->get_shipping_city();
            $billing_city = $order_info->get_billing_city();

            if (empty($shipping_city)) {
                $shipping_city = $billing_city;
            }

            $payment_method = $order_info->get_payment_method();
            if ($payment_method == 'cod') {
                $cod_amount = wc_format_decimal($order_info->get_total(), 2);
            }

            // Get Arabic city names
            $shipping_city = SMSA_Model::get_arabic_city_name($shipping_city);
            $billing_city = SMSA_Model::get_arabic_city_name($billing_city);

            $customer_name = $order_info->get_billing_first_name() . ' ' . $order_info->get_billing_last_name();
            $customer_address = $order_info->get_billing_address_1();

            $args = array(
                'passKey'      => SMSA_API::get_passkey(),
                'refNo'        => $order_id,
                'sentDate'     => date('Y/m/d'),
                'idNo'         => $order_id,
                'cName'        => $customer_name,
                'cntry'        => 'KSA',
                'cCity'        => $shipping_city ?: $order_info->get_shipping_city(),
                'cZip'         => $order_info->get_shipping_postcode(),
                'cPOBox'       => '',
                'cMobile'      => $order_info->get_billing_phone(),
                'cTel1'        => '',
                'cTel2'        => '',
                'cAddr1'       => $customer_address,
                'cAddr2'       => $order_info->get_shipping_address_2(),
                'shipType'     => 'DLV',
                'PCs'          => count($order_products),
                'cEmail'       => $order_info->get_billing_email(),
                'carrValue'    => 0,
                'carrCurr'     => '',
                'codAmt'       => $cod_amount,
                'weight'       => $weight,
                'custVal'      => $order_info->get_subtotal(),
                'custCurr'     => $order_info->get_currency(),
                'insrAmt'      => '',
                'insrCurr'     => '',
                'itemDesc'     => implode(', ', $item_desc),
                'sName'        => !empty(get_bloginfo('name')) ? get_bloginfo('name') : 'My Store',
                'sContact'     => get_option('wc_samsa_settings_tab_name'),
                'sAddr1'       => get_option('wc_samsa_settings_tab_address1'),
                'sAddr2'       => get_option('wc_samsa_settings_tab_address2'),
                'sCity'        => get_option('wc_samsa_settings_tab_city'),
                'sPhone'       => get_option('wc_samsa_settings_tab_telephone'),
                'sCntry'       => get_option('wc_samsa_settings_tab_country'),
                'prefDelvDate' => '',
                'gpsPoints'    => ''
            );

            $result = SMSA_API::add_shipment($args);
            $response = $result['soapBody']['addShipResponse']['addShipResult'];

            if (is_numeric($response)) {
                // Get PDF
                $pdf_result = SMSA_API::get_pdf($response);

                $filename = $response . '.pdf';
                $file_path = ABSPATH . 'awb/' . $filename;
                $content = base64_decode($pdf_result['soapBody']['getPDFResponse']['getPDFResult']);
                file_put_contents($file_path, $content);

                // Save to database
                $save_data = array(
                    'order_id'         => $order_id,
                    'awb_number'       => $response,
                    'reference_number' => $order_id,
                    'pickup_date'      => '',
                    'shipment_label'   => $filename,
                    'status'           => 'TrackShipment',
                    'date_added'       => current_time('mysql', 1)
                );

                SMSA_Model::save_shipment($save_data);

                $json['success']['message'] = 'success';
                $json['success']['awp'] = $response;

                // Add order note in Arabic
                $note = "تم اصدار بوليصة شحن برقم: $response";
                $order_info->add_order_note($note);
            } else {
                $json['error'] = $response;
            }
        }

        echo json_encode($json);
        die();
    }

    /**
     * AJAX handler for canceling shipments
     *
     * @return void
     */
    public static function ajax_cancel_shipment() {
        $json = array();

        if (!get_option('wc_samsa_settings_tab_status')) {
            $json['error'] = 'SMSA Module Disabled!';
        }

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        if (!$order_id) {
            $json['error'] = 'Order ID not found!';
        }

        $awb_number = isset($_POST['awb_number']) ? sanitize_text_field($_POST['awb_number']) : '';
        if (empty($awb_number)) {
            $json['error'] = 'AWB number not found!';
        }

        $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';
        if (empty($reason)) {
            $json['error'] = 'Cancellation reason is required!';
        }

        if (!$json) {
            $result = SMSA_API::cancel_shipment($awb_number, $reason);

            if (strpos($result['soapBody']['cancelShipmentResponse']['cancelShipmentResult'], 'Success') !== false) {
                $save_data = array(
                    'order_id'         => $order_id,
                    'awb_number'       => $awb_number,
                    'reference_number' => '',
                    'pickup_date'      => '',
                    'shipment_label'   => '',
                    'status'           => 'cancelShipment',
                    'date_added'       => current_time('mysql', 1)
                );

                SMSA_Model::save_shipment($save_data);

                $json['success'] = $result['soapBody']['cancelShipmentResponse']['cancelShipmentResult'];

                // Add order note in Arabic
                $order = wc_get_order($order_id);
                $note = "تم الغاء البوليصة رقم: $awb_number بناءً على طلب العميل";
                $order->add_order_note($note);
            } else {
                $json['error'] = $result['soapBody']['cancelShipmentResponse']['cancelShipmentResult'];
            }
        }

        echo json_encode($json);
        die();
    }

    /**
     * Add meta box to order page
     *
     * @return void
     */
    public static function add_meta_box() {
        add_meta_box(
            'smsa_shipment_meta_box',
            'SMSA Express',
            array(__CLASS__, 'render_meta_box'),
            'shop_order',
            'side',
            'high'
        );
    }

    /**
     * Render the meta box content
     *
     * @param WP_Post $post The post object
     * @return void
     */
    public static function render_meta_box($post) {
        wp_nonce_field(basename(__FILE__), 'smsa-meta-box-nonce');

        $order_id = $post->ID;
        $order_info = wc_get_order($order_id);

        $shipping_method = @array_shift($order_info->get_shipping_methods());
        $shipping_method_id = $shipping_method ? $shipping_method['method_id'] : '';

        if ($shipping_method_id != 'ses_samsa_express') {
            echo '<label style="text-align:center;">لم يتم تحديد خيار الشحن بواسطة سمسا</label>';
        }

        $results = SMSA_Model::get_consignment($order_id);
        $data = array(
            'consignment' => array(),
            'awb_btn'     => 1,
            'cancel_btn'  => 1,
            'awb_number'  => ''
        );

        foreach ($results as $result_obj) {
            $result = (array) $result_obj;

            $shipment_label = $result['shipment_label']
                ? get_site_url() . '/awb/' . $result['shipment_label']
                : false;

            $data['consignment'][] = array(
                'awb_number'       => $result['awb_number'],
                'shipment_label'   => $shipment_label,
                'reference_number' => $result['reference_number'],
                'status'           => $result['status'],
                'pickup_date'      => $result['pickup_date'],
                'date_added'       => $result['date_added'],
                'track'            => '&awb_number=' . $result['awb_number'],
            );

            $data['awb_btn'] = 0;

            if ($result['status'] == 'cancelShipment') {
                $data['cancel_btn'] = 0;
            }

            $data['awb_number'] = $result['awb_number'];
        }

        self::render_meta_box_html($data, $order_id);
    }

    /**
     * Render the meta box HTML
     *
     * @param array $data Shipment data
     * @param int $order_id Order ID
     * @return void
     */
    private static function render_meta_box_html($data, $order_id) {
        ?>
        <div class="meta_box_smsa_buttons">
            <?php if ($data['consignment']): ?>
                <?php $result = end($data['consignment']); ?>
                <div class="smsa-shipment-info">
                    <label>رقم الشحنة:</label>
                    <h1 class="shipment_awp_number"><?php echo esc_html($result['awb_number']); ?></h1>
                </div>

                <?php if ($result['shipment_label']): ?>
                    <a href="<?php echo esc_url($result['shipment_label']); ?>" target="_blank" class="get_pdf button button-normal">طباعة البوليصة</a>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($data['awb_btn']): ?>
                <a id="button-smsa-awb" data-title="توليد بوليصة جديدة" data-order_id="<?php echo esc_attr($order_id); ?>" class="get_awp full-width-btn button button-primary">توليد بوليصة جديدة</a>
            <?php elseif ($data['cancel_btn']): ?>
                <a id="button-smsa-cancel" data-title="الغاء البوليصة" data-order_id="<?php echo esc_attr($order_id); ?>" data-awb_number="<?php echo esc_attr($data['awb_number']); ?>" class="cancel_awp button button-danger">الغاء البوليصة</a>
            <?php endif; ?>

            <div class="shipment_status">
                <?php
                if (!empty($result['awb_number'])) {
                    SMSA_Tracking::display_shipment_status($result['awb_number']);
                }
                ?>
            </div>

            <div id="response-awb"></div>
        </div>

        <?php self::render_meta_box_scripts($order_id); ?>
        <?php
    }

    /**
     * Render meta box JavaScript
     *
     * @param int $order_id Order ID
     * @return void
     */
    private static function render_meta_box_scripts($order_id) {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#button-smsa-cancel').click(function(e) {
                if ($(this).hasClass('disabled')) return;

                var confirm_cancel = confirm('هل أنت متأكد من طلب إلغاء الشحنة؟');
                if (!confirm_cancel) return;

                var $btn = $(this);
                $btn.attr('disabled', 'disabled').addClass('disabled').text('').append('<i class="fa fa-circle-o-notch fa-spin" style="font-size:24px"></i>');

                e.preventDefault();

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'post',
                    dataType: 'json',
                    data: {
                        action: 'smsa_cancel_shipment',
                        awb_number: $btn.data('awb_number'),
                        order_id: $btn.data('order_id'),
                        reason: 'CANCELLED ON CLIENTS REQUEST.'
                    },
                    success: function(json) {
                        $('.text-success, .text-danger').remove();
                        if (json.error) {
                            $('#response-awb').append('<div class="text-danger"><i class="fa fa-exclamation-circle"></i> ' + json.error + '</div>');
                        }
                        if (json.success) {
                            $('#response-awb').append('<div class="text-success"><i class="fa fa-check-circle"></i> ' + json.success + '</div>');
                            window.location.reload(true);
                        }
                        resetLoading('#button-smsa-cancel');
                    }
                });
            });

            $('#button-smsa-awb').click(function(e) {
                if ($(this).hasClass('disabled')) return;

                var $btn = $(this);
                $btn.attr('disabled', 'disabled').addClass('disabled').text('').append('<i class="fa fa-circle-o-notch fa-spin" style="font-size:24px"></i>');

                e.preventDefault();

                $.ajax({
                    type: 'post',
                    dataType: 'json',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'smsa_create_shipment',
                        order_id: $btn.data('order_id')
                    },
                    success: function(json) {
                        $('.text-success, .text-danger').remove();
                        if (json.error) {
                            $('#response-awb').append('<div class="text-danger"><i class="fa fa-exclamation-circle"></i> ' + json.error + '</div>');
                        }
                        if (json.success) {
                            $('#response-awb').append('<div class="text-success"><i class="fa fa-check-circle"></i> ' + json.success.message + '</div>');
                            $('#button-smsa-awb').fadeOut(300);
                            $('.meta_box_smsa_buttons').append('<div class="smsa-shipment-info"><label>رقم الشحنة:</label><h1 class="shipment_awp_number">' + json.success.awp + '</h1></div>');
                            $('.smsa-shipment-info').append('<a href="<?php echo home_url(); ?>/awb/' + json.success.awp + '.pdf" target="_blank" class="get_pdf button button-normal">طباعة البوليصة</a>');
                            $('.shipment_status').remove();
                        }
                        resetLoading('#button-smsa-awb');
                    }
                });
            });

            function resetLoading(selector) {
                var $el = $(selector);
                $el.removeAttr('disabled').removeClass('disabled');
                var button_txt = $el.data('title');
                $el.text(button_txt);
                $el.find('i').remove();
                setTimeout(function() {
                    $('#response-awb *').fadeOut(300, function() {
                        $('#response-awb *').remove();
                    });
                }, 3000);
            }
        });
        </script>
        <?php
    }

    /**
     * Add tracking action to customer orders
     *
     * @param array $actions Existing actions
     * @param WC_Order $order The order object
     * @return array Modified actions
     */
    public static function add_order_actions($actions, $order) {
        $order_id = $order->get_order_number();
        $order_info = wc_get_order($order_id);

        if (!$order_info->is_paid()) {
            return $actions;
        }

        $results = SMSA_Model::get_consignment($order_id);

        foreach ($results as $result_obj) {
            $result = (array) $result_obj;

            if ($result['shipment_label']) {
                ?>
                <a href="<?php echo esc_url(SMSA_Tracking::get_tracking_url($result['awb_number'])); ?>" target="_blank" class="view">
                    <i class="fa fa-truck"></i>
                    تتبع الشحنة
                </a>
                <?php
            }
        }

        return $actions;
    }

    /**
     * Modify checkout city field to dropdown
     *
     * @param array $fields Checkout fields
     * @return array Modified fields
     */
    public static function modify_checkout_city_field($fields) {
        $cities = self::get_saudi_cities();

        $city_args = wp_parse_args(array(
            'type'        => 'select',
            'options'     => $cities,
            'input_class' => array('wc-enhanced-select')
        ), $fields['shipping']['shipping_city']);

        $fields['shipping']['shipping_city'] = $city_args;
        $fields['billing']['billing_city'] = $city_args;

        wc_enqueue_js("
            jQuery(':input.wc-enhanced-select').filter(':not(.enhanced)').each(function() {
                var select2_args = { minimumResultsForSearch: 5 };
                jQuery(this).select2(select2_args).addClass('enhanced');
            });
        ");

        return $fields;
    }

    /**
     * Get list of Saudi cities
     *
     * @return array City list with English key and Arabic value
     */
    private static function get_saudi_cities() {
        return array(
            'Abha' => 'ابها',
            'Abu Arish' => 'ابو عريش',
            'Afif' => 'عفيف',
            'Ahad Al Masarhah' => 'احد المسارحة',
            'Ahad Rafidah' => 'احد رفيدة',
            'Ain Dar' => 'عين دار',
            'Al Aflaj (Layla)' => 'الافلاج (ليلى)',
            'Al Ahsa' => 'الاحساء',
            'Al Ayun' => 'العيون',
            'Al Dayer' => 'الداير',
            'Al Jafr' => 'الجفر',
            'Al Jouf' => 'الجوف',
            'Al Ruqi' => 'الروقي',
            'Anak' => 'عنك',
            'Aqiq' => 'العقيق',
            'Arar' => 'عرعر',
            'Artawiyah' => 'الأرطاوية',
            'At Tuwal' => 'الطوال',
            'Atawlah' => 'الاطاولة',
            'Bad' => 'البدع',
            'Badar Hunain' => 'بدر حنين',
            'Badaya ' => 'البدايع ',
            'Badr' => 'بدر',
            'Baha' => 'الباحة',
            'Bahrah' => 'بحره',
            'Bahrain Causeway' => 'جسر الملك فهد',
            'Baljurashi' => 'بلجرشي',
            'Bani Malek ' => 'بني مالك ',
            'Bariq' => 'بارق',
            'Bashayer' => 'البشاير',
            'Batha' => 'البطحاء',
            'Baysh' => 'بيش',
            'Bellasmar' => 'بللسمر',
            'Bijadiyah' => 'البجادية',
            'Bishah' => 'بيشة',
            'Bukayriyah' => 'البكيرية',
            'Buqaiq' => 'بقيق',
            'Buraydah' => 'بريدة',
            'Dammam' => 'الدمام',
            'Dammam Airport' => 'مطار الدمام',
            'Darb' => 'الدرب',
            'Dawmat Al Jandal' => 'دومة الجندل',
            'Dhahran' => 'الظهران',
            'Dhahran Al Janoub' => 'ظهران الجنوب',
            'Dhalim' => 'ظلم',
            'Dhamad' => 'ضمد',
            'Dhuba' => 'ضبا',
            'Dhurma' => 'ضرما',
            'Dilam' => 'الدلم',
            'Diriyah' => 'الدرعية',
            'Dukhnah' => 'الدخنة',
            'Duwadimi' => 'الدوادمي',
            'Farasan' => 'فرسان',
            'Ghat' => 'الغاط',
            'Haditha' => 'الحديثة',
            'Hafar Al Baten' => 'حفر الباطن',
            'Hail' => 'حايل',
            'Halit Ammar' => 'حاله عمار',
            'Hanakiyah' => 'الحناكية',
            'Haql' => 'حقل',
            'Hawtat Bani Tamim' => 'حوطة بني تميم',
            'Hawtat Sudayr ' => 'حوطة سدير ',
            'Hayer' => 'الحاير',
            'Hufuf' => 'الهفوف',
            'Huraymila' => 'حريملاء',
            'Jadidah Arar' => 'جديدة عرعر',
            'Jamoum' => 'الجموم',
            'Jash' => 'جاش',
            'Jazan' => 'جازان',
            'Jeddah' => 'جدة',
            'Jeddah Airport' => 'مطار جدة',
            'Jubail' => 'الجبيل',
            'Kamil' => 'الكامل',
            'Khabra' => 'الخبراء',
            'Khafji' => 'الخفجي',
            'Khamasin' => 'الخماسين',
            'Khamis Mushayt' => 'خميس مشيط',
            'Kharj' => 'الخرج',
            'Khayber' => 'خيبر',
            'Khubar' => 'الخبر',
            'Khulais' => 'خليص',
            'Khurmah' => 'الخمرة',
            'King Khalid City' => 'مدينة الملك خالد',
            'Lith' => 'الليث',
            'Madinah' => 'المدينة',
            'Mahd Ad Dhahab' => 'مهد الذهب',
            'Majardah' => 'المجاردة',
            'Majmaah' => 'المجمعة',
            'Makkah' => 'مكة',
            'Mandaq' => 'المندق',
            'Masturah' => 'مستورة',
            'Midhnab' => 'المذنب',
            'Mubarraz' => 'المبرز',
            'Mudhaylif' => 'المظيلف',
            'Muhayil' => 'محايل عسير',
            'Mukhwah' => 'المخواة',
            'Muneefa ' => 'منيفة ',
            'Muwayh' => 'المويه',
            'Muzahmiyah' => 'المزاحمية',
            'Nabaniya' => 'النبانية',
            'Nabhaniah' => 'النبهانية',
            'Nairiyah' => 'النعيرية',
            'Najran' => 'نجران',
            'Nakeea' => 'النقية',
            'Namas' => 'النماص',
            'Nifi' => 'نفي',
            'Qarya Al Uliya' => 'قرية العليا',
            'Qaseem' => 'القصيم',
            'Qaseem Airport' => 'مطار القصيم',
            'Qatif' => 'القطيف',
            'Qaysumah' => 'القيصومة',
            'Qilwah' => 'قلوه',
            'Qunfudhah' => 'القنفذة',
            'Qurayyat' => 'القريات',
            'Quwayiyah' => 'القويعية',
            'Rabigh' => 'رابغ',
            'Rafayaa Al Gimsh' => 'رفاعة الجمش',
            'Rafha' => 'رفحاء',
            'Rahima' => 'رحيمة',
            'Ranyah' => 'رنية',
            'Ras Tannurah' => 'راس تنورة',
            'Rass' => 'الرس',
            'Rayn' => 'الرين',
            'Rijal Almaa' => 'رجال ألمع',
            'Riyadh' => 'الرياض',
            'Riyadh Airport' => 'مطار الرياض',
            'Riyadh Al Khabra' => 'رياض الخبراء',
            'Rumah' => 'رماح',
            'Ruwaidah' => 'الرويضة',
            'Sabya' => 'صبيا',
            'Safwa' => 'صفوى',
            'Saira' => 'ساير',
            'Sajir' => 'ساجر',
            'Salwa' => 'سلوى',
            'Samtah' => 'صامطة',
            'Sapt Al Alaya' => 'سبت العليا',
            'Sarat Abida' => 'سراة عبيدة',
            'Sarrar' => 'الصرار',
            'Sayhat' => 'سيهات',
            'Sayirah' => 'السعيرة',
            'Sayl Al Kabir' => 'السيل الكبير',
            'Shaibah' => 'الشيبة',
            'Shaqra' => 'شقراء',
            'Sharourah' => 'شرورة',
            'Shedgum' => 'شدقم',
            'Shuqayq' => 'الشقيق',
            'Skakah' => 'سكاكا',
            'Sulayyil' => 'السليل',
            'Tabarjal' => 'طبرجل',
            'Tabuk' => 'تبوك',
            'Taif' => 'الطايف',
            'Taima' => 'تيماء',
            'Tanajib' => 'تناجيب',
            'Tanumah' => 'التنومه',
            'Tarib' => 'طريب',
            'Tarut (Darin)' => 'تاروت (دارين)',
            'Tathlith' => 'تثليث',
            'Thqbah' => 'الثقبه',
            'Thuwal' => 'ثول',
            'Turayf' => 'طريف',
            'Turbah' => 'تربه',
            'Turbah (Makkah)' => 'تربة (مكة)',
            'Udhayliyah' => 'العضيلية',
            'Ula' => 'العلا',
            'Ummlujj' => 'املج',
            'Unayzah' => 'عنيزة',
            'Uqlat As Suqur' => 'عقلة الصقور',
            'Uthmaniyah' => 'العثمانية',
            'Uyun Al Jiwa' => 'عيون الجواء',
            'Wadi Al-Dawasir' => 'وادي الدواسر',
            'Wadi Bin Hashbal' => 'وادي بن شهبل',
            'Wajh' => 'الوجه',
            'Yanbu' => 'ينبع',
            'Zulfi' => 'الزلفي',
        );
    }
}
