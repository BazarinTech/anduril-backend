<?php

namespace Bazarin\Security;

class Cryptions {
    // Encryption key and method
    private $key;
    private $method = 'AES-256-CBC';

    // Constructor to set the key for encryption and decryption
    public function __construct($key) {
        if (empty($key)) {
            throw new InvalidArgumentException("Encryption key cannot be empty.");
        }
        $this->key = $key;
    }

    // Encrypt data
    public function encrypt($data) {
        // Generate an initialization vector (IV) for encryption
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->method));
        
        // Encrypt the data
        $encryptedData = openssl_encrypt($data, $this->method, $this->key, 0, $iv);
        
        // Return the encrypted data and IV as a base64-encoded string
        return base64_encode($encryptedData . '::' . $iv);
    }

    // Decrypt data
    public function decrypt($data) {
        // Decode the base64-encoded encrypted string
        list($encryptedData, $iv) = explode('::', base64_decode($data), 2);
        
        // Decrypt the data
        $decryptedData = openssl_decrypt($encryptedData, $this->method, $this->key, 0, $iv);
        
        return $decryptedData;
    }
}
