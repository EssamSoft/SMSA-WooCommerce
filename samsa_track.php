<?php


function get_shipment_status($awb_number) {


  $data['result'] = array();

  if(get_option('wc_samsa_settings_tab_status') && (isset($awb_number) || $awb_number)){
      $args = array(
          'awbNo'     => $awb_number,
          'passkey'   => get_option('wc_samsa_settings_tab_passkey'),
      );

      $xml = createXml('http://track.smsaexpress.com/secom/getTracking', 'getTracking', $args);

      $result = send($xml);

      if(isset($result['soapBody']['getTrackingResponse']['getTrackingResult']['diffgrdiffgram']) && $result['soapBody']['getTrackingResponse']['getTrackingResult']['diffgrdiffgram']){
          $response = $result['soapBody']['getTrackingResponse']['getTrackingResult']['diffgrdiffgram'];

          if(isset($response['NewDataSet']['Tracking'][0])){
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
  }


  if($data['result']){

    echo  $data['status'] . "<!-- ";
    print_r($data);
    echo " -->";

  }else{
    echo 'لا توجد بيانات للشحنة';
  }


}

?>
