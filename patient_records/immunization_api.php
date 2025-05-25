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

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

require_once('../include/MysqliDb.php');
date_default_timezone_set('Asia/Manila');

class API
{
  public function __construct()
  {

    if (!isset($_SERVER['DB_HOST'], $_SERVER['DB_USER'], $_SERVER['DB_PASS'], $_SERVER['DB_NAME'])) {
      die("Error: Missing environment variables!");
    }
      $this->db = new MysqliDB(
        $_SERVER['DB_HOST'], 
        $_SERVER['DB_USER'], 
        $_SERVER['DB_PASS'], 
        $_SERVER['DB_NAME']
      );
  }

    public function httpGet()
    {
      $payload = (array) json_decode($_GET['payload']);

      $this->db->where('immunization_id', $payload['record_id']);
      $this->db->where('status', 0);
      $immunization_records = $this->db->get('tbl_immunization');
      $immunization_records = $immunization_records[0];

      $this->db->where('user_id', $immunization_records['immunizer_id']);
      $name = $this->db->get('tbl_users', null, 'CONCAT(first_name, " ", last_name, IFNULL(CONCAT(" ", suffix), "")) AS name');

        $immunization_records['immunizer_name'] = $name[0]['name'];

      if ($immunization_records) {
        echo json_encode(array('status' => 'success',
                                  'data' => $immunization_records,
                                  'method' => 'GET'
                                ));
      }

    }

    public function httpPost($payload)
    {
        $immunization_record = (array) $payload;

          $immunization_record['immunization_id'] = $this->db->insert('tbl_immunization', $immunization_record);

          if ($immunization_record['immunization_id']) {

            $immunization_record['record_id'] = $immunization_record['immunization_id'];
            unset($immunization_record['immunization_id']);

            $immunization_record['date'] = $immunization_record['immunization_date'];
            unset($immunization_record['immunization_date']);

            echo json_encode(array('status' => 'success',
                                      'data' => $immunization_record,
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

      $this->db->where('immunization_id', $payload['immunization_id']);
      $immunization_record = $this->db->update('tbl_immunization', $payload);

      $this->db->where('user_id', $payload['immunizer_id']);
        $name = $this->db->get('tbl_users', null, 'CONCAT(first_name, " ", last_name, IFNULL(CONCAT(" ", suffix), "")) AS name');
        $payload['immunizer_name'] = $name[0]['name'];

      if ($immunization_record) {
        echo json_encode(array('status' => 'success',
                              'data' => $payload,
                              'method' => 'PUT'
                            ));
      }

    }

    public function httpDelete($payload)
    {

      $this->db->where('immunization_id', $_GET['record_id']);
      $this->db->update('tbl_immunization', array('status' => 1));

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
