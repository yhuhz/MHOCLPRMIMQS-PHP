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
      if (isset($payload['prenatal_checkup_id'])) {
        $this->db->where('prenatal_checkup_id', $payload['prenatal_checkup_id']);
        $prenatal_checkup = $this->db->get('prenatal_checkup');
      } else {
        if (isset($payload['record_id'])) {
          $this->db->where('prenatal_id', $payload['record_id']);
        }
        $this->db->where('pn.status', 0);
  
        $this->db->join('tbl_users u', 'u.user_id=pn.midwife_id', 'LEFT');
        
        $prenatal_records = $this->db->get('tbl_prenatal pn', null, 'prenatal_id, midwife_id, patient_id, last_menstruation, previous_full_term, previous_premature, previous_miscarriage, tetanus_vaccine, pn.date_added, CONCAT(first_name, " ", last_name, IFNULL(CONCAT(" ", suffix), "")) AS midwife_name');

        $prenatal_checkup = [];
  
        foreach ($prenatal_records as $prenatal) {
          $this->db->where('prenatal_id', $prenatal['prenatal_id']);
          $this->db->where('status', 0);
          
          $this->db->orderBy('checkup_date', 'DESC');
          $prenatal_checkup_details = $this->db->get('tbl_prenatal_checkup');
          // print_r($prenatal_checkup_details); return;

          foreach($prenatal_checkup_details as $checkup) {
            $this->db->where('prenatal_checkup_id', $checkup['prenatal_checkup_id']);

            $checkup['prescription'] = $this->db->get('tbl_prescription', null, 'medicine_name, quantity');
            array_push($prenatal_checkup, $checkup);
          }

          
        }
  
        if ($prenatal_records) {
          echo json_encode(array('status' => 'success',
                                    'record' => $prenatal_records[0],
                                    'array' => $prenatal_checkup,
                                    'method' => 'GET'
                                  ));
        }
      }
      

    }

    public function httpPost($payload)
    {
      $payload = (array) $payload;

      if (isset($payload['patient_id'])) {
        $prenatal_record = $payload;
        $prenatal_record['prenatal_id'] = $this->db->insert('tbl_prenatal', $prenatal_record);

        if ($prenatal_record['prenatal_id']) {

          $prenatal_record['record_id'] = $prenatal_record['prenatal_id'];
          unset($prenatal_record['prenatal_id']);

          $prenatal_record['date'] = $prenatal_record['date_added'];
          unset($prenatal_record['date_added']);

          echo json_encode(array('status' => 'success',
                                    'data' => $prenatal_record,
                                    'method' => 'POST'
                                  ));
        } else {
          echo json_encode(array('status' => 'fail',
                                    'message' => 'Failed to add record',
                                    'method' => 'POST'
                                  ));
        }
      } else if (isset($payload['temperature'])) {
        $prenatal_checkup = $payload;
        $prescriptions = $prenatal_checkup['prescription'];
        unset($prenatal_checkup['prescription']);

        $prenatal_checkup['prenatal_checkup_id'] = $this->db->insert('tbl_prenatal_checkup', $prenatal_checkup);

        foreach($prescriptions as $prescription) {
          $prescription = (array) $prescription;
          $prescription['prenatal_checkup_id'] = $prenatal_checkup['prenatal_checkup_id'];
          $prescription['prescription_id'] = $this->db->insert('tbl_prescription', $prescription);

        }

        $prenatal_checkup['prescription'] = $prescriptions;


        if ($prenatal_checkup['prenatal_checkup_id']) {

          echo json_encode(array('status' => 'success',
                                    'data' => $prenatal_checkup,
                                    'method' => 'POST'
                                  ));
        } else {
          echo json_encode(array('status' => 'fail',
                                    'message' => 'Failed to add record',
                                    'method' => 'POST'
                                  ));
        }
      }

      
    }

    public function httpPut($payload)
    {
      $payload = (array) $payload;

      if (isset($payload['prenatal'])) {
        $prenatal = (array) $payload['prenatal'];

        $this->db->where('prenatal_id', $prenatal['prenatal_id']);
        $prenatal_record = $this->db->update('tbl_prenatal', $prenatal);

        if ($prenatal_record) {
          $this->db->where('user_id', $prenatal['midwife_id']);
          $name = $this->db->get('tbl_users', null, 'CONCAT(first_name, " ", last_name, IFNULL(CONCAT(" ", suffix), "")) AS name');
          $prenatal['midwife_name'] = $name[0]['name'];
          // print_r($prenatal); return;

          $this->db->where('prenatal_id', $prenatal['prenatal_id']);
          $prenatal_checkup = $this->db->get('tbl_prenatal_checkup');

          echo json_encode(array('status' => 'success',
                                'record' => $prenatal,
                                'array' => $prenatal_checkup,
                                'method' => 'PUT'
                              ));
        }
      } else if (isset($payload['checkup'])) {
        $prenatal_checkup = (array) $payload['checkup'];
        $prescriptions = $prenatal_checkup['prescription'];
        unset($prenatal_checkup['prescription']);

        $this->db->where('prenatal_checkup_id', $prenatal_checkup['prenatal_checkup_id']);
        $prenatal = $this->db->update('tbl_prenatal_checkup', $prenatal_checkup);

        $prescription_array = [];
        $this->db->where('prenatal_checkup_id', $prenatal_checkup['prenatal_checkup_id']);
        $this->db->delete('tbl_prescription');

        foreach($prescriptions as $prescription) {
          $prescription = (array) $prescription;
          $prescription['prenatal_checkup_id'] = $prenatal_checkup['prenatal_checkup_id'];
          $this->db->insert('tbl_prescription', $prescription);
        }

        $prenatal_checkup['prescription'] = $prescriptions;

        $record = [];
        $array = [];

        
        echo json_encode(array('status' => 'success',
                              'record' => $record,
                              'array' => $array,
                              'method' => 'PUT'
                            ));
      
      }
      

    }

    public function httpDelete($payload)
    {
      $this->db->where('prenatal_id', $_GET['record_id']);
        $delete_user = $this->db->update('tbl_prenatal', array('status' => 1));

        if ($delete_user) {
            echo json_encode(array('status' => 'success',
                                'message' => 'Record successfully removed',
                                'method' => 'DELETE'
          ));
        } else {
            echo json_encode(array('status' => 'failed'));
        }
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
