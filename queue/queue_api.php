<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

require __DIR__ . '/../vendor/autoload.php';



require_once('../include/MysqliDb.php');
date_default_timezone_set('Asia/Manila');

class API
{
  public function __construct()
  {

    // Check both $_SERVER and getenv() for variables
    $dbHost = $_SERVER['DB_HOST'] ?? getenv('DB_HOST');
    $dbUser = $_SERVER['DB_USER'] ?? getenv('DB_USER');
    $dbPass = $_SERVER['DB_PASS'] ?? getenv('DB_PASS');
    $dbName = $_SERVER['DB_NAME'] ?? getenv('DB_NAME');

    if (empty($dbHost) || empty($dbUser) || empty($dbName)) {
      die("Error: Missing database configuration!");
    }

    $this->db = new MysqliDB($dbHost, $dbUser, $dbPass, $dbName);
  
  }

    public function httpGet($payload)
    {
      if (isset($_GET['department'])) {
        // print_r($_GET['department']); return;

        $this->db->join('tbl_patient_info p', 'p.patient_id=q.patient_id', 'LEFT');

        $this->db->where('department', $_GET['department']);
        $queue = $this->db->get('tbl_queue q', null, 'queue_id, queue_number, q.patient_id, q.department, q.is_current, q.is_priority, p.first_name, p.middle_name, p.last_name, p.suffix');

          echo json_encode(array('status' => 'success',
                                    'data' => $queue,
                                    'method' => 'GET'
                                  ));

      } else if (isset($_GET['priority'])) {

        if (isset($_GET['department_specific'])) {
          $this->db->where('department', $_GET['department_specific']);
        }

        $this->db->where('is_priority', $_GET['priority']);
        $this->db->orderBy('queue_id', 'DESC');
        $queue = $this->db->get('tbl_queue', null, 'queue_number');

        if ($queue != []) {
          $queue = $queue[0]['queue_number'];
          if(substr($queue, 0, 1) === 'P') {
            $queue = trim($queue, 'Priority ');
            $queue = intVal($queue) + 1;
          } else {
            $queue = intVal($queue) + 1;
          }

        } else {
          $queue = 1;
        }

          echo json_encode(array('status' => 'success',
                                    'data' => $queue,
                                    'method' => 'GET'
                                  ));

      } else if (isset($_GET['patient_id'])) {

        $this->db->where('patient_id', $_GET['patient_id']);
        $check_queue = $this->db->get('tbl_queue');

        if ($check_queue === []) {

          $listCheckup = [];

          $this->db->where('patient_id', $_GET['patient_id']);
          $this->db->where('checkup_date', date("Y-m-d"));
          $check_opd = $this->db->get('tbl_opd');
          if ($check_opd === []) {
            array_push($listCheckup, 'OPD');
          }

          $this->db->where('patient_id', $_GET['patient_id']);
          $this->db->where('checkup_date', date("Y-m-d"));
          $check_dental = $this->db->get('tbl_dental');
          if ($check_dental === []) {
            array_push($listCheckup, 'Dental');
          }

          $this->db->where('patient_id', $_GET['patient_id']);
          $this->db->where('immunization_date', date("Y-m-d"));
          $check_immunization = $this->db->get('tbl_immunization');
          if ($check_immunization === []) {
            array_push($listCheckup, 'Immunization');
          }

          if ($_GET['sex']) {

            $this->db->where('patient_id', $_GET['patient_id']);
            $this->db->orderBy('date_added', 'DESC');
            $prenatal = $this->db->get('tbl_prenatal');
            if ($prenatal !== []) {
              $this->db->where('prenatal_id', $prenatal[0]['prenatal_id']);
              $this->db->where('checkup_date', date("Y-m-d"));
              $check_prenatal = $this->db->get('tbl_prenatal_checkup');
              if ($check_prenatal === []) {
                array_push($listCheckup, 'Prenatal');
              }
            } else {
              array_push($listCheckup, 'Prenatal');
            }

            
          }
          // echo json_encode(array('status' => 'success',
          //                           'message' => 'Patient not on queue yet',
          //                           'method' => 'GET'
          //                         ));

          echo json_encode(array('status' => 'success',
                                    'data' => $listCheckup,
                                    'method' => 'GET'
                                  ));
        } else {
          echo json_encode(array('status' => 'fail',
                                    'message' => 'Patient is on queue',
                                    'method' => 'GET'
                                  ));
        }

      } else {
        $this->db->where('department', 5);
        $this->db->orderBy('queue_number', 'asc');
        $queue['Front_Desk'] = $this->db->get('tbl_queue');

        $this->db->where('department', 1);
        $this->db->orderBy('queue_number', 'asc');
        $queue['OPD'] = $this->db->get('tbl_queue');

        $this->db->where('department', 2);
        $this->db->orderBy('queue_number', 'asc');
        $queue['Dental'] = $this->db->get('tbl_queue');

        $this->db->where('department', 3);
        $this->db->orderBy('queue_number', 'asc');
        $queue['Prenatal'] = $this->db->get('tbl_queue');

        $this->db->where('department', 7);
        $this->db->orderBy('queue_number', 'asc');
        $queue['Immunization'] = $this->db->get('tbl_queue');

        if ($queue) {
          echo json_encode(array('status' => 'success',
                                    'data' => $queue,
                                    'method' => 'GET'
                                  ));
        }
      }


    }

