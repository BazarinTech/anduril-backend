<?php
namespace Bazarin\Database;

use PDO;
use PDOException;

class Connection {
    private $conn;

    public function __construct($config) {
        /**
         * PATCHED (local): optional port and charset.
         *
         * The DSN was "mysql:host=...;dbname=..." with no port, so PDO always
         * assumed 3306 and any configured port was silently ignored. That is
         * fine against a database on the default port and breaks against a
         * managed one reached through a proxy, which is how most container
         * platforms expose MySQL -- with a connection failure that says
         * nothing about the port being the cause.
         *
         * Charset is pinned for the same reason it is pinned everywhere else:
         * so the connection does not inherit whatever the server defaults to.
         *
         * Keep this file byte-identical to the copy in the other vendor tree.
         */
        $dsn = "mysql:host={$config['host']}";

        if (!empty($config['port'])) {
            $dsn .= ";port={$config['port']}";
        }

        $dsn .= ";dbname={$config['database']}";
        $dsn .= ";charset=" . (empty($config['charset']) ? 'utf8mb4' : $config['charset']);

        try {
            $this->conn = new PDO(
                $dsn,
                $config['user'],
                $config['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            /**
             * The detail goes to the log, never to the response.
             *
             * This used to die() with the driver's own message, which prints
             * the host, port and database name to whoever made the request --
             * and it did so regardless of APP_DEBUG, because it is an explicit
             * die() rather than an uncaught error. The DSN below carries no
             * credentials, so it is safe in the log and it is exactly what you
             * need to diagnose a refused connection.
             */
            error_log("Database connection failed for {$dsn}: " . $e->getMessage());

            if (PHP_SAPI !== 'cli') {
                http_response_code(500);
            }

            die('Service temporarily unavailable.');
        }
    }

    public function getConnection() {
        return $this->conn;
    }
}
