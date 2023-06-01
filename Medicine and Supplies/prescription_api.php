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

        if ($payload['mode'] === 'pending') {
            $records_list = [];
            // OPD
            $this->db->join('tbl_patient_info p', 'p.patient_id=opd.patient_id', 'LEFT');
            $this->db->where('opd.status', 0);
            $this->db->where('checkup_date', $date_array, 'BETWEEN');

            $opd = $this->db->get('tbl_opd opd', null, 'opd_id, opd.patient_id, checkup_date, first_name, middle_name, last_name, suffix');

            foreach($opd as $opd_record) {
                $opd_record['department'] = 1;
                if ($payload['mode'] === 'pending') {
                    $this->db->where('status', 0);
                } else {
                    $this->db->where('status', 1);
                }
                
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

            $dental = $this->db->get('tbl_dental d', null, 'dental_id, d.patient_id, checkup_date, first_name, middle_name, last_name, suffix');

            foreach($dental as $dental_record) {
                $dental_record['department'] = 2;
                if ($payload['mode'] === 'pending') {
                    $this->db->where('status', 0);
                } else {
                    $this->db->where('status', 1);
                }
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
                $checkup = $this->db->get('tbl_prenatal_checkup', null, 'prenatal_checkup_id, checkup_date');

                if ($checkup !== []) {
                    foreach($checkup as $item) {
                        $item['department'] = 3;
                        $item['patient_id'] = $prenatal_record['patient_id'];
                        $item['first_name'] = $prenatal_record['first_name'];
                        $item['middle_name'] = $prenatal_record['middle_name'];
                        $item['last_name'] = $prenatal_record['last_name'];
                        $item['suffix'] = $prenatal_record['suffix'];

                        if ($payload['mode'] === 'pending') {
                            $this->db->where('status', 0);
                        } else {
                            $this->db->where('status', 1);
                        }
                        $this->db->where('prenatal_checkup_id', $item['prenatal_checkup_id']);
                        $item['prescription'] = $this->db->get('tbl_prescription');
                        if ($item['prescription'] !== []) {
                            array_push($records_list, $item);
                }
                    }
                    
                }
            }

            if ($payload['mode'] === 'done') {
                $records_array = [];
                foreach($records_list as $record) {
                    $this->db->where('mr.status', 0);
                    $this->db->where('patient_id', $record['patient_id']);
                    $this->db->where('release_date', $record['checkup_date']);

                    $this->db->join('tbl_medicine_inventory mi', 'mi.medicine_id=mr.medicine_id', 'LEFT');
                    $medicines = $this->db->get('tbl_medicine_release mr', null, 'med_release_id, mr.medicine_id, generic_name, brand_name, mr.quantity');

                    $medicine_array = [];
                    foreach($medicines as $medicine) {
                        $meds = [];
                        $meds['med_release_id'] = $medicine['med_release_id'];
                        $meds['medicine_details'] = array('medicine_id' => $medicine['medicine_id'], 'medicine_name' => $medicine['generic_name'] . " - ". $medicine['brand_name']);
                        $meds['quantity'] = $medicine['quantity'];

                        array_push($medicine_array, $meds);
                    }

                    $record['medicines'] = $medicine_array;

                    array_push($records_array, $record);
                }

                $records_list = $records_array;
            }
        } else if ($payload['mode'] === 'done') {

            $records_list = [];
            //Get patient ID
            $this->db->where('patient_id', NULL, 'IS NOT');
            $this->db->where('status', 0);
            $this->db->where('release_date', $date_array, 'BETWEEN');
            $this->db->groupBy('patient_id');
            $patients = $this->db->get('tbl_medicine_release', null, 'patient_id');
            // print_r($patients); return;

            foreach($patients as $patient) {
                $this->db->where('patient_id', $patient['patient_id']);
                $this->db->where('status', 0);
                $details = $this->db->get('tbl_patient_info', null, 'first_name, middle_name, last_name, suffix');

                $patient['first_name'] = $details[0]['first_name'];
                $patient['middle_name'] = $details[0]['middle_name'];
                $patient['last_name'] = $details[0]['last_name'];
                $patient['suffix'] = $details[0]['suffix'];

                $this->db->where('patient_id', $patient['patient_id']);
                $this->db->where('release_date', $date_array, 'BETWEEN');
                $this->db->join('tbl_medicine_inventory mi', 'mi.medicine_id=mr.medicine_id', 'LEFT');
                    $medicines = $this->db->get('tbl_medicine_release mr', null, 'med_release_id, mr.medicine_id, generic_name, brand_name, mr.quantity, release_date');
                // print_r($medicines); return;

                $medicine_array = [];
                foreach($medicines as $medicine) {
                    $meds = [];
                    $meds['med_release_id'] = $medicine['med_release_id'];
                    $meds['medicine_details'] = array('medicine_id' => $medicine['medicine_id'], 'medicine_name' => $medicine['generic_name'] . " - ". $medicine['brand_name']);
                    $meds['quantity'] = $medicine['quantity'];
                    $meds['release_date'] = $medicine['release_date'];

                    array_push($medicine_array, $meds);
                }
                
                $patient['medicines'] = $medicine_array;

                $prescription = [];

                //OPD
                $this->db->where('patient_id', $patient['patient_id']);
                $this->db->where('checkup_date', $date_array, 'BETWEEN');
                $this->db->where('status', 0);
                $opd = $this->db->get('tbl_opd', null, 'opd_id');
                // print_r($opd);

                foreach($opd as $opd_record) {
                    
                    $this->db->where('opd_id', $opd_record['opd_id']);
                    $this->db->where('status', 1);
                    $opd_prescription = $this->db->get('tbl_prescription');

                    foreach ($opd_prescription as $pres) {
                        array_push($prescription, $pres);
                    }
                }

                //Dental
                $this->db->where('patient_id', $patient['patient_id']);
                $this->db->where('checkup_date', $date_array, 'BETWEEN');
                $this->db->where('status', 0);
                $dental = $this->db->get('tbl_dental', null, 'dental_id');

                foreach($dental as $dental_record) {
                    
                    $this->db->where('dental_id', $dental_record['dental_id']);
                    $this->db->where('status', 1);
                    $dental_prescription = $this->db->get('tbl_prescription');

                    foreach ($dental_prescription as $pres) {
                        array_push($prescription, $pres);
                    }
                }

                //Prenatal Checkup
                $this->db->where('patient_id', $patient['patient_id']);
                $this->db->where('status', 0);
                $prenatal = $this->db->get('tbl_prenatal', null, 'prenatal_id');

                foreach($prenatal as $prenatal_record) {
                    
                    $this->db->where('prenatal_id', $prenatal_record['prenatal_id']);
                    $this->db->where('status', 0);
                    $this->db->where('checkup_date', $date_array, 'BETWEEN');
                    $prenatal_checkup = $this->db->get('tbl_prenatal_checkup', null, 'prenatal_checkup_id');

                    foreach($prenatal_checkup as $prenatal_record) {
                    
                        $this->db->where('prenatal_checkup_id', $prenatal_record['prenatal_checkup_id']);
                        $this->db->where('status', 1);
                        $prenatal_prescription = $this->db->get('tbl_prescription');
    
                        foreach ($prenatal_prescription as $pres) {
                            array_push($prescription, $pres);
                        }
                    }
                }

                $patient['prescription'] = $prescription;
                array_push($records_list, $patient);
            }

            //Get doctor ID
            $this->db->where('doctor_id', NULL, 'IS NOT');
            $this->db->where('status', 0);
            $this->db->where('release_date', $date_array, 'BETWEEN');
            $this->db->groupBy('doctor_id', 'DESC');
            $doctors = $this->db->get('tbl_medicine_release', null, 'doctor_id');

            foreach($doctors as $doctor) {
                $this->db->where('user_id', $doctor['doctor_id']);
                $this->db->where('status', 0);
                $details = $this->db->get('tbl_users', null, 'first_name, middle_name, last_name, suffix');

                $doctor['first_name'] = $details[0]['first_name'];
                $doctor['middle_name'] = $details[0]['middle_name'];
                $doctor['last_name'] = $details[0]['last_name'];
                $doctor['suffix'] = $details[0]['suffix'];

                $this->db->where('doctor_id', $doctor['doctor_id']);
                $this->db->where('release_date', $date_array, 'BETWEEN');
                $this->db->join('tbl_medicine_inventory mi', 'mi.medicine_id=mr.medicine_id', 'LEFT');
                    $medicines = $this->db->get('tbl_medicine_release mr', null, 'med_release_id, mr.medicine_id, generic_name, brand_name, mr.quantity, release_date');
                // print_r($medicines); return;

                $medicine_array = [];
                foreach($medicines as $medicine) {
                    $meds = [];
                    $meds['med_release_id'] = $medicine['med_release_id'];
                    $meds['medicine_details'] = array('medicine_id' => $medicine['medicine_id'], 'medicine_name' => $medicine['generic_name'] . " - ". $medicine['brand_name']);
                    $meds['quantity'] = $medicine['quantity'];
                    $meds['release_date'] = $medicine['release_date'];

                    array_push($medicine_array, $meds);
                }
                
                $doctor['prescription'] = [];
                $doctor['medicines'] = $medicine_array;
                array_push($records_list, $doctor);
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
        $payload = (array) $payload;

        foreach ($payload as $prescription) {
            $prescription = (array) $prescription;
            $this->db->where('prescription_id', $prescription['prescription_id']);
            $this->db->update('tbl_prescription', array('status' => 1));
        }

        echo json_encode(array('status' => 'success',
                                  'data' => $payload,
                                  'method' => 'PUT'
                                ));
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
