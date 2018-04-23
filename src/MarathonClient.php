<?php

namespace Kase;

/**
 * Created by PhpStorm.
 * User: michael
 * Date: 07/02/2018
 * Time: 15:20
 */

class MarathonClient {

    const TIMEREPORT = "timereport";
    const CLIENT = "client";
    const PROJECT = "project";
    const INVOICE = "invoice";
    const FEECODE = "feecode";
    const ORDER_NUMBER = "order_number";

    const CONF_SERVER_ADDRESS = "server_address";
    const CONF_PROGRAM = "program";
    const CONF_COMPANY_ID = "company_id";
    const CONF_PASSWORD = ".password";
    const CONF_INTERNAL_PASSWORD = ".internal_password";

    protected $marathon_server_address;
    protected $program;
    protected $password;
    protected $company_id;
    protected $internal_password;

    public function __construct(Array $config) {
        $this->marathon_server_address = $config[self::CONF_SERVER_ADDRESS];
        $this->program = $config[self::CONF_PROGRAM];
        $this->password = $config[self::CONF_PASSWORD];
        $this->company_id = $config[self::CONF_COMPANY_ID];
        $this->internal_password = $config[self::CONF_INTERNAL_PASSWORD];
    }

    public function get_endpoint_url() {
        return "https://{$this->marathon_server_address}/cgi-bin/{$this->program}?{$this->password}";
    }

    protected function __request($type, Array $request_data = [], $expected_type = null) {
        $request_data["type"] = $type;
        $request_data["password"] = $this->internal_password;
        $request_data["company_id"] = $this->company_id;
        $url = $this->get_endpoint_url();
        return MarathonUtil::fetch($url, $request_data, $expected_type);
    }

    protected function __raw_request($type, Array $request_data = []) {
        $request_data["type"] = $type;
        $request_data["password"] = $this->internal_password;
        $request_data["company_id"] = $this->company_id;
        $url = $this->get_endpoint_url();
        return MarathonUtil::raw_fetch($url, $request_data);
    }

    /**
     * Input: filter on client name Output : active clients
     * Filtering can be done on the name. The search pattern can start and/or end by an asterisk.
     * Without asterisk exact match is required. All searches are ignoring case.
     *
     * <?xml version='1.0' encoding='ISO-8859-1'?>
     * <marathon>
     *  <password>xyz</password>
     *  <type>get_clients</type>
     *  <company_id>DS</company_id>
     *  <filter_client_name>*o</filter_client_name>
     * </marathon>
     *
     * Reply:
     * <client id=”VOLV” name=”Volvo AB” internal_name=”Volvo” />
     *
     * @param $company_id
     * @param string $filter_client_name
     */
    public function get_clients($filter_client_name = null) {
        $result = $this->__request(__FUNCTION__, [
            "filter_client_name" => $filter_client_name
        ], self::CLIENT);
        return $result;
    }

    public function get_products($client_id) {

    }

    public function get_agreements($client_id) {
        return $this->__request(__FUNCTION__, [
            "client_id" => $client_id
        ]);
    }

    public function get_agreement_details($agreement_id) {
    }

    public function get_collective_mediatypes() {
        return $this->__request(__FUNCTION__);
    }

    public function get_mediatypes($collective_mediatype_id = null) {
        return $this->__request(__FUNCTION__, [
            "collective_mediatype_id" => $collective_mediatype_id
        ]);
    }

    public function get_discount_codes() {
    }

    public function get_surcharge_codes() {
    }

    public function get_units() {
    }

    public function get_campaign($campaign_id) {
    }

    public function get_campaigns($client_id) {
    }

    public function create_campaign($campaign_data) {
    }

    public function get_plan($plan_id) {
    }

    public function get_changed_plans($from) {
    }

    public function scratch_plan($client_id) {
    }

    /**
     * Returns an flattened and transformed order_lines.
     * plan_nr,orde_nr,inf_lopnr and pris_lopnr creates an unique key.
     *
     * @param $order_number
     * @return array
     */
    public function get_order($order_number) {
        $result = $this->__raw_request(__FUNCTION__, [
            "order_number" => $order_number,
        ]);
        /**
         * Nesting out this terrible structure to create something that might be usable.
         */
        $order = $result["pur"]["orde"];

        if (isset($result["pur"]["orde"]["inf"]["inf-lopnr"])) {
            $infos = [$result["pur"]["orde"]["inf"]];
        } else {
            $infos = $result["pur"]["orde"]["inf"];
        }
        $order_lines = [];
        foreach ($infos as $info) {
            if (isset($info["pris"]["pris-lopnr"])) {
                $prices = [$info["pris"]];
            } else {
                $prices = $info["pris"];
            }
            foreach ($prices as $pris) {
                $order_lines[] = array_merge_recursive($order, $info, $pris);
            }
        }

        /**
         * Removing arrays and moving dashes to underscores.
         */
        $transformed_order_lines = [];
        foreach ($order_lines as $i => $order_line) {
            foreach ($order_line as $key => $value) {
                if (!is_array($order_line[$key])) {
                    $transformed_key = str_replace("-", "_", $key);
                    $transformed_order_lines[$i][$transformed_key] = $value;
                }
            }

        }

        return $transformed_order_lines;
    }

