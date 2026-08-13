<?php 

namespace Bazarin\Database;

use PDO;
use PDOException;

class QueryBuilder {
    private $conn;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    public function select($table, $columns = '*', $conditions = [], $orderBy = null, $limit = null) {
        $sql = "SELECT $columns FROM $table";
        $params = [];

        if (!empty($conditions)) {
            $conditionStrings = [];
            foreach ($conditions as $col => $val) {
                if (preg_match('/(.*)\s*(>|<|>=|<=|!=|=)\s*/', $col, $matches)) {
                    $col = trim($matches[1]);
                    $operator = trim($matches[2]);
                    $conditionStrings[] = "$col $operator :$col";
                } else {
                    $conditionStrings[] = "$col = :$col";
                }
                $params[$col] = $val;
            }
            $sql .= " WHERE " . implode(' AND ', $conditionStrings);
        }

        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy['column']} " . strtoupper($orderBy['direction']);
        }

        if ($limit) {
            $sql .= " LIMIT $limit";
        }

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $col => $val) {
            $stmt->bindValue(":$col", $val);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert($table, $data) {
        $columns = implode(',', array_keys($data));
        $placeholders = implode(',', array_map(fn($col) => ":$col", array_keys($data)));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";

        $stmt = $this->conn->prepare($sql);
        foreach ($data as $col => $val) {
            $stmt->bindValue(":$col", $val);
        }

        return $stmt->execute() ? $this->conn->lastInsertId() : false;
    }

    public function update($table, $data, $conditions = []) {
        $setClause = implode(', ', array_map(fn($col) => "$col = :$col", array_keys($data)));
        $whereClause = '';
        $params = $data;

        if (!empty($conditions)) {
            $conditionStrings = [];
            foreach ($conditions as $col => $val) {
                $conditionStrings[] = "$col = :cond_$col";
                $params["cond_$col"] = $val;
            }
            $whereClause = " WHERE " . implode(' AND ', $conditionStrings);
        }

        $sql = "UPDATE $table SET $setClause $whereClause";
        $stmt = $this->conn->prepare($sql);

        foreach ($params as $col => $val) {
            $stmt->bindValue(":$col", $val);
        }

        return $stmt->execute() ? $stmt->rowCount() : false;
    }

    public function delete($table, $conditions) {
        $whereClause = implode(' AND ', array_map(fn($col) => "$col = :$col", array_keys($conditions)));
        $sql = "DELETE FROM $table WHERE $whereClause";

        $stmt = $this->conn->prepare($sql);
        foreach ($conditions as $col => $val) {
            $stmt->bindValue(":$col", $val);
        }

        return $stmt->execute() ? $stmt->rowCount() : false;
    }

    public function auth($table, $uname, $password) {
        $user = $this->select($table, '*', ['username' => $uname]);

        if ($user && password_verify($password, $user[0]['password'])) {
            return ['Status' => 'Success', 'Data' => $user, 'Message' => 'Authentication Successful'];
        } else {
            return ['Status' => 'Failed', 'Message' => 'Invalid Credentials'];
        }
    }

    public function randomly($table, $columns = '*', $conditions = [], $limit = null) {
        $sql = "SELECT $columns FROM $table";
        $params = [];

        if (!empty($conditions)) {
            $conditionStrings = [];
            foreach ($conditions as $col => $val) {
                $conditionStrings[] = "$col = :$col";
                $params[$col] = $val;
            }
            $sql .= " WHERE " . implode(' AND ', $conditionStrings);
        }

        $sql .= " ORDER BY RAND()";

        if ($limit) {
            $sql .= " LIMIT $limit";
        }

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $col => $val) {
            $stmt->bindValue(":$col", $val);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
