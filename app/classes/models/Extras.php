<?php

if (!function_exists('fileExists')) {
    function fileExists($file="") {
        for ($i=0; $i<10 && !file_exists($file); $i++) {
            $file = "../" . $file;
        } return $file;
    }
}

include_once fileExists('app/config/Database.class.php');

class Extras extends Database {
    protected $db;

    public function __construct() {
        parent::__construct();
        $this->db = Database::connection();
    }

    public static function fileExists($file="") {
        for ($i=0; $i<10 && !file_exists($file); $i++) {
            $file = "../" . $file;
        } return $file;
    }

    public function saveSystemLog($result='') {
        $result = $this->db->real_escape_string($result);
        $sql = "INSERT INTO system_logs (result) VALUES ('$result')"; 
        $this->db->query($sql);
    }
}