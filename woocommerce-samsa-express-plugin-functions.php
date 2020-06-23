<?php

    function createXml($SOAPAction, $method, $variables){
        $xmlcontent = '<?xml version="1.0" encoding="utf-8"?>
            <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
              <soap:Body>
                <'.$method.' xmlns="http://track.smsaexpress.com/secom/">';
                  if(count($variables)){
                    foreach($variables As $key=>$val){
                        $xmlcontent .= '<'.$key.'>'.$val.'</'.$key.'>';
                    }
                  }
        $xmlcontent .= '</'.$method.'>
              </soap:Body>
            </soap:Envelope>';

        $headers = array(
            "POST /SECOM/SMSAwebService.asmx HTTP/1.1",
            "Host: track.smsaexpress.com",
            "Content-Type: text/xml; charset=utf-8",
            "Content-Length: ".strlen($xmlcontent),
            "SOAPAction: ".$SOAPAction
        );

        return array(
            'xml'       => $xmlcontent,
            'header'    => $headers
        );
    }

    function send($arg=array()){
        $_apiUrl = "http://track.smsaexpress.com/SECOM/SMSAwebService.asmx";

        $ch = curl_init();
        //curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $arg['header']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_URL, $_apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $arg['xml']);
        $content=curl_exec($ch);

        $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $content);
        $xml = new SimpleXMLElement($response);
        $array = json_decode(json_encode((array)$xml), TRUE);
        return $array;
    }

    ?>