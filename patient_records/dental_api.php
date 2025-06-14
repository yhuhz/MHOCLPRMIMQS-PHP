<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");







require_once('../include/MysqliDb.php');
date_default_timezone_set('Asia/Manila');

class API
{
  protected $db;
    
    public function __construct() {
    $this->db = new MysqliDb([
        'host' => getenv('DB_HOST'), // sql.freedb.tech
        'username' => getenv('DB_USER'), // freedb_yhuhz
        'password' => getenv('DB_PASS'), // your-password
        'db' => getenv('DB_NAME'), // freedb_MHOCLPRMIMQS
        'port' => 3306,
        'connect_timeout' => 5 // Fail fast if connection fails
    ]);

    if ($this->db->getLastError()) {
        die("DB Error: " . $this->db->getLastError());
    }
}

    public function httpGet()
    {
      $payload = (array) json_decode($_GET['payload']);

      $dental_array = [];

      if (isset($payload['record_id'])) {
        $this->db->where('dental_id', $payload['record_id']);
        $dental_array['prescription'] = $this->db->get('tbl_prescription', null, 'medicine_name, quantity');

        $this->db->where('dental_id', $payload['record_id']);

        
      }

      $this->db->where('status', 0);
      $dental_records = $this->db->get('tbl_dental');
      $dental_records = $dental_records[0];

      if (isset($dental_records['doctor_id'])) {
        $this->db->where('user_id', $dental_records['doctor_id']);
        $doctor_name = $this->db->get('tbl_users', null, 'CONCAT(first_name, " ", last_name, IFNULL(CONCAT(" ", suffix), "")) AS name');
        $dental_records['doctor_name'] = $doctor_name[0]['name'];
        }
      
      
      $this->db->where('patient_id', $dental_records['patient_id']);
      $dental_array['dental_chart'] = $this->db->get('tbl_dental_chart');

      


        echo json_encode(array('status' => 'success',
                                  'record' => $dental_records,
                                  'array' => $dental_array,
                                  'method' => 'GET'
                                ));


    }

    public function httpPost($payload)
    {
      // print_r($payload); return;
      $payload = (array) $payload;
      $dental_record = $payload;
      $dental_record['dental_id'] = $this->db->insert('tbl_dental', $dental_record);

        //MANIPULATE IN FRONTEND
        //Check if dental chart exists
        $this->db->where('patient_id', $dental_record['patient_id']);
        $check_dental_chart = $this->db->getValue('tbl_dental_chart', 'count(*)');
        if ($check_dental_chart < 1) {

          for ($i = 1; $i <= 32; $i++) {
            $dental_chart_array = array('patient_id' => $dental_record['patient_id'], 'tooth_number' => $i);
            $dental_chart_array['dental_chart_id'] = $this->db->insert('tbl_dental_chart', $dental_chart_array);

          }
        }



        if ($dental_record['dental_id']) {
          $dental_record['record_id'] = $dental_record['dental_id'];
          unset($dental_record['dental_id']);

          $dental_record['date'] = $dental_record['checkup_date'];
          unset($dental_record['checkup_date']);

          echo json_encode(array('status' => 'success',
                                    'data' => $dental_record,
                                    'method' => 'POST'
                                  ));
        } else {
          echo json_encode(array('status' => 'fail',
                                    'message' => 'Failed to add record',
                                    'method' => 'POST'
                                  ));
          return;
        }

    }

    public function httpPut($payload)
    {
      $payload = (array) $payload;
      // print_r($payload); return;

      $dental_record = (array) $payload['dental_record'];
      $dental_record['dental_id'] = $dental_record['record_id'];
      unset($dental_record['record_id']);
      $dental_chart = (array) $payload['dental_chart'];

      //EDIT DENTAL RECORD
        $this->db->where('dental_id', $dental_record['dental_id']);
        $this->db->update('tbl_dental', $dental_record);
        $this->db->where('user_id', $dental_record['doctor_id']);
        $name = $this->db->get('tbl_users', null, 'CONCAT(first_name, " ", last_name, IFNULL(CONCAT(" ", suffix), "")) AS name');
         $dental_record['doctor_name'] = $name[0]['name'];

        foreach($dental_chart as $tooth) {
          $tooth = (array) $tooth;
          $this->db->where('dental_chart_id', $tooth['dental_chart_id']);
          $this->db->update('tbl_dental_chart', $tooth);
        }

        $dental_array = [];
        $dental_array['dental_chart'] = $dental_chart;

        $prescription_array = [];
        if (isset($payload['prescription'])) {
          //Remove existing prescription records
          $this->db->where('dental_id', $dental_record['dental_id']);
          $this->db->delete('tbl_prescription');

          //Replace records
          foreach ($payload['prescription'] as $prescription) {
            $prescription = (array) $prescription;
            $prescription['dental_id'] = $dental_record['dental_id'];
            $prescription['prescription_id'] = $this->db->insert('tbl_prescription', $prescription);

            if ($prescription['dental_id']) {
              array_push($prescription_array, $prescription);
            }
          }
        }

        $dental_array['prescription'] = $prescription_array;

        $dental_record['record_id'] = $dental_record['dental_id'];
        unset($dental_record['dental_id']);

        echo json_encode(array('status' => 'success',
                                'record' => $dental_record,
                                'array' => $dental_array,
                                'method' => 'PUT'
                              ));


    }

    public function httpDelete($payload)
    {

      //DELETE DENTAL RECORD

        $this->db->where('dental_id', $_GET['record_id']);
        $dental_record = $this->db->update('tbl_dental', array('status' => 1));

        echo json_encode(array('status' => 'success',
                                'data' => 'Record successfully deleted',
                                'method' => 'DELETE'
                              ));

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
