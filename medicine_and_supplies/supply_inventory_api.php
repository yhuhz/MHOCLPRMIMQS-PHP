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
  protected $db;

public function __construct()
  {

   $dbHost = $_SERVER['DB_HOST'] ?? getenv('DB_HOST');
        $dbUser = $_SERVER['DB_USER'] ?? getenv('DB_USER');
        $dbPass = $_SERVER['DB_PASS'] ?? getenv('DB_PASS');
        $dbName = $_SERVER['DB_NAME'] ?? getenv('DB_NAME');

        if (empty($dbHost) || empty($dbUser) || empty($dbName)) {
            error_log("DB Config Missing: Host:{$dbHost} User:{$dbUser} DB:{$dbName}");
            throw new Exception("Database configuration incomplete");
        }

        try {
            $this->db = new MysqliDB($dbHost, $dbUser, $dbPass, $dbName);
            $this->db->set_charset('utf8mb4');
        } catch (Exception $e) {
            error_log("DB Connection Failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
  
  }

    public function httpGet($payload)
    {
      if (isset($_GET['supply_id'])) {
        //GET SUPPLY DETAILS

        $this->db->where('supply_id', $_GET['supply_id']);
        $supply_details = $this->db->get('tbl_supplies_inventory');
        $supply_details = $supply_details[0];

        $this->db->where('supply_id', $_GET['supply_id']);
        $this->db->where('status', 0);
        $supply_release = $this->db->getValue('tbl_supply_release', 'CAST(SUM(quantity) as int)');
        $supply_details['quantity_released'] = $supply_release != null ? $supply_release : 0;

        echo json_encode(array('status' => 'success',
                                  'data' => $supply_details,
                                  'method' => 'GET'
                                ));

       //GET ALL SUPPLY RELEASE
      } else if (isset($_GET['date'])) {

        $date_array = [];

        
          if ($_GET['date'] === 'Today') {
            $date_array = [date("Y-m-d"), date("Y-m-d")];
  
          } else if ($_GET['date']  === 'This Week') {
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
  
          } else if ($_GET['date'] === 'This Month') {
              $date_array = [date('Y-m-01'), date('Y-m-t')];
          } else if ($_GET['date'] === 'This Year') {
              $date_array = [date('Y-01-01'), date('Y-12-31')];
          } else {
              $date_array = $_GET['date'];
          }
        

        $this->db->join('tbl_users u', 'u.user_id=sr.user_id', 'LEFT');
        $this->db->where('release_date', $date_array, 'BETWEEN');
        $this->db->groupBy('u.first_name');
        $this->db->orderBy('u.first_name', 'ASC');
        $users = $this->db->get('tbl_supply_release sr', null, 'sr.user_id, sr.department, u.first_name, u.last_name, u.middle_name, u.suffix');

        $supply_release = [];

        foreach ($users as $user) {
          $this->db->join('tbl_supplies_inventory si', 'si.supply_id=sr.supply_id', 'LEFT');
          $this->db->where('user_id', $user['user_id']);
          $this->db->where('release_date', $date_array, 'BETWEEN');
          $this->db->where('sr.status', 0);
          $supplies = $this->db->get('tbl_supply_release sr', null, 'sr.supply_id, si.supply_name, sr.quantity, sr.release_date, sr.released_by, sr.status');

          $supplies_array = [];

          if ($supplies !== []) {
            foreach ($supplies as $supply) {
              $supply['supply_details'] = array("supply_id" => $supply['supply_id'], "supply_name" => $supply['supply_name']);
              unset($supply['supply_id']);
              unset($supply['supply_name']);
              array_push($supplies_array, $supply);
            }
          }
          
          $user['supplies'] = $supplies_array;

          if ($user['supplies'] !== []) {
            array_push($supply_release, $user);
          }
          
        }

        echo json_encode(array('status' => 'success',
                                    'data' => $supply_release,
                                    'method' => 'GET'
                                  ));
      
       //FIND SUPPLY FOR DROPDOWN
      } else if (isset($_GET['supply_name'])) {

        $this->db->where('supply_name', "%" . $_GET['supply_name'] . "%", "LIKE");
        $this->db->where('status', 0);

        $this->db->groupBy("supply_name");
        $supplies = $this->db->get('tbl_supplies_inventory');

        if ($supplies === []) {
          $this->db->where('supply_type', "%" . $_GET['supply_name'] . "%", "LIKE");
          $this->db->where('status', 0);
          
          $this->db->groupBy("supply_type");
          $supplies = $this->db->get('tbl_supplies_inventory');
        }

          if ($supplies !== []) {
            $supply_array = [];

            foreach($supplies as $supply) {
              $this->db->where('supply_id', $supply['supply_id']);
              $this->db->where('status', 0);
              $supply['quantity_released'] = $this->db->getValue('tbl_supply_release', 'CAST(SUM(quantity) as int)');

              array_push($supply_array, $supply);
            }

            echo json_encode(array('status' => 'success',
                                    'data' => $supply_array,
                                    'method' => 'GET'
                                  ));
          } else {
            echo json_encode(array('status' => 'success',
                                  'data' => $supplies,
                                  'method' => 'GET'
                                ));
          }
        


      } else if (isset($_GET['release_filter'])) {
        $release_filter = (array) json_decode($_GET['release_filter']);

        if (isset($release_filter['department'])) {
          $this->db->where('department', $release_filter['department'], 'IN');
        }

        if (isset($release_filter['status'])) {
          $this->db->where('status', $release_filter['status'], 'IN');
        }

        if (isset($release_filter['quantity_released']) && ($release_filter['quantity_released'][0] != '') && ($release_filter['quantity_released'][1] != '')) {
          $this->db->where('quantity', $release_filter['quantity_released'], 'BETWEEN');
        }

        if (isset($release_filter['date_released']) && ($release_filter['date_released'][0] != '') && ($release_filter['quantity_released'][1] != '')) {
          $this->db->where('release_date', $release_filter['date_released'], 'BETWEEN');
        }

        $this->db->where('supply_id', $release_filter['supply_id']);

        $supply_release = $this->db->get('tbl_supply_release');
        $supply_release_array = [];

        foreach($supply_release as $release) {

            $this->db->where('user_id', $release['user_id']);
            $name = $this->db->get('tbl_users', null, 'concat(first_name, " ", last_name, IFNULL(CONCAT(" ", suffix), "")) as name');

            $release['user_name'] = $name[0]['name'];
            array_push($supply_release_array, $release);
          }



        echo json_encode(array('status' => 'success',
                                  'data' => $supply_release_array,
                                  'method' => 'GET'
                                ));

      } else {
        //GET SUPPLY INVENTORY
      $payload = (array) json_decode($_GET['payload']);

      //check if there are parameters
      if (isset($payload['search_by'])) {
        $search_by = (array) $payload['search_by'];

        if (isset($search_by['search_string']) && ($search_by['search_string'] != '')) {
          if ($search_by['search_category'] === "Supply Name") {
            $this->db->where('supply_name', '%'.$search_by['search_string'].'%', 'LIKE');

          } else if ($search_by['search_category'] === "Supply ID") {
            $this->db->where('supply_id', $search_by['search_string']);

          } else if ($search_by['search_category'] === "Supply Type") {
            $this->db->where('supply_type', '%'.$search_by['search_string'].'%', 'LIKE');

          } else if ($search_by['search_category'] === "Quantity Type") {
            $this->db->where('quantity_type', '%'.$search_by['search_string'].'%', 'LIKE');

          } else if ($search_by['search_category'] === "Procured By") {
            $this->db->where('procured_by', '%'.$search_by['search_string'].'%', 'LIKE');
          }
        }
      }

      //FILTER

      if (isset($payload['filter'])) {
        $filter = (array) $payload['filter'];

        //Status filter
        if (isset($filter['status'])) {
          $this->db->where('status', $filter['status'], 'IN');
        }

        //Date Added filter
        if (isset($filter['date_added'][0]) && isset($filter['date_added'][1])) {
          $this->db->where('date_added', $filter['date_added'], 'BETWEEN');
        }

        //Manufacturing Date filter
        if (isset($filter['mfg_date'][0]) && isset($filter['mfg_date'][1])) {
          $this->db->where('mfg_date', $filter['mfg_date'], 'BETWEEN');
        }

        // //Expiry Date filter
        if (isset($filter['exp_date'][0]) && isset($filter['exp_date'][1])) {
          $this->db->where('exp_date', $filter['exp_date'], 'BETWEEN');
        }
      }

      $supply_inventory = $this->db->get('tbl_supplies_inventory');
      $supply_array = [];

      foreach($supply_inventory as $supply) {
        $this->db->where('supply_id', $supply['supply_id']);
        $this->db->where('status', 0);
        $count = $this->db->getValue('tbl_supply_release', 'CAST(SUM(quantity) as int)');
        $supply['quantity_released'] = $count;

        if (isset($filter['in_stock']) && ($filter['in_stock'][0] != '') && ($filter['in_stock'][1] != '')) {
          if ($supply['quantity'] - $supply['quantity_released'] >= $filter['in_stock'][0] && $supply['quantity'] - $supply['quantity_released'] <= $filter['in_stock'][1]) {
            array_push($supply_array, $supply);
          }
        } else {
          array_push($supply_array, $supply);
        }
      }


        echo json_encode(array('status' => 'success',
                                  'data' => $supply_array,
                                  'method' => 'GET'
                                ));
      }

    }

    public function httpPost($payload)
    {
      $payload = (array) $payload;

      if (isset($payload['department'])) {
        if (isset($payload['supplies_array'])) {
          // print_r($payload); return;

          //ADD SUPPLY RELEASE RECORD
          $supplies = [];
          foreach($payload['supplies_array'] as $supply) {
            $supply = (array) $supply;
            $supply['release_date'] = date("Y-m-d");
            $supply['released_by'] = $payload['released_by'];
            $supply['user_id'] = $payload['user_id'];
            $supply['department'] = $payload['department'];
            $supply['supply_release_id'] = $this->db->insert('tbl_supply_release', $supply);

            array_push($supplies, $supply);
          }
          

          echo json_encode(array('status' => 'success',
                                      'data' => $supplies,
                                      'method' => 'POST'
                                    ));

        } else {
          //ADD SUPPLY RELEASE RECORD
          $payload['supply_release_id'] = $this->db->insert('tbl_supply_release', $payload);

          if ($payload['supply_release_id']) {


              $this->db->where('user_id', $payload['user_id']);
              $name = $this->db->get('tbl_users', null, 'concat(first_name, " ", last_name, IFNULL(CONCAT(" ", suffix), "")) as name');

              $payload['user_name'] = $name[0]['name'];


            echo json_encode(array('status' => 'success',
                                      'data' => $payload,
                                      'method' => 'POST'
                                    ));
          }
        }
        

      } else {
        //ADD SUPPLY RECORD
        $payload['supply_id'] = $this->db->insert('tbl_supplies_inventory', $payload);
        $payload['in_stock'] = $payload['quantity'];

        if ($payload['supply_id']) {
          echo json_encode(array('status' => 'success',
                                    'data' => $payload,
                                    'method' => 'POST'
                                  ));
        }
      }



    }

    public function httpPut($payload)
    {
      $payload = (array) $payload;

      if (isset($payload['supplies'])) {
        // print_r($payload);

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

        $this->db->where('user_id', $payload['user_id']);
        $this->db->where('release_date', $date_array, 'BETWEEN');
        $this->db->delete('tbl_supply_release');

        $supply_array = [];

        foreach ($payload['supplies'] as $supply) {
          $supply = (array) $supply;
          $supply['user_id'] = $payload['user_id'];
          $supply_details = (array) $supply['supply_details'];
          $supply['supply_id'] = $supply_details['supply_id'];
          unset($supply['supply_details']);
          $supply['department'] = $payload['department'];
          
          $this->db->insert('tbl_supply_release', $supply);
          unset($supply['supply_id']);
          $supply['supply_details'] = $supply_details;

          array_push($supply_array, $supply);
        }

        $payload['supplies'] = $supply_array;

        echo json_encode(array('status' => 'success',
                                  'data' => $payload,
                                  'method' => 'PUT'
                                ));

      } else if(isset($payload['department'])) {
        //EDIT SUPPLY RELEASE RECORD
        $this->db->where('supply_release_id', $payload['supply_release_id']);
        $supply_release = $this->db->update('tbl_supply_release', $payload);

        if ($supply_release) {

          $this->db->where('user_id', $payload['user_id']);
          $name = $this->db->get('tbl_users', null, 'concat(first_name, " ", last_name, IFNULL(CONCAT(" ", suffix), "")) as name');

          $payload['user_name'] = $name[0]['name'];


          echo json_encode(array('status' => 'success',
                                  'data' => $payload,
                                  'method' => 'PUT'
                                ));
        }

      } else {
        //EDIT SUPPLY RECORD
        $this->db->where('supply_id', $payload['supply_id']);
        $household = $this->db->update('tbl_supplies_inventory', $payload);

        if ($household) {

          $this->db->where('supply_id', $payload['supply_id']);
          $count = $this->db->getValue('tbl_supply_release', 'CAST(SUM(quantity) as int)');
          $payload['quantity_released'] = $count;

          echo json_encode(array('status' => 'success',
                                  'data' => $payload,
                                  'method' => 'PUT'
                                ));
        }
      }




    }

    public function httpDelete($payload)
    {
      if (isset($_GET['supply_id'])) {
        $this->db->where('supply_id', $_GET['supply_id']);
        $delete_supply = $this->db->update('tbl_supplies_inventory', array('status' => 1));

        if ($delete_supply) {
            echo json_encode(array('status' => 'success',
                                'message' => 'Supply record successfully removed',
                                'method' => 'DELETE'
          ));
        } else {
            echo json_encode(array('status' => 'failed'));
        }
      } else if (isset($_GET['supply_release_id'])) {
        $this->db->where('supply_release_id', $_GET['supply_release_id']);
        $delete_supply = $this->db->update('tbl_supply_release', array('status' => 1));

        if ($delete_supply) {
            echo json_encode(array('status' => 'success',
                                'message' => 'Medicine release record successfully removed',
                                'method' => 'DELETE'
          ));
        } else {
            echo json_encode(array('status' => 'failed'));
        }
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
