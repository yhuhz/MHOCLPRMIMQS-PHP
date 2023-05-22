<?php
// Tells the browser to allow code from any origin to access
header("Access-Control-Allow-Origin: *");
// Tells browsers whether to expose the response to the frontend JavaScript code when the request's credentials mode (Request.credentials) is include
header("Access-Control-Allow-Credentials: true");
// Specifies one or more methods allowed when accessing a resource in response to a preflight request
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE");
// Used in response to a preflight request which includes the Access-Control-Request-Headers to indicate which HTTP headers can be used during the actual request
header("Access-Control-Allow-Headers: Content-Type");

require_once('../include/MysqliDb.php');
date_default_timezone_set('Asia/Manila');

class API
{
    public function __construct()
    {
        $this->db = new MysqliDB('localhost', 'root', '', 'mhoclprmimqs');
    }

    public function httpGet($payload)
    {
        
        $payload = (array) json_decode($_GET['payload']);
        $date_array = [];

        if ($payload['date'] === 'Today') {
            $date_array = [date("Y-m-d"), date("Y-m-d")];

        } else if ($payload['date']  === 'This Week') {
            $today = new DateTime();
            $week_start = clone $today;
            $week_start->modify('last Sunday');
            $week_end = clone $week_start;
            $week_end->modify('+6 days');

            $today = date_format($today, "Y-m-d");
            $week_start = date_format($week_start, "Y-m-d");
            $week_end = date_format($week_end, "Y-m-d");

            $date_array = [$week_start, $week_end];
            // print_r($date_array); return;

        } else if ($payload['date'] === 'This Month') {
            $date_array = [date('Y-m-01'), date('Y-m-t')];
        } else if ($payload['date'] === 'This Year') {
            $date_array = [date('Y-01-01'), date('Y-12-31')];
        } else {
            $date_array = $payload['date'];
        }

        $records_list = [];
        // OPD
        $this->db->join('tbl_patient_info p', 'p.patient_id=opd.patient_id', 'LEFT');
        $this->db->where('opd.status', 0);
        $this->db->where('checkup_date', $date_array, 'BETWEEN');

        $opd = $this->db->get('tbl_opd opd', null, 'opd_id, opd.patient_id, first_name, middle_name, last_name, suffix');

        foreach($opd as $opd_record) {
            $this->db->where('status', 0);
            $this->db->where('opd_id', $opd_record['opd_id']);
            $opd_record['prescription'] = $this->db->get('tbl_prescription');
            if ($opd_record['prescription'] !== []) {
                array_push($records_list, $opd_record);
            }
        }

        // Dental
        $this->db->join('tbl_patient_info p', 'p.patient_id=d.patient_id', 'LEFT');
        $this->db->where('d.status', 0);
        $this->db->where('checkup_date', $date_array, 'BETWEEN');

        $dental = $this->db->get('tbl_dental d', null, 'dental_id, d.patient_id, first_name, middle_name, last_name, suffix');

        foreach($dental as $dental_record) {
            $this->db->where('status', 0);
            $this->db->where('dental_id', $dental_record['dental_id']);
            $dental_record['prescription'] = $this->db->get('tbl_prescription');
            if ($dental_record['prescription'] !== []) {
                array_push($records_list, $dental_record);
            }
        }

        // Prenatal Checkup
        $this->db->join('tbl_patient_info p', 'p.patient_id=pnl.patient_id', 'LEFT');
        $this->db->where('pnl.status', 0);

        $prenatal = $this->db->get('tbl_prenatal pnl', null, 'prenatal_id, pnl.patient_id, first_name, middle_name, last_name, suffix');

        foreach($prenatal as $prenatal_record) {
            $this->db->where('status', 0);
            $this->db->where('prenatal_id', $prenatal_record['prenatal_id']);
            $this->db->where('checkup_date', $date_array, 'BETWEEN');
            $checkup = $this->db->get('tbl_prenatal_checkup', null, 'prenatal_checkup_id');

            if ($checkup !== []) {
                foreach($checkup as $item) {
                    $item['patient_id'] = $prenatal_record['patient_id'];
                    $item['first_name'] = $prenatal_record['first_name'];
                    $item['middle_name'] = $prenatal_record['middle_name'];
                    $item['last_name'] = $prenatal_record['last_name'];
                    $item['suffix'] = $prenatal_record['suffix'];

                    $this->db->where('status', 0);
                    $this->db->where('prenatal_checkup_id', $item['prenatal_checkup_id']);
                    $item['prescription'] = $this->db->get('tbl_prescription');
                    if ($item['prescription'] !== []) {
                        array_push($records_list, $item);
            }
                }
                
            }
        }

        echo json_encode(array('status' => 'success',
                                  'data' => $records_list,
                                  'method' => 'GET'
                                ));

      
    }

    public function httpPost($payload)
    {
      
    }

    public function httpPut($payload)
    {
    
    }

    public function httpDelete($payload)
    {
      
  }
}
/* END OF CLASS */


$received_data = json_decode(file_get_contents('php://input'));
$request_method = $_SERVER['REQUEST_METHOD'];

$api = new API;

if ($request_method == 'GET') {
    $api->httpGet($received_data);
}
if ($request_method == 'POST') {
    $api->httpPost($received_data);
}
if ($request_method == 'PUT') {
    $api->httpPut($received_data);
}
if ($request_method == 'DELETE') {
    $api->httpDelete($received_data);
}
