<?php
require_once ('../vendor/autoload.php');

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
    'user' => 'xgrammco_xgrammco',
    'password' => ')nnFINZj5c1:65',
    'database' => 'xgrammco_sanders'
]);

// Initialize the Query Builder
$query = new QueryBuilder($db->getConnection());

/**
 * ===========================
 * FILE HELPER USAGE
 * ===========================
 * Used for handling file uploads and operations.
 */
$fileHelper = new FileHelper();

/**
 * ===========================
 * CURL CLIENT USAGE
 * ===========================
 * Used for making HTTP requests.
 */
$headers = [
    'Authorization: Basic pp_live_537b508caed8ce9e4a39d3f38d975b039b1df6ac355f3851'
];
$curl = new Curl($headers);

/**
 * ===========================
 * FILE GET CONTENT USAGE
 * ===========================
 * Used to retrieve file contents over HTTP.
 */
$fileGetContent = new FileGetContent('*');