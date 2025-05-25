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

      if (isset ($_GET['dbList'])) {
        $folderPath = '../sql';

        //test
        $file_list = [];
        if (is_dir($folderPath)) {
            if ($handle = opendir($folderPath)) {
                while (($file = readdir($handle)) !== false) {
                    if ($file != "." && $file != "..") {
                        // echo $file . "<br>";
                        array_push($file_list, $file);
                    }
                }
                closedir($handle);
            }
        }

        $file_list = array_reverse($file_list);
        echo json_encode(array('status' => 'success',
                                    'data' => $file_list,
                                    'method' => 'GET'
                                  ));

      } else {

        $connection = mysqli_connect('localhost','root','','mhoclprmimqs');

        $tables = array();
        $result = mysqli_query($connection,"SHOW TABLES");
        while($row = mysqli_fetch_row($result)){
          $tables[] = $row[0];
        }

        $return = 'SET foreign_key_checks = 0;';
        foreach($tables as $table){
          $result = mysqli_query($connection,"SELECT * FROM ".$table);
          $num_fields = mysqli_num_fields($result);

          // $return .= 'DROP TABLE '.$table.';';
          $row2 = mysqli_fetch_row(mysqli_query($connection,"SHOW CREATE TABLE ".$table));
          $return .= "\n\n".$row2[1].";\n\n";

          for($i=0;$i<$num_fields;$i++){
            while($row = mysqli_fetch_row($result)){
              $return .= "INSERT INTO ".$table." VALUES(";
              for($j=0;$j<$num_fields;$j++){
                $row[$j] = addslashes($row[$j]);
                if(isset($row[$j])){ $return .= '"'.$row[$j].'"';}
                else{ $return .= '""';}
                if($j<$num_fields-1){ $return .= ',';}
              }
              $return .= ");\n";
            }
          }
          $return .= "";
        }

        // Get the name of the database
        $databaseName = 'mhoclprmimqs';

        // Create a backup filename based on the current date and time
        $backupFilename = 'mhoclprmimqs_backup' . '_' . date('Y-m-d-H-i') . '.sql';

        // Create the full path to the backup file in the specified folder
        $backupFilePath = '../sql' . '/' . $backupFilename;

        //save file
        $handle = fopen($backupFilePath,"w+");
        fwrite($handle,$return);
        fclose($handle);

        echo json_encode(array('status' => 'success',
                                    'data' => 'Successfully Backed Up',
                                    'method' => 'GET'
                                  ));

        }

    }

    public function httpPost($payload)
    {
      $payload = (array) $payload;

      $connection = new mysqli('localhost', 'root', '');
      $drop_prev_db = "DROP DATABASE IF EXISTS mhoclprmimqs";
      $connection->query($drop_prev_db);

      $create_db = "CREATE DATABASE if not exists mhoclprmimqs";
      $connection->query($create_db);

      $connection->select_db('mhoclprmimqs');

      $filename = '../sql/' . $payload['db'];
      $handle = fopen($filename,"r+");
      $contents = fread($handle,filesize($filename));
      $sql = explode(';',$contents);
      array_pop($sql); // remove last element


      foreach($sql as $query){
          if (isset($query) && !empty($query) && $query !== ';' && $query !== '' && $query !== ' ') {


            $result = mysqli_query($connection,$query);
          }
        }


      fclose($handle);
      // echo 'Successfully imported';

      echo json_encode(array('status' => 'success',
                                  'data' => 'Successfully Restored',
                                  'method' => 'GET'
                                ));
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