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

    public function httpGet($payload)
    {
      if (isset($_GET['name'])) {
        $this->db->where("CONCAT_WS(' ', REPLACE(first_name, ' ', ''), REPLACE(middle_name, ' ', ''), REPLACE(last_name, ' ', ''), REPLACE(suffix, ' ', '')) LIKE '%" . $_GET['name'] . "%'");
        $users = $this->db->get('tbl_users', null, 'CONCAT(first_name, " ", last_name, IFNULL(CONCAT(" ", suffix), "")) AS user_name, user_id as id');


          echo json_encode(array('status' => 'success',
                                'data' => $users,
                                'method' => 'GET'
        ));

      } else if (isset($_GET['id'])) {
          $this->db->where('user_id', $_GET['id']);

          $users = $this->db->get('tbl_users', null, 'CONCAT(first_name, " ", last_name, IFNULL(CONCAT(" ", suffix), "")) AS user_name, user_id as id, department');


            echo json_encode(array('status' => 'success',
                                  'data' => $users,
                                  'method' => 'GET'
          ));
      
      } else if (isset($_GET['username'])) {

        $this->db->where('username', $_GET['username']);
        $user = $this->db->get('tbl_users');

        if ($user === []) {
          echo json_encode(array('status' => 'success',
                                  'message' => 'Username available',
                                  'method' => 'GET'
          ));
        } else {
          if (intval($user[0]['user_id']) === intval($_GET['user_id'])) {
            echo json_encode(array('status' => 'success',
                                  'message' => 'Username available',
                                  'method' => 'GET'
          ));
          } else {
            echo json_encode(array('status' => 'fail',
                                  'message' => 'Username already taken',
                                  'method' => 'GET'
          ));
          }
          
        }

      } else if (isset($_GET['user_id'])) {

        $this->db->where('user_id', $_GET['user_id']);
        $user = $this->db->get('tbl_users');
        unset($user['password']);

        echo json_encode(array('status' => 'success',
                                  'data' => $user,
                                  'method' => 'GET'
          ));
      
      

      } else {
        $payload = (array) json_decode($_GET['payload']);

        if (isset($payload['search_by'])) {
          $search_by = (array) $payload['search_by'];

          if (isset($search_by['search_string']) && $search_by['search_string'] != '') {
            if ($search_by['search_category'] === 'Name') {
              $this->db->where("CONCAT_WS(' ', REPLACE(first_name, ' ', ''), REPLACE(middle_name, ' ', ''), REPLACE(last_name, ' ', ''), REPLACE(suffix, ' ', '')) LIKE '%" . $search_by['search_string'] . "%'");

            } else if ($search_by['search_category'] === 'Username') {
              $this->db->where('username', '%' . $search_by['search_string'] . '%', 'LIKE');

            } else if ($search_by['search_category'] === 'User ID') {
              $this->db->where('user_id',  $search_by['search_string']);

            } else if ($search_by['search_category'] === 'Phone Number') {
              $this->db->where('phone_number', '%' . $search_by['search_string'] . '%', 'LIKE');

            }
          }
        }

        if (isset($payload['filters'])) {
          $filters = (array) $payload['filters'];

          if (isset($filters['department']) && $filters['department'] != []) {
            $this->db->where('department', $filters['department'], 'IN');
          }

          if (isset($filters['permission_level']) && $filters['permission_level'] != []) {
            $this->db->where('permission_level', $filters['permission_level'], 'IN');
          }

          if (isset($filters['status']) && $filters['status'] != []) {
            $this->db->where('status', $filters['status'], 'IN');
          }

          if (isset($filter['date_added']) && $filters['date_added'] != [] && $filters['date_added'][0] != '' && $filters['date_added'][1] != '') {
            $this->db->where('date_added', $filter['date_added'], 'BETWEEN');
          }
        }

        $users = $this->db->get('tbl_users', null, 'user_id, username, first_name, middle_name, last_name, suffix, department, job_title, permission_level, phone_number, sex, birthdate, status');

          echo json_encode(array('status' => 'success',
                                'data' => $users,
                                'method' => 'GET'
        ));

      }

    }

    public function httpPost($payload)
    {
      $payload = (array) $payload;

      //CHECK LOGIN CREDENTIALS
      if (isset($payload['username'])) {

        $this->db->where('username', $payload['username']);
        $attempt = $this->db->get('tbl_users');


        if ($attempt === []) {
          echo json_encode(array('status' => 'fail',
                                  'message' => 'Username not found',
                                  'method' => 'POST'
                                ));
        } else {
          if (password_verify($payload['password'], $attempt[0]['password']) && $attempt[0]['username'] === $payload['username']) {
              unset($attempt[0]['password']);
              echo json_encode(array('status' => 'success',
                                  'data' => $attempt,
                                  'method' => 'POST'
                                ));
          } else {
            echo json_encode(array('status' => 'fail',
                                  'message' => 'Incorrect password!',
                                  'method' => 'POST'
                                ));
          }

        }

      //CREATE NEW USER
      } else {

        //Random Generated Password
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890-@=+_';
        $pass = array(); //remember to declare $pass as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < 10; $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        $password = implode($pass);
        $payload['password'] = password_hash($password, PASSWORD_DEFAULT);

        $payload['date_added'] = date('Y-m-d');

        $this->db->where('dept_id', $payload['department']);
        $dept = $this->db->get('tbl_department', null, 'dept_code');
        $dept = $dept[0]['dept_code'];



        $payload['user_id'] = $this->db->insert('tbl_users', $payload);

        if ($payload['user_id']) {

          //set username
          $username = array('username' => strtolower(str_replace(' ', '', (str_replace(' ', '', $payload['last_name']).substr($payload['first_name'], 0, 1).(isset($payload['middle_name']) ? substr($payload['middle_name'], 0, 1) : null)))) . strtoupper(trim($dept)) . $payload['user_id']);

          //update username based on user_id
          $this->db->where('user_id', $payload['user_id']);
          $update_username = $this->db->update('tbl_users', $username);

          //response message
          $payload['username'] = $username['username'];
          $payload['password'] = $password;
          echo json_encode(array('status' => 'success',
                                'data' => $payload,
                                'method' => 'POST'
                              ));
        } else {
          $this->db->where('user_id', $user_id);
          $this->db->delete('tbl_users');
          echo json_encode(array('status' => 'failed',
                                'method' => 'POST'
                              ));
        }
      }
    }

    public function httpPut($payload)
    {
        $payload = (array) $payload;
        $user_id = $payload['user_id'];

        //FORGET PASSWORD
        if (isset($payload['mode'])) {

          unset($payload['mode']);

          //Random Generated Password
          $pass = []; //remember to declare $pass as an array
          $alphabet_lowercase = 'abcdefghijklmnopqrstuvwxyz';
          $alphabet_uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
          $numbers = '1234567890';
          $special_character = '@!_-';
          
          for($i = 0; $i < 3; $i++) {
            $rand_lower = rand(0, strlen($alphabet_lowercase) - 1);            
            array_push($pass, $alphabet_lowercase[$rand_lower]);
          }

          for($i = 0; $i < 2; $i++) {
            $rand_upper = rand(0, strlen($alphabet_uppercase) - 1);
            array_push($pass, $alphabet_uppercase[$rand_upper]);
          }

          for($i = 0; $i < 2; $i++) {
            $rand_number = rand(0, strlen($numbers) - 1);
            array_push($pass, $numbers[$rand_number]);
          }

          for($i = 0; $i < 1; $i++) {
            $rand_special = rand(0, strlen($special_character) - 1);
            array_push($pass, $special_character[$rand_special]);
          }

          shuffle($pass);
          $password = implode($pass);
          $payload['password'] = password_hash($password, PASSWORD_DEFAULT);

          $this->db->where('user_id', $user_id);
          $update_pass = $this->db->update('tbl_users', $payload);

          if ($update_pass) {
              echo json_encode(array('status' => 'success',
                                      'data' => $password,
                                      'method' => 'PUT'
                      ));
          } else {
            echo json_encode(array('status' => 'failed',
                                    'message' => 'Password reset failed',
                                    'method' => 'PUT'
                      ));
          }

        //CHANGE PASSWORD - CUSTOM
        } else if (isset($payload['new_password'])) {

          $password = array('password' => password_hash($payload['new_password'], PASSWORD_DEFAULT));
          $this->db->where('user_id', $user_id);
          $set_password = $this->db->update('tbl_users', $password);

          if ($set_password) {
            echo json_encode(array('status' => 'success',
                                'message' => 'Password has been changed to ' . $payload['new_password'],
                                'method' => 'PUT'
                              ));
          } else {
            echo json_encode(array('status' => 'fail',
                                'message' => 'Error changing password',
                                'method' => 'PUT'
                              ));
          }

        //EDIT USER ACCOUNT
        } else {
          $this->db->where('user_id', $user_id);
          $update_user = $this->db->update('tbl_users', $payload);

          if ($update_user) {
            echo json_encode(array('status' => 'success',
                                'data' => $payload,
                                'method' => 'PUT'
                              ));
          } else {
            echo json_encode(array('status' => 'failed',
                                'method' => 'PUT'
                              ));
          }

        }

    }

    public function httpDelete($payload)
    {

        $this->db->where('user_id', $_GET['user_id']);
        $payload['status'] = 1;
        $delete_user = $this->db->update('tbl_users', array('status' => 2));

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
