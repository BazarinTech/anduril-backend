<?php

namespace Bazarin\APIS;


class Curl {
    private $defaultHeaders = [];
    private $debug = false;

    public function __construct($defaultHeaders = [], $debug = false) {
        $this->defaultHeaders = $defaultHeaders;
        $this->debug = $debug;
    }

    public function setDefaultHeaders(array $headers) {
        $this->defaultHeaders = $headers;
    }

    public function enableDebugMode($debug = true) {
        $this->debug = $debug;
    }

    public function request($url, $method = 'GET', $data = [], $headers = []) {
        $ch = curl_init();

        // Merge default headers with specific headers
        $headers = array_merge($this->defaultHeaders, $headers);

        // Set request method and data
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;

            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;

            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;

            default: // GET
                if (!empty($data)) {
                    $url .= '?' . http_build_query($data);
                }
                break;
        }

        // Common cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout in seconds
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(['Content-Type: application/json'], $headers));

        // Execute request
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Debugging information
        if ($this->debug) {
            error_log("Request URL: $url");
            error_log("Request Method: $method");
            error_log("Request Data: " . json_encode($data));
            error_log("Response Code: $httpCode");
            error_log("Response Body: $response");
        }

        // Handle cURL errors
        // if ($error) {
        //     throw new RuntimeException("cURL error: $error");
        // }

        // Close connection
        curl_close($ch);

        // Check for HTTP errors
        // if ($httpCode >= 400) {
        //     throw new RuntimeException("HTTP Error: $httpCode, Response: $response");
        // }

        // Decode JSON response
        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid JSON response: $response");
        }

        return $decodedResponse;
    }
}

class ApiManager {
    private $client;

    public function __construct($defaultHeaders = [], $debug = false) {
        $this->client = new RestClient($defaultHeaders, $debug);
    }

    public function setDefaultHeaders(array $headers) {
        $this->client->setDefaultHeaders($headers);
    }

    public function enableDebugMode($debug = true) {
        $this->client->enableDebugMode($debug);
    }

    public function fetchAll($url, $headers = []) {
        return $this->client->request($url, 'GET', [], $headers);
    }

    public function create($url, $data, $headers = []) {
        return $this->client->request($url, 'POST', $data, $headers);
    }

    public function update($url, $data, $headers = []) {
        return $this->client->request($url, 'PUT', $data, $headers);
    }

    public function delete($url, $headers = []) {
        return $this->client->request($url, 'DELETE', [], $headers);
    }
}
