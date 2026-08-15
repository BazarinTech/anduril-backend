<?php 

namespace Bazarin\Database;

use PDO;
use PDOException;

class QueryBuilder {
    private $conn;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /**
     * Phase 5.6 -- identifier guard.
     *
     * Table names, column names, ORDER BY targets and LIMIT values are all
     * interpolated straight into the SQL below; PDO placeholders cannot bind
     * an identifier, so they never could be parameterised. That is fine while
     * every caller passes a literal, and a hole the moment one does not --
     * admin/actions/delete_record.php passed a request-supplied table name
     * into DELETE FROM until Phase 1.2 whitelisted it.
     *
     * Rather than trust that no future caller repeats that, anything used as
     * an identifier is validated here: letters, digits and underscores only.
     */
    private static function ident($name, $what = 'identifier') {
        if (!is_string($name) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException("Invalid {$what}: " . var_export($name, true));
        }

        return $name;
    }

    /** Column lists allow a bare * or a comma-separated list of identifiers. */
    private static function columnList($columns) {
        if ($columns === '*' || $columns === null) {
            return '*';
        }

        $parts = array_map('trim', explode(',', (string) $columns));

        return implode(', ', array_map(function ($c) {
            return $c === '*' ? '*' : self::ident($c, 'column');
        }, $parts));
    }

    /** ORDER BY / LIMIT, both interpolated, both validated. */
    private static function orderClause($orderBy) {
        if (!$orderBy || empty($orderBy['column'])) {
            return '';
        }

        $direction = strtoupper($orderBy['direction'] ?? 'ASC');

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException('Invalid sort direction: ' . $direction);
        }

        return ' ORDER BY ' . self::ident($orderBy['column'], 'sort column') . ' ' . $direction;
    }

    private static function limitClause($limit) {
        if ($limit === null || $limit === '' || $limit === false) {
            return '';
        }

        if (!is_numeric($limit) || (int) $limit < 0) {
            throw new \InvalidArgumentException('Invalid limit: ' . var_export($limit, true));
        }

        return ' LIMIT ' . (int) $limit;
    }

    public function select($table, $columns = '*', $conditions = [], $orderBy = null, $limit = null) {
        $sql = "SELECT " . self::columnList($columns) . " FROM " . self::ident($table, 'table');
        $params = [];

        if (!empty($conditions)) {
            $conditionStrings = [];
            foreach ($conditions as $col => $val) {
                if (preg_match('/(.*)\s*(>|<|>=|<=|!=|=)\s*/', $col, $matches)) {
                    $col = trim($matches[1]);
                    $operator = trim($matches[2]);
                    $conditionStrings[] = self::ident($col, 'column') . " $operator :$col";
                } else {
                    $conditionStrings[] = self::ident($col, 'column') . " = :$col";
                }
                $params[$col] = $val;
            }
            $sql .= " WHERE " . implode(' AND ', $conditionStrings);
        }

        $sql .= self::orderClause($orderBy);
        $sql .= self::limitClause($limit);

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $col => $val) {
            $stmt->bindValue(":$col", $val);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function selectOR($table, $columns = '*', $conditions = [], $orderBy = null, $limit = null) {
        $sql = "SELECT " . self::columnList($columns) . " FROM " . self::ident($table, 'table');
        $params = [];

        if (!empty($conditions)) {
            $conditionStrings = [];
            foreach ($conditions as $col => $val) {
                if (preg_match('/(.*)\s*(>|<|>=|<=|!=|=)\s*/', $col, $matches)) {
                    $col = trim($matches[1]);
                    $operator = trim($matches[2]);
                    $conditionStrings[] = self::ident($col, 'column') . " $operator :$col";
                } else {
                    $conditionStrings[] = self::ident($col, 'column') . " = :$col";
                }
                $params[$col] = $val;
            }
            $sql .= " WHERE " . implode(' OR ', $conditionStrings);
        }

        $sql .= self::orderClause($orderBy);
        $sql .= self::limitClause($limit);

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $col => $val) {
            $stmt->bindValue(":$col", $val);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert($table, $data) {
        $columns = implode(',', array_map(fn($col) => self::ident($col, 'column'), array_keys($data)));
        $placeholders = implode(',', array_map(fn($col) => ":$col", array_keys($data)));
        $sql = "INSERT INTO " . self::ident($table, 'table') . " ($columns) VALUES ($placeholders)";

        $stmt = $this->conn->prepare($sql);
        foreach ($data as $col => $val) {
            $stmt->bindValue(":$col", $val);
        }

        return $stmt->execute() ? $this->conn->lastInsertId() : false;
    }

    public function update($table, $data, $conditions = []) {
        $setClause = implode(', ', array_map(fn($col) => self::ident($col, 'column') . " = :$col", array_keys($data)));
        $whereClause = '';
        $params = $data;

        if (!empty($conditions)) {
            $conditionStrings = [];
            foreach ($conditions as $col => $val) {
                $conditionStrings[] = self::ident($col, 'column') . " = :cond_$col";
                $params["cond_$col"] = $val;
            }
            $whereClause = " WHERE " . implode(' AND ', $conditionStrings);
        }

        $sql = "UPDATE " . self::ident($table, 'table') . " SET $setClause $whereClause";
        $stmt = $this->conn->prepare($sql);

        foreach ($params as $col => $val) {
            $stmt->bindValue(":$col", $val);
        }

        return $stmt->execute() ? $stmt->rowCount() : false;
    }

    public function delete($table, $conditions) {
        $whereClause = implode(' AND ', array_map(fn($col) => self::ident($col, 'column') . " = :$col", array_keys($conditions)));
        $sql = "DELETE FROM " . self::ident($table, 'table') . " WHERE $whereClause";

        $stmt = $this->conn->prepare($sql);
        foreach ($conditions as $col => $val) {
            $stmt->bindValue(":$col", $val);
        }

        return $stmt->execute() ? $stmt->rowCount() : false;
    }

    public function auth($table, $uname, $password) {
        $user = $this->selectOR($table, '*', ['phone' => $uname]);
        $user_count = count($user);

        if ($user_count > 0) {
            if ($user[0]['passwrd'] == $password) {
                return ['Status' => 'Success', 'Data' => $user[0], 'Message' => 'Authentication Successful. Please wait for redirection'];
            } else {
                return ['Status' => 'Failed', 'Message' => 'Invalid Credentials'];
            }
        }else{
            return ['Status' => 'Failed', 'Message' => 'Invalid Credentials'];
        }
       
    }

    public function randomly($table, $columns = '*', $conditions = [], $limit = null) {
        $sql = "SELECT " . self::columnList($columns) . " FROM " . self::ident($table, 'table');
        $params = [];

        if (!empty($conditions)) {
            $conditionStrings = [];
            foreach ($conditions as $col => $val) {
                $conditionStrings[] = self::ident($col, 'column') . " = :$col";
                $params[$col] = $val;
            }
            $sql .= " WHERE " . implode(' AND ', $conditionStrings);
        }

        $sql .= " ORDER BY RAND()";
        $sql .= self::limitClause($limit);

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $col => $val) {
            $stmt->bindValue(":$col", $val);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
