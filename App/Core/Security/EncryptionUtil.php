<?php

/**
 * LazyMePHP
 * @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */


declare(strict_types=1);

namespace Security;

class EncryptionUtil
{
    public static function encrypt(string $plaintext, string $key)
    {
        if (empty($key)) {
            // Consider logging this or throwing an exception, as encryption without a key is a security risk.
            // For now, returning false to indicate failure, consistent with potential OpenSSL failures.
            return false; 
        }
        return openssl_encrypt($plaintext, 'aes-128-ecb', $key);
    }

    public static function decrypt(string $cipherText, string $key)
    {
        if (empty($key) || empty($cipherText)) {
            // Similar to encrypt, returning false for empty key or ciphertext.
            return false;
        }
        return openssl_decrypt($cipherText, 'aes-128-ecb', $key);
    }
}
