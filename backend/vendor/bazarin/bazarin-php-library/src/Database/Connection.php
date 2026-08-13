<?php
namespace Bazarin\Database;

use PDO;
use PDOException;

class Connection {
    private $conn;

    public function __construct($config) {
        try {
            $this->conn = new PDO(
                "mysql:host={$config['host']};dbname={$config['database']}",
                $config['user'],
                $config['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            die("Database Connection Error: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->conn;
    }
}
