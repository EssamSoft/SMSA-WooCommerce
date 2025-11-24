<?php
/**
 * SMSA API Handler Class
 *
 * Handles SOAP API communication with SMSA Express
 *
 * @package SMSA_WooCommerce
 * @subpackage Includes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SMSA_API
 *
 * Handles all API communication with SMSA Express SOAP service
 */
class SMSA_API {

    /**
     * SMSA API endpoint URL
     *
     * @var string
     */
    private static $api_url = 'http://track.smsaexpress.com/SECOM/SMSAwebService.asmx';

    /**
     * Create XML SOAP envelope for API request
     *
     * @param string $soap_action The SOAP action URL
     * @param string $method The method name to call
     * @param array $variables The parameters to pass
     * @return array Contains 'xml' and 'header' keys
     */
    public static function create_xml($soap_action, $method, $variables) {
        $xml_content = '<?xml version="1.0" encoding="utf-8"?>
            <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
              <soap:Body>
                <' . $method . ' xmlns="http://track.smsaexpress.com/secom/">';

        if (count($variables)) {
            foreach ($variables as $key => $val) {
                $xml_content .= '<' . $key . '>' . esc_html($val) . '</' . $key . '>';
            }
        }

        $xml_content .= '</' . $method . '>
              </soap:Body>
            </soap:Envelope>';

        $headers = array(
            "POST /SECOM/SMSAwebService.asmx HTTP/1.1",
            "Host: track.smsaexpress.com",
            "Content-Type: text/xml; charset=utf-8",
            "Content-Length: " . strlen($xml_content),
            "SOAPAction: " . $soap_action
        );

        return array(
            'xml'    => $xml_content,
            'header' => $headers
        );
    }

    /**
     * Send SOAP request to SMSA API
     *
     * @param array $args Contains 'xml' and 'header' keys from create_xml()
     * @return array Parsed response as associative array
     */
    public static function send($args = array()) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, $args['header']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, self::$api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $args['xml']);
        $content = curl_exec($ch);
        curl_close($ch);

        $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $content);
        $xml = new SimpleXMLElement($response);
        $array = json_decode(json_encode((array)$xml), true);

        return $array;
    }

    /**
     * Get the API passkey from settings or use default
     *
     * @return string The passkey
     */
    public static function get_passkey() {
        $passkey = get_option('wc_samsa_settings_tab_passkey');
        return !empty($passkey) ? $passkey : 'Testing1';
    }

    /**
     * Add a new shipment via SMSA API
     *
     * @param array $args Shipment parameters
     * @return array API response
     */
    public static function add_shipment($args) {
        $xml = self::create_xml('http://track.smsaexpress.com/secom/addShip', 'addShip', $args);
        return self::send($xml);
    }

    /**
     * Cancel a shipment via SMSA API
     *
     * @param string $awb_number AWB number to cancel
     * @param string $reason Cancellation reason
     * @return array API response
     */
    public static function cancel_shipment($awb_number, $reason) {
        $args = array(
            'awbNo'   => $awb_number,
            'passkey' => self::get_passkey(),
            'reas'    => $reason
        );

        $xml = self::create_xml('http://track.smsaexpress.com/secom/cancelShipment', 'cancelShipment', $args);
        return self::send($xml);
    }

    /**
     * Get PDF label for a shipment
     *
     * @param string $awb_number AWB number
     * @return array API response containing PDF data
     */
    public static function get_pdf($awb_number) {
        $args = array(
            'awbNo'   => $awb_number,
            'passKey' => self::get_passkey(),
        );

        $xml = self::create_xml('http://track.smsaexpress.com/secom/getPDF', 'getPDF', $args);
        return self::send($xml);
    }

    /**
     * Get tracking information for a shipment
     *
     * @param string $awb_number AWB number
     * @return array API response containing tracking data
     */
    public static function get_tracking($awb_number) {
        $args = array(
            'awbNo'   => $awb_number,
            'passkey' => self::get_passkey(),
        );

        $xml = self::create_xml('http://track.smsaexpress.com/secom/getTracking', 'getTracking', $args);
        return self::send($xml);
    }
}
