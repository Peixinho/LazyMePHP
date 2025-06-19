<?php

use Core\Security\EncryptionUtil;

test('encryption and decryption works', function () {
    $original = 'Sensitive Data';
    $key = sodium_crypto_secretbox_keygen(); // Use sodium's keygen for a valid key
    $encrypted = EncryptionUtil::encrypt($original, $key);
    $decrypted = EncryptionUtil::decrypt($encrypted, $key);
    expect($decrypted)->toBe($original);
}); 