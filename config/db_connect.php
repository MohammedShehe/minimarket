<?php
// config/db_connect.php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

class Database {
    private $host;
    private $username;
    private $password;
    private $dbname;
    private $charset;
    public $conn;

    public function __construct() {
        // Load environment variables securely
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();

        $this->host     = $_ENV['DB_HOST'];
        $this->username = $_ENV['DB_USERNAME'];
        $this->password = $_ENV['DB_PASSWORD'];
        $this->dbname   = $_ENV['DB_NAME'];
        $this->charset  = $_ENV['DB_CHARSET'] ?? 'utf8';
    }

    public function getConnection() {
        $this->conn = new mysqli($this->host, $this->username, $this->password, $this->dbname);

        if ($this->conn->connect_error) {
            die(json_encode([
                "status" => "error",
                "message" => "Database connection failed: " . $this->conn->connect_error
            ]));
        }

        $this->conn->set_charset($this->charset);
        return $this->conn;
    }
}

// For backward compatibility
$database = new Database();
$conn = $database->getConnection();
?>
