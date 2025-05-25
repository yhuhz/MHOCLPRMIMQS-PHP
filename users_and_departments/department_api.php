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

$dotenv = Dotenv\Dotenv::createImmutable('/var/www/html/');
$dotenv->load();

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
      //GET DEPARTMENTS
        // $this->db->where('status', 0);
        $dept = $this->db->get('tbl_department');

        echo json_encode(array('status' => 'success',
                                  'data' => $dept,
                                  'method' => 'GET'
                                ));
    }

    public function httpPost($payload)
    {

      $payload = (array) $payload;

      //ADD DEPARTMENTS
        $add_department['dept_id'] = $this->db->insert('tbl_department', $payload);


        if ($add_department['dept_id']) {
          echo json_encode(array('status' => 'success',
                                  'data' => $add_department,
                                  'method' => 'POST'
                                ));
        } else {
          echo json_encode(array('status' => 'fail',
                                  'message' => 'Failed to add department',
                                  'method' => 'POST'
                                ));
        }
    }

    public function httpPut($payload)
    {
        $payload = (array) $payload;

        //EDIT DEPARTMENT
          $this->db->where('dept_id', $payload['dept_id']);
          $update_department = $this->db->update('tbl_department', $payload);

          if ($update_department) {
            echo json_encode(array('status' => 'success',
                                'data' => $payload,
                                'method' => 'PUT'
                              ));
          } else {
            echo json_encode(array('status' => 'fail',
                                  'message' => 'Failed to update department',
                                  'method' => 'PUT'
                              ));
          }

    }

    public function httpDelete($payload)
    {
        $payload = (array) $payload;

        //DELETE DEPARTMENT
        $this->db->where('dept_id', $payload['dept_id']);
        $payload['status'] = 1;
        $delete_user = $this->db->update('tbl_department', $payload);

        if ($delete_user) {
            echo json_encode(array('status' => 'success',
                                'message' => 'Department successfully removed',
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