    /**
     * Returns an array with orders numbers
     *
     * @param null $client_id
     * @param null $media_id
     * @param null $from_insertion_date
     * @param null $to_insertion_date
     * @return array
     */
    public function get_orders($client_id = null, $media_id = null, $from_insertion_date = null, $to_insertion_date = null) {
        return $this->__request(__FUNCTION__, [
            "client_id" => $client_id,
            "media_id" => $media_id,
            "from_insertion_date" => $from_insertion_date,
            "to_insertion_date" => $to_insertion_date,
        ], self::ORDER_NUMBER);
    }

    public function get_orders_with_data($client_id = null, $media_id = null, $from_insertion_date = null, $to_insertion_date = null) {
        $order_numbers = $this->get_orders($client_id, $media_id, $from_insertion_date, $to_insertion_date);

        $orders = [];
        foreach ($order_numbers as $order_number) {
            /**
             * Get order returnes
             */
            $orders = array_merge($orders, $this->get_order($order_number));
        }
        foreach ($orders as $key => $order) {
            $orders[$key] = MarathonUtil::cast_to_int($order, [
                "plan_nr",
                "orde_nr",
                "inf_inf_dat",
                "inf_slutdat",
                "inf_lopnr",
                "inf_dagar",
                "orde_mediatyp_kod",
                "orde_bredd",
                "orde_hojd",
                "orde_antalformat",
                "orde_upplaga",
                "orde_lasartal",
                "inf_mtrlnr",
                "pris_lopnr",
                "pris_till_kod"
            ]);
        }
        return $orders;
    }

    public function create_order($client_id) {
    }

    public function change_order($client_id) {
    }

    public function change_client_status($client_id) {
    }

    public function create_order_direct($client_id) {
    }

    public function delete_order($client_id) {
    }

    public function get_invoice($client_id) {
    }

    public function get_placements($client_id) {
    }

    public function get_insertion_dates($client_id) {
    }

    public function get_sizes($client_id) {
    }

    public function get_price($client_id) {
    }

    public function get_proclients($filter_client_name = null, $timereporting = true) {
        $result = $this->__request(__FUNCTION__, [
            "filter_client_name" => $filter_client_name,
            "timereporting" => $timereporting,
        ], self::CLIENT);
        return $result;
    }

    public function get_project($client_id, $project_no = null, $from_date = null, $to_date = null) {
        $project = $this->__request(__FUNCTION__, [
            "client_id" => $client_id,
            "project_no" => $project_no,
            "from_date" => $from_date,
            "to_date" => $to_date
        ]);
        return $project;
    }

    public function get_projects($client_id = null, $timereporting = true) {
        $projects = $this->__request(__FUNCTION__, [
            "client_id" => $client_id,
            "timereporting" => $timereporting
        ], self::PROJECT);
        foreach ($projects as $i => $project) {
            $projects[$i]["key"] = $client_id . $project["id"];
        }
        return $projects;
    }

    public function create_project($client_id) {
    }

    public function get_feecodes($timereporting = true) {
        return $this->__request(__FUNCTION__, [
            "timereporting" => $timereporting,
        ], self::FEECODE);
    }

    public function get_employee($employee_id = null) {
        return $this->__request(__FUNCTION__, [
            "employee_id" => $employee_id
        ]);
    }

    public function get_employees() {
        return $this->__request(__FUNCTION__);
    }

    /**
     * If run without employee_id it will return timereports for all users
     *
     * @param $employee_id
     * @param $from_date
     * @param $to_date
     * @return mixed
     */
    public function get_timereports($employee_id = null, $from_date, $to_date) {
        $result = $this->__request(__FUNCTION__, [
            "employee_id" => $employee_id,
            "from_date" => $from_date,
            "to_date" => $to_date,
        ], self::TIMEREPORT);

        return $result;
    }

