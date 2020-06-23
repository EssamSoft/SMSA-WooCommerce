<?php
include_once plugin_dir_path(__FILE__).'woocommerce-samsa-express-plugin-functions.php';

add_action('wp_ajax_my_unique_cancl_action','cancelShipment');
add_action('wp_ajax_nopriv_my_unique_cancl_action','cancelShipment');


function cancelShipment(){
        $json = array();

         if(!get_option('wc_samsa_settings_tab_status')){
            $json ['error'] = $this->language->get('error_smsa_status');
        }

        if (!isset($_POST['order_id']) || !$_POST['order_id']) {
            $json ['error'] = $this->language->get('error_order_id');
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            if (empty($_POST['reason'])) {
                $json['error'] = $this->language->get('error_reason');
            }

            if(!isset($_POST['awb_number'])){
                $json['error'] = $this->language->get('error_awb_number');
            }

        }


         if(empty(get_option('wc_samsa_settings_tab_passkey'))){
             $passKey = 'Testing1';
        }else{
            $passKey = get_option('wc_samsa_settings_tab_passkey');
        }

        if(!$json) {
            $args = array(
                'awbNo'     => $_POST['awb_number'],
                'passkey'   => $passKey,
                'reas'      => $_POST['reason']
            );

            $xml = createXml('http://track.smsaexpress.com/secom/cancelShipment', 'cancelShipment', $args);
            $result = send($xml);
            if (strpos($result['soapBody']['cancelShipmentResponse']['cancelShipmentResult'], 'Success') !== false) {
                $save_data = array(
                    'order_id'  => $_POST['order_id'],
                    'awb_number'    => $_POST['awb_number'],
                    'reference_number'  =>'',
                    'pickup_date' => '',
                    'shipment_label' => '',
                    'status' => 'cancelShipment',
                    'date_added' => current_time('mysql', 1)
                );
                global $table_prefix, $wpdb;

                $tblname = 'smsa';
                $wp_samsa_table = $table_prefix . "$tblname";


                $wpdb->insert($wp_samsa_table , $save_data);

                $json['success'] = $result['soapBody']['cancelShipmentResponse']['cancelShipmentResult'];

                $order_id = $_POST['order_id'];
                $order = wc_get_order(  $order_id );
                $awb_number = $_POST['awb_number'];
                $note = "تم الغاء البوليصة رقم: $awb_number بناءً على طلب العميل";
                $order->add_order_note( $note );

            } else {
                $json['error'] = $result['soapBody']['cancelShipmentResponse']['cancelShipmentResult'];
            }
        }



          echo json_encode($json);
    die();

    }


add_action('wp_ajax_my_unique_action','addShip');
add_action('wp_ajax_nopriv_my_unique_action','addShip');