    public function httpPost($payload)
    {
        $payload = (array) $payload;

        //RESET AUTO INCREMENT
        // $this->db->query("SET  @num := 0");
        // $this->db->query("UPDATE tbl_household SET household_id = @num := (@num+1)");
        // $this->db->query("ALTER TABLE tbl_household AUTO_INCREMENT = 1");
        // return;

        //CHECK IF PATIENT ALREADY ON QUEUE FOR SAME DEPT

        // $this->db->where('department', $payload['department']);
        $this->db->where('patient_id', $payload['patient_id']);
        $check_queue = $this->db->get('tbl_queue');
        // print_r($check_queue); return;

        if ($check_queue === []) {

          //CHECK IF NO OTHER PERSON IN THE QUEUE
          $this->db->where('department', $payload['department']);
          $current_queue = $this->db->get('tbl_queue');

          //ADD TO QUEUE
          if ($payload['is_priority'] === 1) {
            $payload['queue_number'] = 'Priority ' . $payload['queue_number'];
          }

          $this->db->insert('tbl_queue', $payload);


            echo json_encode(array('status' => 'success',
                                      'data' => $payload,
                                      'method' => 'POST'
                                    ));
        } else {
          echo json_encode(array('status' => 'fail',
                                    'data' => 'Patient already on queue',
                                    'method' => 'POST'
                                  ));
        }



    }

    public function httpPut($payload)
    {
        $payload = (array) $payload;

        if (isset($payload['done'])) {
          if ($payload['department'] === 5) {
            
            if ($payload['priority'] === 0) {
              $this->db->where('department', 1);
            }
            
            $this->db->where('is_priority', $payload['priority']);
            $this->db->orderBy('queue_id', 'DESC');
            $queue = $this->db->get('tbl_queue', null, 'queue_number');

            if ($queue != []) {
              $queue = $queue[0]['queue_number'];
              if(substr($queue, 0, 1) === 'P') {
                $queue = trim($queue, 'Priority ');
                $queue = intVal($queue) + 1;
              } else {
                $queue = intVal($queue) + 1;
              }

            } else {
              $queue = 1;
            }

            if ($payload['priority'] === 1) {
              $queue = 'Priority ' . $queue;
            }

            $this->db->where('queue_id', $payload['current_patient']);
            $this->db->update('tbl_queue', array('department' => 1, 'queue_number' => $queue, 'is_current' => 0));

          } else {
            $this->db->where('queue_id', $payload['current_patient']);
            $this->db->delete('tbl_queue');
          }
        } else {
          if ($payload['department'] === 5) {
            
            // if ($payload['priority'] === 0) {
            //   $this->db->where('department', 1);
            // }
            
            // $this->db->where('is_priority', $payload['priority']);
            // $this->db->orderBy('queue_id', 'DESC');
            // $queue = $this->db->get('tbl_queue', null, 'queue_number');

            // if ($queue != []) {
            //   $queue = $queue[0]['queue_number'];
            //   if(substr($queue, 0, 1) === 'P') {
            //     $queue = trim($queue, 'Priority ');
            //     $queue = intVal($queue) + 1;
            //   } else {
            //     $queue = intVal($queue) + 1;
            //   }

            // } else {
            //   $queue = 1;
            // }

            // if ($payload['priority'] === 1) {
            //   $queue = 'Priority ' . $queue;
            // }

            $this->db->where('queue_id', $payload['current_patient']);
            $this->db->update('tbl_queue', array('department' => 1, 'is_current' => 0));

            $this->db->where('queue_id', $payload['next_patient']);
            $this->db->update('tbl_queue', array('is_current' => 1));
          } else {
            $this->db->where('queue_id', $payload['next_patient']);
            $this->db->update('tbl_queue', array('is_current' => 1));
  
            if ($payload['current_patient'] !== null) {
              $this->db->where('queue_id', $payload['current_patient']);
              $this->db->delete('tbl_queue');
            }
          }
        }

          echo json_encode(array('status' => 'success',
                                  'data' => $payload,
                                  'method' => 'PUT'
                                ));


    }

    public function httpDelete()
    {
      if (isset($_GET['queue_id'])) {
        $this->db->where('queue_id', $_GET['queue_id']);
      }

      if (isset($_GET['department'])) {
        $this->db->where('department', $_GET['department']);
      }

      $this->db->delete('tbl_queue');

      echo json_encode(array('status' => 'success',
                            'message' => 'Patient successfully removed from queue',
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
