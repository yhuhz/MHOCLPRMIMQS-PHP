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
    
    public function __construct()
    {
        // Initialize using MysqliDb (from your included file)
        $this->db = new MysqliDb([
            'host' => getenv('DB_HOST'),
            'username' => getenv('DB_USER'), 
            'password' => getenv('DB_PASS'),
            'db' => getenv('DB_NAME'),
            'port' => 3306
        ]);
        
        // Alternative simple check (remove after testing)
        if (!$this->db) {
            die("Database connection failed");
        }
    
  
  }

    public function httpGet($payload)
    {
      //GET HOUSEHOLD
      // $this->db->where('status', 0);

      if (isset($_GET['household_name'])) {
        $this->db->where('household_name', $_GET['household_name'].'%', 'LIKE');
      }

      if (isset($_GET['household_id'])) {
        $this->db->where('household_id', $_GET['household_id']);
      }

      if (isset($_GET['status'])) {
        $this->db->where('status', $_GET['status'], 'IN');
      }

      $households = $this->db->get('tbl_household');

      $household_array = [];

      foreach($households as $household) {
        $this->db->where('household_id', $household['household_id']);
        $household['patient_count'] = $this->db->getValue('tbl_patient_info', 'count(*)');
        array_push($household_array, $household);
      }

      if ($households) {
        echo json_encode(array('status' => 'success',
                                  'data' => $household_array,
                                  'method' => 'GET'
                                ));
      }

    }

    public function httpPost($payload)
    {
        $household = (array) $payload;

        //RESET AUTO INCREMENT
        // $this->db->query("SET  @num := 0");
        // $this->db->query("UPDATE tbl_household SET household_id = @num := (@num+1)");
        // $this->db->query("ALTER TABLE tbl_household AUTO_INCREMENT = 1");
        // return;

        //ADD HOUSEHOLD
        $household['date_added'] = date("Y-m-d");
        $household['household_id'] = $this->db->insert('tbl_household', $household);
        $household['patient_count'] = 0;

        if ($household['household_id']) {
          echo json_encode(array('status' => 'success',
                                    'data' => $household,
                                    'method' => 'POST'
                                  ));
        }

    }

    public function httpPut($payload)
    {
        $payload = (array) $payload;
        // $user_id = $payload['user_id'];

        //EDIT HOUSEHOLD INFO
        $this->db->where('household_id', $payload['household_id']);
        $household = $this->db->update('tbl_household', $payload);

        $this->db->where('household_id', $payload['household_id']);
        $payload['patient_count'] = $this->db->getValue('tbl_patient_info', 'count(*)');

        if ($household) {
          echo json_encode(array('status' => 'success',
                                  'data' => $payload,
                                  'method' => 'PUT'
                                ));
        }


    }

    public function httpDelete()
    {
        $this->db->where('household_id', $_GET['household_id']);
        $payload['status'] = 1;
        $delete_user = $this->db->update('tbl_household', array('status' => 1));

        if ($delete_user) {
            echo json_encode(array('status' => 'success',
                                'message' => 'User successfully removed',
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