function addShip(){

    $order_id = intval( $_POST['order_id'] );
    $json = array();


    if(!get_option('wc_samsa_settings_tab_status')){
        $json ['error'] = 'Samsa Module Disabled!';
    }

    if (!isset($order_id) || !$order_id) {
        $json ['error'] = 'Order Id not find!';
    }


    $order_info = wc_get_order( $order_id );




    if(!$json && $order_info) {

        $weight = 0;


        $order_products = $order_info->get_items();
        foreach ( $order_products as $product_item ) {
            $item_desc[] = $product_item['name'];
            $product_id = $product_item['product_id'];
            $quantity = $product_item->get_quantity();
            $product = $product_item->get_product();
            $product_weight = $product->get_weight();
            $weight += floatval( $product_weight * $quantity );
        }

        if($weight == 0){
            $weight = 1;
        }

        $codAmt = 0;

        $shipping_city=$order_info->get_shipping_city();
        $billing_city=$order_info->get_billing_city();
        if($shipping_city == "") {
            $shipping_city = $billing_city;

        }
        $payment_method=$order_info->get_payment_method();

        if($payment_method == 'cod'){
            $codAmt = wc_format_decimal($order_info->get_total(), 2);
        }
        if(true){


            global $table_prefix, $wpdb;
            $query = $wpdb->get_results("SELECT city_id FROM " . $table_prefix . "smsa_city WHERE name LIKE '%" . $shipping_city . "%' AND language_id = 1 LIMIT 1");

            $city_id = $query->city_id;

            $query2 = $wpdb->get_row("SELECT name FROM " . $table_prefix . "smsa_city WHERE city_id = '" . $city_id . "' AND language_id = 2 LIMIT 1");


            if($query2->name){
                $shipping_city = $query2->name;
            }



            $query3 = $wpdb->get_row("SELECT city_id FROM " . $table_prefix . "smsa_city WHERE name LIKE '%" . $billing_city . "%' AND language_id = 1 LIMIT 1");

            $city_id = $query3->city_id;
            $query4 = $wpdb->get_row("SELECT name FROM " . $table_prefix . "smsa_city WHERE city_id = '" . $city_id . "' AND language_id = 2 LIMIT 1");

            if($query2->name){
                $billing_city = $query4->name;
            }
        }


        if(empty(get_option('wc_samsa_settings_tab_passkey'))){
            $passKey = 'Testing1';
       }else{
            $passKey = get_option('wc_samsa_settings_tab_passkey');
       }




        // $essf_name = $order_info->get_shipping_first_name().' '.$order_info->get_shipping_last_name();
        // if ( empty($essf_name)) {
            $essf_name  = $order_info->get_billing_first_name().' '.$order_info->get_billing_last_name();
        // }

        // $essf_address = $order_info->get_shipping_address_1();
        // if ( empty($essf_address)) {
            $essf_address  = $order_info->get_billing_address_1();
        // }




        $args = array(
            'passKey'       => $passKey,
            'refNo'         => $order_id,
            'sentDate'      => date('Y/m/d'),
            'idNo'          => $order_id,
            'cName'         => $essf_name,
            'cntry'         => 'KSA',
            'cCity'         => ($shipping_city) ? $shipping_city : $order_info->get_shipping_city(),
            'cZip'          => $order_info->get_shipping_postcode(),
            'cPOBox'        => '',
            'cMobile'       => $order_info->get_billing_phone(),
            'cTel1'         => '',
            'cTel2'         => '',
            'cAddr1'        => $essf_address,
            'cAddr2'        => $order_info->get_shipping_address_2(),
            'shipType'      => 'DLV',
            'PCs'           => count($order_products),
            'cEmail'        => $order_info->get_billing_email(),
            'carrValue'     => $order_info->get_total(),
            'carrValue'     => 0,
            'carrCurr'      => '',
            'codAmt'        => $codAmt,
            'weight'        => $weight,
            'custVal'       => $order_info->get_subtotal(),
            'custCurr'      => $order_info->get_currency(),
            'insrAmt'       => '',
            'insrCurr'      => '',
            'itemDesc'      => implode(", ",$item_desc),
            'sName'         => (!empty(get_bloginfo( 'name' ))) ? get_bloginfo( 'name' ) : 'My Store',
            'sContact'      => get_option('wc_samsa_settings_tab_name'),
            'sAddr1'        => get_option('wc_samsa_settings_tab_address1'),
            'sAddr2'        => get_option('wc_samsa_settings_tab_address2'),
            'sCity'         => get_option('wc_samsa_settings_tab_city'),
            'sPhone'        => get_option('wc_samsa_settings_tab_telephone'),
            'sCntry'        => get_option('wc_samsa_settings_tab_country'),
            'prefDelvDate'  => '',
            'gpsPoints'     => ''
        );

        $xml = createXml('http://track.smsaexpress.com/secom/addShip', 'addShip', $args);
        $result = send($xml);
        $response = $result['soapBody']['addShipResponse']['addShipResult'];
        if(is_numeric($response)){
            $param = array(
                'awbNo'  =>$response,
                'passKey'=>$passKey,
            );
            $PDF = createXml('http://track.smsaexpress.com/secom/getPDF', 'getPDF', $param);
            $resultPDF = send($PDF);

            $filename = $response.'.pdf';
            $file_path = '../awb/'.$filename;
            $content =base64_decode($resultPDF['soapBody']['getPDFResponse']['getPDFResult']);
            $fp = fopen($file_path,"wb");
            fwrite($fp,$content);
            fclose($fp);

            $save_data = array(
                'order_id'  => $order_id,
                'awb_number'    => $response,
                'reference_number'  => $order_id,
                'pickup_date' => '',
                'shipment_label' => $filename,
                'status' => 'TrackShipment',
                'date_added' => current_time('mysql', 1)
            );

             global $table_prefix, $wpdb;

        $tblname = 'smsa';
        $wp_samsa_table = $table_prefix . "$tblname";


        $wpdb->insert($wp_samsa_table , $save_data);

            $json['success']['message'] = 'success';
            $json['success']['awp'] = $response;


            $note = "تم اصدار بوليصة شحن برقم: $response";
            $order_info->add_order_note( $note );


        }else{
            $json['error'] = $response;
        }
    }
    echo json_encode($json);



    die();

}




    /*adding front track shipment and download shipment buttons in my accont order page */
    function ses_add_my_account_order_actions( $actions, $order ) {

        $order_id  = $order->get_order_number();
        $order_info = wc_get_order( $order_id );

        if(!$order_info->is_paid()) {

            return $actions;
        }


        $shipping_method = @array_shift($order_info->get_shipping_methods());
        // $shipping_method_id = $shipping_method['method_id'];




        $results = getConsignment($order_id);
        $data['consignment'] = array();

        $data['awb_btn'] = 1;
        $data['cancel_btn'] = 1;

        foreach ($results as $result_obj) {

            $result = (array)$result_obj;

            if($result['shipment_label']){
                $shipment_label = get_site_url().'/awb/'.$result['shipment_label'];
            } else {
                $shipment_label = false;
            }
            $data['consignment'][] = array(
                'awb_number'  => $result['awb_number'],
                'shipment_label'  => $shipment_label,
                'reference_number'  => $result['reference_number'],
                'status'  => $result['status'],
                'pickup_date'  => $result['pickup_date'],
                'date_added'  => $result['date_added'],
                'track'       => '&awb_number=' . $result['awb_number'],
            );
        }


        ?>

        <?php if($data['consignment']){ ?>
            <?php foreach($data['consignment'] as $result) { ?>
                <?php if($result['shipment_label']){ ?>
                        <a href="http://www.smsaexpress.com/arabic/TrackAr.aspx?tracknumbers=<?php echo $result['awb_number']; ?>" target="_blank" class="view">
                        <i class="fa fa-truck"></i>
                        تتبع الشحنة
                        </a>

                <?php } ?>

            <?php } ?>
        <?php
        } ?>


        <?php

        return $actions;
    }


    add_filter( 'woocommerce_my_account_my_orders_actions', 'ses_add_my_account_order_actions', 10, 2 );









    // Add metabox to genarate AWP essf
    function add_meta_box_for_awp() {
        add_meta_box("meta_box_for_awp", "SMSA Express", "smsa_meta_box_control", "shop_order", "side", "high", null);
    }

    add_action("add_meta_boxes", "add_meta_box_for_awp");
    function smsa_meta_box_control($object) {
        wp_nonce_field(basename(__FILE__), "meta-box-nonce");

        $order_id = $object->ID;
        $order_info = wc_get_order( $order_id );

        $shipping_method = @array_shift($order_info->get_shipping_methods());
        $shipping_method_id = $shipping_method['method_id'];

        if ($shipping_method_id != 'ses_samsa_express') {
            ?>
                <label style="text-align:center;">لم يتم تحديد خيار الشحن بواسطة سمسا</label>
            <?php
        }



        $results = getConsignment($order_id);
        $data['consignment'] = array();

        $data['awb_btn'] = 1;
        $data['cancel_btn'] = 1;

        foreach ($results as $result_obj) {

            $result = (array)$result_obj;

            if($result['shipment_label']){
                $shipment_label = get_site_url().'/awb/'.$result['shipment_label'];
            } else {
                $shipment_label = false;
            }
            $data['consignment'][] = array(
                'awb_number'  => $result['awb_number'],
                'shipment_label'  => $shipment_label,
                'reference_number'  => $result['reference_number'],
                'status'  => $result['status'],
                'pickup_date'  => $result['pickup_date'],
                'date_added'  => $result['date_added'],
                'track'       => '&awb_number=' . $result['awb_number'],
            );
            $data['awb_btn'] = 0;
            if($result['status'] == 'cancelShipment'){
                $data['cancel_btn'] = 0;
            }

            $data['awb_number'] = $result['awb_number'];

        }

        ?><div class="meta_box_smsa_buttons"><?php


         if($data['consignment']){ ?>

            <?php $result = end($data['consignment']); // foreach($data['consignment'] as $result) { ?>

                <div class="smsa-shipment-info">

                    <label>رقم الشحنة:</label>
                    <h1 class="shipment_awp_number"><?php echo $result['awb_number']; ?></h1>
                </div>

                <?php if($result['shipment_label']){ ?>
                    <a href="<?php echo $result['shipment_label']; ?>" target="_blank" class="get_pdf button button-normal">طباعة البوليصة</a>
                <?php } ?>

            <?php //} ?>
        <?php } ?>

        <?php if($data['awb_btn']): ?>

            <a id="button-smsa-awb" data-title="توليد بوليصة جديدة" data-order_id="<?php echo $order_id; ?>" class="get_awp full-width-btn button button-primary">توليد بوليصة جديدة</a>

        <?php elseif($data['cancel_btn']): ?>

            <a id="button-smsa-cancel" data-title="الغاء البوليصة" data-order_id="<?php echo $order_id; ?>" data-awb_number="<?php echo $data['awb_number']; ?>" class="cancel_awp button button-danger">الغاء البوليصة</a>

        <?php endif; ?>

            <div class="shipment_status">

                <?php get_shipment_status($result['awb_number']); ?>
            </div>

            <div id="response-awb" ></div>

        </div>

        <script type="text/javascript">

            jQuery(document).ready( function() {


                jQuery("#button-smsa-cancel").click( function(e) {


                    if(jQuery(this).hasClass("disabled")) { return;}

                    var confirm_cancel = confirm("هل أنت متأكد من طلب إلغاء الشحنة؟");
                    if(!confirm_cancel) { return; }


                    jQuery(this).attr("disabled","disabled");
                    jQuery(this).addClass("disabled");
                    jQuery(this).text("");
                    jQuery(this).append("<i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i>");


                    e.preventDefault();
                    awb_number =  jQuery(this).data('awb_number');
                    order_id =  jQuery(this).data('order_id');
                    jQuery.ajax({
                        url : "<?php echo get_site_url(); ?>/wp-admin/admin-ajax.php",
                        type: 'post',
                        dataType: 'json',
                        data : {action: "my_unique_cancl_action", awb_number : awb_number, order_id : order_id, reason : "CANCELLED ON CLIENTS REQUEST."},
                        success: function(json) {
                            jQuery('.text-success, .text-danger').remove();
                            if (json['error']) {
                                jQuery("#response-awb").append('<div class="text-danger"><i class="fa fa-exclamation-circle"></i> ' + json['error'] + '</div>');
                            }
                            if (json['success']) {
                                jQuery("#response-awb").append('<div class="text-success"><i class="fa fa-check-circle"></i> ' + json['success'] + '</div>');
                                window.location.reload(true);
                            }
                            resetLoading("#button-smsa-cancel");

                        }
                    });
                });

                jQuery("#button-smsa-awb").click( function(e) {

                    if(jQuery(this).hasClass("disabled")) {
                        return;
                    }

                    jQuery(this).attr("disabled","disabled");
                    jQuery(this).addClass("disabled");
                    jQuery(this).text("");
                    jQuery(this).append("<i class='fa fa-circle-o-notch fa-spin' style='font-size:24px'></i>");


                    e.preventDefault();
                    order_id =  jQuery(this).data('order_id');
                    jQuery.ajax({
                        type : "post",
                        dataType: 'json',
                        url : "<?php echo get_site_url(); ?>/wp-admin/admin-ajax.php",
                        data : {action: "my_unique_action", order_id : order_id},
                        success: function(json) {
                            jQuery('.text-success, .text-danger').remove();
                            if (json['error']) {
                                jQuery("#response-awb").append('<div class="text-danger"><i class="fa fa-exclamation-circle"></i> ' + json['error'] + '</div>');
                            }
                            if (json['success']) {
                                jQuery("#response-awb").append('<div class="text-success"><i class="fa fa-check-circle"></i> ' + json['success']['message'] + '</div>');
                                // window.location.reload(true);
                                jQuery("#button-smsa-awb").fadeOut(300);
                                jQuery(".meta_box_smsa_buttons").append("<div class='smsa-shipment-info' ><label>رقم الشحنة:</label><h1 class='shipment_awp_number'>" +json['success']['awp'] + "</h1></div>");
                                jQuery(".smsa-shipment-info").append("<a href='<?php echo get_home_url(); ?>/awb/" +json['success']['awp'] +".pdf' target='_blank' class='get_pdf button button-normal'>طباعة البوليصة</a>")
                                jQuery(".shipment_status").remove();
                            }
                            resetLoading("#button-smsa-awb");
                        }
                    });
                });

                function resetLoading(e) {
                    // reset
                    jQuery(e).removeAttr("disabled");
                    jQuery(e).removeClass("disabled");
                    var button_txt = jQuery(e).data("title");
                    jQuery(e).text(button_txt);
                    jQuery(e + " i").remove();
                    setTimeout(function(){
                        jQuery("#response-awb *").fadeOut( 300, function() {
                            jQuery("#response-awb *").remove();
                        });
                    }, 3000);
                }

            });

            </script>










        <?php

    }








    function ace_change_city_to_dropdown( $fields ) {


        $city_args = wp_parse_args( array(
        'type' => 'select',
'options' => array(
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
        ), 'input_class' => array(
                'wc-enhanced-select',
            )
        ), $fields['shipping']['shipping_city'] );

        $fields['shipping']['shipping_city'] = $city_args;
        $fields['billing']['billing_city'] = $city_args;



        wc_enqueue_js( "
        jQuery( ':input.wc-enhanced-select' ).filter( ':not(.enhanced)' ).each( function() {
        var select2_args = { minimumResultsForSearch: 5 };
        jQuery( this ).select2( select2_args ).addClass( 'enhanced' );
        });" );


        return $fields;

    }
    add_filter( 'woocommerce_checkout_fields', 'ace_change_city_to_dropdown' );



    ?>