    public function get_timereports_flat($employee_id = null, $from_date, $to_date) {
        $timereports = $this->get_timereports($employee_id, $from_date, $to_date);
        $timereports = MarathonUtil::flatten_timereports($timereports);
        $timereports = MarathonUtil::sort_array_by_key($timereports, "date");
        return $timereports;
    }

    public function create_timereport($client_id) {
    }

    /**
     * @param $invoice_number
     * @return mixed
     */
    public function get_proinvoice($invoice_number) {
        $invoice = $this->__request(__FUNCTION__, [
            "invoice_number" => $invoice_number,
        ]);
        if (isset($invoice[self::INVOICE])) {
            return $invoice[self::INVOICE];
        } else {
            return false;
        }
    }

    /**
     * @param $invoice_number
     * @return mixed
     */
    public function get_proinvoice_flat($invoice_number) {
        $invoice = $this->get_proinvoice($invoice_number);
        if (isset($invoice["base_currency"])) {
            foreach ($invoice["base_currency"] as $key => $value) {
                $invoice["base_" . $key] = $value;
            }
        }
        if (isset($invoice["invoice_currency"])) {
            foreach ($invoice["invoice_currency"] as $key => $value) {
                $invoice["invoice_" . $key] = $value;
            }
        }
        foreach ($invoice as $key => $value) {
            if (is_array($invoice[$key])) {
                unset($invoice[$key]);
            }
        }
        //$invoice = MarathonUtil::remove_char($invoice, "*", ",");

        $invoice = MarathonUtil::cast_to_date($invoice, [
            "invoice_date",
            "due_date",
            "booking_date"
        ], "Ymd");
        $invoice = MarathonUtil::cast_to_int($invoice, [
            "invoice_number",
            "invoice_date",
            "due_date",
            "booking_date"
        ]);
        $invoice = MarathonUtil::cast_to_float($invoice, [
            "base_fee",
            "base_purchase",
            "base_other",
            "base_pre_invoiced",
            "base_rounding",
            "base_vat",
            "base_total",
            "invoice_excl_vat",
            "invoice_vat",
            "invoice_total",
        ], 2);
        return $invoice;
    }

    /**
     * Returns all invoices from a number untill there is no more invoices or
     * limit is reached.
     *
     * Fairly efficient. Each call should take max 20 seconds to complete.
     *
     * @param $invoice_start
     * @param int $limit
     * @return array
     */
    public function get_invoices_from_number($invoice_start, $limit = 100) {
        $invoices = [];
        do {
            $invoice_data = [];
            try {
                $invoice_data = $this->get_proinvoice_flat($invoice_start);
                $invoices[] = $invoice_data;
            } catch (\Exception $exception) {

            }
            $invoice_start++;
        } while (count($invoice_data) && count($invoices) < $limit);
        return $invoices;
    }

    public function get_proinvoices($client_id, $project_no) {
        return $this->__request(__FUNCTION__, [
            "client_id" => $client_id,
            "project_no" => $project_no,
        ]);
    }

    /**
     * Input: filtering on media name, media type, collective media type and country Output: active medias
     * Filtering can be done on the name with the field filter_media_name. Filtering can be done with a wild card (*)
     * in the beginning and at the end of the filter string.
     * Filtering can also be done on the media type code, collective media type code and country code with the fields
     * filter_media_type, filter_collective_media_type and filter_country. More than one code can be filtered resulting
     * in all medias with any of the codes returned. When filtering on more than one code the codes should be delimited
     * by comma or space.
     *
     *
     * @param string $filter_media_name
     * @param string $filter_media_type
     * @param string $filter_collective_media_type
     * @param string $filter_country
     * @return mixed
     */
    public function get_medias($filter_media_name = null, $filter_media_type = null, $filter_collective_media_type = null, $filter_country = null) {
        return $this->__request(__FUNCTION__, [
            "filter_media_name" => $filter_media_name,
            "filter_media_type" => $filter_media_type,
            "filter_collective_media_type" => $filter_collective_media_type,
            "filter_country" => $filter_country
        ], "media");
    }

    public function create_plan($plan_data) {
    }

    /**
     * @return array
     */
    public function get_all_projects() {
        $clients = $this->get_proclients();
        $rows = [];
        foreach ($clients as $client) {
            $projects = $this->get_projects($client["id"]);
            foreach ($projects as $project) {
                $row = [];
                foreach ($client as $key => $val) {
                    $row["client_" . $key] = $val;
                }
                foreach ($project as $key => $val) {
                    $row["project_" . $key] = $val;
                }
                $rows[] = $row;
            }
        }
        return $rows;
    }

}