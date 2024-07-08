<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;

class AES extends Controller
{
    private $cipher;
    private $key;
    private $options;

    public function __construct($key, $cipher = 'aes-128-ecb', $options = OPENSSL_RAW_DATA)
    {
        $this->key = hex2bin($key);
        $this->cipher = $cipher;
        $this->options = $options;
    }

    public function encrypt($plainText)
    {
        $encryptedData = openssl_encrypt($plainText, $this->cipher, $this->key, $this->options);
        if ($encryptedData === false) {
            throw new Exception('Encryption failed');
        }
        return bin2hex($encryptedData); // Return encrypted data as a hex string
    }

    public function decrypt($encryptedHex)
    {
        $encryptedData = hex2bin($encryptedHex);
        $decryptedData = openssl_decrypt($encryptedData, $this->cipher, $this->key, $this->options);
        if ($decryptedData === false) {
            throw new Exception('Decryption failed');
        }
        return $decryptedData;
    }
}
