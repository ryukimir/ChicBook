<?php

class Database {

    private static $instance = null;
    private $conn;

    private $host = 'db'; 
    private $db_name = 'chicbook';
    private $username = 'chicuser';
    private $password = 'chicpassword';

    private function __construct() {
        try {
            $this->conn = new PDO(
                "pgsql:host=" . $this->host . ";dbname=" . $this->db_name, 
                $this->username, 
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            error_log("DB connection error: " . $e->getMessage());
            die("Une erreur technique est survenue. Veuillez réessayer plus tard.");
        }
    }

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }
}
?>