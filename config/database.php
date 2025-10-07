<?php
/**
 * Database Configuration
 * MDRRMO-GLAN Incident Reporting and Response Coordination System
 */

class Database {
    private $host = 'localhost';
    private $port = '3306';
    private $db_name = 'incident_management';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_TIMEOUT => 5, // 5 second timeout
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch(PDOException $exception) {
            // Don't echo error directly, let calling code handle it
            $this->conn = null;
            error_log("Database connection error: " . $exception->getMessage());
        }
        
        return $this->conn;
    }
}
?>
