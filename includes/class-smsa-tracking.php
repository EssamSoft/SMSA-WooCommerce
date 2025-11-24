<?php
/**
 * SMSA Tracking Class
 *
 * Handles shipment tracking functionality
 *
 * @package SMSA_WooCommerce
 * @subpackage Includes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMSA_Tracking
 *
 * Provides shipment tracking functionality
 */
class SMSA_Tracking {

    /**
     * Get shipment status and display it
     *
     * @param string $awb_number AWB number to track
     * @return void
     */
    public static function display_shipment_status($awb_number) {
        $data = self::get_shipment_status($awb_number);

        if (!empty($data['result'])) {
            echo esc_html($data['status']) . "<!-- ";
            print_r($data);
            echo " -->";
        } else {
            echo 'لا توجد بيانات للشحنة';
        }
    }

    /**
     * Get shipment tracking status from API
     *
     * @param string $awb_number AWB number to track
     * @return array Tracking data
     */
    public static function get_shipment_status($awb_number) {
        $data = array(
            'result' => array(),
            'awbNo'  => '',
            'status' => ''
        );

        if (!get_option('wc_samsa_settings_tab_status')) {
            return $data;
        }

        if (empty($awb_number)) {
            return $data;
        }

        $result = SMSA_API::get_tracking($awb_number);

        if (isset($result['soapBody']['getTrackingResponse']['getTrackingResult']['diffgrdiffgram'])
            && $result['soapBody']['getTrackingResponse']['getTrackingResult']['diffgrdiffgram']) {

            $response = $result['soapBody']['getTrackingResponse']['getTrackingResult']['diffgrdiffgram'];

            if (isset($response['NewDataSet']['Tracking'][0])) {
                $data['awbNo'] = $response['NewDataSet']['Tracking'][0]['awbNo'];
                $data['status'] = $response['NewDataSet']['Tracking'][0]['Activity'];
                unset($response['NewDataSet']['Tracking'][0]);
                $data['result'] = $response['NewDataSet']['Tracking'];
            } else {
                $data['awbNo'] = $response['NewDataSet']['Tracking']['awbNo'];
                $data['status'] = $response['NewDataSet']['Tracking']['Activity'];
                $data['result'][] = $response['NewDataSet']['Tracking'];
            }
        }

        return $data;
    }

    /**
     * Get tracking URL for a shipment
     *
     * @param string $awb_number AWB number
     * @return string Tracking URL
     */
    public static function get_tracking_url($awb_number) {
        return 'http://www.smsaexpress.com/arabic/TrackAr.aspx?tracknumbers=' . urlencode($awb_number);
    }
}
