<?php
require 'vendor/autoload.php';

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
 * 
 * USAGE:
 * - Configure the database connection
 * - Utilize helper classes for different functionalities
 * - Perform API requests, cryptographic operations, and file handling
 */

/** 
 * ===========================
 * DATABASE INITIALIZATION
 * ===========================
 * Configure and establish a connection to the MySQL database.
 */
$db = new Connection([
    'host' => 'localhost',
    'user' => 'bazarin',
    'password' => 'bazarin',
    'database' => 'xgramm'
]);

// Initialize the Query Builder
$query = new QueryBuilder($db->getConnection());

// Example Query: Fetch a user where username = 'xgramm'
$users = $query->select("users", '*', ['username' => 'xgramm']);
print_r($users);

/**
 * ===========================
 * FILE HELPER USAGE
 * ===========================
 * Used for handling file uploads and operations.
 */
$fileHelper = new FileHelper();

/**
 * ===========================
 * DATE HELPER USAGE
 * ===========================
 * Provides functions to work with dates.
 */
$dateHelper = new DateHelper();

/**
 * ===========================
 * CURL CLIENT USAGE
 * ===========================
 * Used for making HTTP requests.
 */
$curl = new Curl();
$response = $curl->request('https://jsonplaceholder.typicode.com/todos/1', 'GET');
var_dump($response);

/**
 * ===========================
 * FILE GET CONTENT USAGE
 * ===========================
 * Used to retrieve file contents over HTTP.
 */
$fileGetContent = new FileGetContent('*');

/**
 * ===========================
 * SECURITY / ENCRYPTION
 * ===========================
 * Used for cryptographic operations like encryption and decryption.
 */
$cryptions = new Cryptions('key');


