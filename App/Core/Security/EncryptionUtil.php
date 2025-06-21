<?php

/**
 * LazyMePHP
 * @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */


declare(strict_types=1);

namespace Core\Security;

class EncryptionUtil
{
    /**
     * Encrypt plaintext using sodium (libsodium).
     *
     * @param string $plaintext
     * @param string $key Must be SODIUM_CRYPTO_SECRETBOX_KEYBYTES bytes
     * @return string|false Base64-encoded ciphertext or false on failure
     */
    public static function encrypt(string $plaintext, string $key): string|false
    {
        if (empty($key) || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            return false; 
        }
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key);
        return base64_encode($nonce . $cipher);
    }

    /**
     * Decrypt ciphertext using sodium (libsodium).
     *
     * @param string $ciphertext Base64-encoded ciphertext
     * @param string $key Must be SODIUM_CRYPTO_SECRETBOX_KEYBYTES bytes
     * @return string|false Decrypted plaintext or false on failure
     */
    public static function decrypt(string $ciphertext, string $key): string|false
    {
        if (empty($key) || empty($ciphertext) || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            return false;
        }
        $decoded = base64_decode($ciphertext, true);
        if ($decoded === false || strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return false;
        }
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open($cipher, $nonce, $key);
        return $plaintext === false ? false : $plaintext;
    }
}
