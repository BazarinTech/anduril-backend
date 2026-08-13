<?php

namespace Bazarin\APIS;


class FileGetContent {
    public function __construct($origin){
        $this->origin = $origin;
    }
    private function cors_auth (){
        header("Access-Control-Allow-Origin: ".$this->origin);
        header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        header("Access-Control-Allow-Credentials: true");

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
    public function get_content(){
        $this->cors_auth();
        $json = file_get_contents('php://input');
        return json_decode($json, true);
    }
    public function send_content($response){
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}