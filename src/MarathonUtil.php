<?php

namespace Kase;

class MarathonUtil {

    static function raw_fetch($url, $request){
        $request_xml = self::createXML($request);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 500);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
        $data = curl_exec($ch);
        curl_close($ch);
        $result_xmlobj = simplexml_load_string($data);
        $result_json = json_encode($result_xmlobj);
        $result_array = json_decode($result_json, TRUE);
        return $result_array;
    }
    /**
     * @param $url
     * @param $request
     * @return mixed
     * @throws MarathonException
     */
    static function fetch($url, $request, $expected_type) {
        $result_array = self::raw_fetch($url, $request);

         if ($result_array["status"] == "OK") {
            if (is_null($expected_type)) {
                return $result_array;
            } elseif (isset($result_array[$expected_type])) {
                return self::flatten_result($result_array[$expected_type]);
            } else {
                throw new MarathonException("Expected data in the field $expected_type, not found.");
            }
        } elseif (isset($result_array["message"])) {
            throw new MarathonException($result_array["message"]);
        } else {
            throw new MarathonException("Unknown Error");
        }
    }

    /**
     *
     * @param array $request
     * @return \SimpleXMLElement
     */
    static function createXML($request) {
        $xml = new \SimpleXMLElement('<marathon/>');
        foreach ($request as $name => $value) {
            $xml->addChild($name, $value);
        }
        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->encoding = "UTF-8";
        $dom->formatOutput = true;
        return $dom->saveXML();
    }

    static function flatten_result($input) {
        $output = [];
        foreach ($input as $row) {
            if (isset($row["@attributes"])) {
                $row = array_merge_recursive($row, $row["@attributes"]);
                unset($row["@attributes"]);
            }
            $output[] = $row;
        }
        return $output;
    }
}