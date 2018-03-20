<?php

namespace Kase;

class MarathonUtil {

    static function raw_fetch($url, $request) {
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
                syslog(LOG_WARNING, "Expected data in the field $expected_type, not found.");
                return false;
            }
        } elseif ($result_array["status"] == "ERROR") {
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

    static function flatten_timereports(Array $timereports) {
        $key_names = ["employee_id", "date", "client_id", "project_no", "feecode_id"];
        $output = [];
        foreach ($timereports as $timereport) {
            $key_parts = [];
            foreach ($key_names as $key_name) {
                $key_parts[] = $timereport[$key_name];
            }
            $key = md5(implode("*", $key_parts));
            if (!isset($output[$key])) {
                $output[$key] = $timereport;
                $output[$key]["hours"] = 0;
                $output[$key]["comment"] = [];
                $output[$key]["date"] = date("Ymd", strtotime($timereport["date"]));
            }
            $output[$key]["hours"] += $timereport["hours"];
            if (is_string($timereport["comment"])) {
                $output[$key]["comment"][] = $timereport["comment"];
            }
        }
        foreach ($output as $key => $timereport) {
            $output[$key]["comment"] = implode(PHP_EOL, $timereport["comment"]);
            $output[$key]["date"] = (int)$timereport["date"];
            $output[$key]["hours"] = number_format((float)$timereport["hours"], 2);
        }
        return array_values($output);
    }

    static function cast_to_float($input_array, $fields_to_cast, $decimals = 2) {
        foreach ($fields_to_cast as $field_name) {
            if (isset($input_array[$field_name])) {
                $input_array[$field_name] = number_format((float)$input_array[$field_name], $decimals, '.', false);
            }
        }
        return $input_array;
    }

    static function cast_to_date($input_array, $fields_to_cast, $date_format = "Ymd") {
        foreach ($fields_to_cast as $field_name) {
            if (isset($input_array[$field_name])) {
                $input_array[$field_name] = date($date_format, strtotime($input_array[$field_name]));
            }
        }
        return $input_array;
    }

    static function cast_to_int($input_array, $fields_to_cast) {
        foreach ($fields_to_cast as $field_name) {
            if (isset($input_array[$field_name])) {
                $input_array[$field_name] = (int)$input_array[$field_name];
            }
        }
        return $input_array;
    }

    static function remove_char($input_array, $fields_to_cast, $char_to_remove, $replace = "") {
        if ($fields_to_cast == "*") {
            $fields_to_cast = array_keys($input_array);
        } elseif (!is_array($fields_to_cast)) {
            $fields_to_cast = [];
        }
        foreach ($fields_to_cast as $field_name) {
            if (isset($input_array[$field_name])) {
                $input_array[$field_name] = str_replace($char_to_remove, $replace, $input_array[$field_name]);
            }
        }
        return $input_array;
    }

    static function output_csv($input_array, $output_file = "php://output") {
        $all_keys = [];
        foreach ($input_array as $element) {
            $all_keys = array_merge(array_keys($element));
        }
        $all_keys = array_unique($all_keys);

        $fp = fopen($output_file, 'w');
        fputcsv($fp, $all_keys);
        foreach ($input_array as $element) {
            $row = [];
            foreach ($all_keys as $key) {
                $row[] = @$element[$key];
            }
            fputcsv($fp, $row);
        }
        fclose($fp);
    }

    static function sort_array_by_key($input_array, $key) {
        usort($input_array, function ($item1, $item2) use ($key) {
            if ($item1[$key] == $item2[$key]) return 0;
            return $item1[$key] < $item2[$key] ? -1 : 1;
        });
        return $input_array;
    }
}