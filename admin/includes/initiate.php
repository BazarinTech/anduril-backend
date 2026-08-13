<?php
require_once ('./vendor/autoload.php');

use Bazarin\Database\Connection;
use Bazarin\Database\QueryBuilder;
use Bazarin\Helpers\DateHelper;
use Bazarin\Helpers\FileHelper;
use Bazarin\APIS\Curl;
use Bazarin\APIS\FileGetContent;
use Bazarin\Security\Cryptions;

/**
 * ===============================
 *  BAZARIN PHP LIBRARY SETUP FILE
 * ===============================
 * This file is used to initialize and work with the Bazarin PHP Library.
 * Make sure to follow the structure below when using the library.
 */

/** 
 * ===========================
 * DATABASE INITIALIZATION
 * ===========================
 */
$db = new Connection([
    'host' => 'localhost',
    'user' => 'xgrammco_xgrammco',
    'password' => ')nnFINZj5c1:65',
    'database' => 'xgrammco_sanders'
]);

$query = new QueryBuilder($db->getConnection());

/**
 * ===========================
 * DATABASE-BACKED SESSION HANDLER
 * ===========================
 */
class DbSessionHandler implements SessionHandlerInterface {
    private $pdo;

    public function __construct($pdo) { 
        $this->pdo = $pdo; 
    }

    public function open($savePath, $sessionName) { return true; }
    public function close() { return true; }

    public function read($id) {
        $stmt = $this->pdo->prepare("SELECT data FROM sessions WHERE id=:id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetchColumn() ?: '';
    }

    public function write($id, $data) {
        $stmt = $this->pdo->prepare(
            "REPLACE INTO sessions (id, data, timestamp) VALUES (:id, :data, :ts)"
        );
        return $stmt->execute([':id'=>$id, ':data'=>$data, ':ts'=>time()]);
    }

    public function destroy($id) {
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE id=:id");
        return $stmt->execute([':id'=>$id]);
    }

    public function gc($maxlifetime) {
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE timestamp < :old");
        return $stmt->execute([':old'=>time()-$maxlifetime]);
    }
}

// Initialize session handler
$handler = new DbSessionHandler($db->getConnection());
session_set_save_handler($handler, true);
session_start();

/**
 * ===========================
 * FILE HELPER USAGE
 * ===========================
 */
$fileHelper = new FileHelper();

/**
 * ===========================
 * CURL CLIENT USAGE
 * ===========================
 */
$headers = [
    'Authorization: Basic pp_live_537b508caed8ce9e4a39d3f38d975b039b1df6ac355f3851'
];
$curl = new Curl($headers);

// API CURL
$api = new Curl();


/**
 * ===========================
 * FILE GET CONTENT USAGE
 * ===========================
 */
$fileGetContent = new FileGetContent('*');
