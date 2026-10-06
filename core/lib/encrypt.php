<?php

const PASSWORD_BCRYPT_COST = 12;

/**
 * Hashes a password for storage. The salt parameter is kept for callers and
 * for the record's salt field; password_hash() makes its own.
 */
function encrypt($text, $salt)
{
    if (empty($salt)) {
        throw new Exception('Empty salt');
    }
    // bcrypt reads the first 72 bytes only
    return password_hash(substr((string)$text, 0, 72), PASSWORD_BCRYPT, ['cost' => PASSWORD_BCRYPT_COST]);
}

/**
 * True when the typed password belongs to the stored hash, old or new.
 */
function password_matches($text, $stored)
{
    $text = (string)$text;
    if (!is_string($stored) || $stored === '' || str_contains($text, "\0")) {
        return false;
    }
    return password_verify(substr($text, 0, 72), $stored);
}

/**
 * True when a stored hash was made with older settings than encrypt() uses now.
 */
function password_is_outdated($stored)
{
    return password_needs_rehash((string)$stored, PASSWORD_BCRYPT, ['cost' => PASSWORD_BCRYPT_COST]);
}


/**
 * The key comes from PEPPER and a salt stored inside the value, so a value
 * stays readable when the record's salt changes on a later save.
 */
function encrypt_2way_key(string $value_salt): string
{
    $pepper = (string)($_SERVER['PEPPER'] ?? '');
    if ($pepper === '') {
        throw new Exception('Empty pepper');
    }
    return hash_hkdf('sha256', $pepper, 32, 'nimbly-encrypt-2way', $value_salt);
}

function encrypt_2way($text, $salt)
{
    if (empty($salt)) {
        throw new Exception('Empty salt');
    }
    $cipher = "aes-256-gcm";
    if (!in_array($cipher, openssl_get_cipher_methods())) {
        throw new Exception('Unknown cipher');
    }
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = random_bytes($ivlen);
    $value_salt = random_bytes(16);
    $result =
        [
            'encrypted_text' => openssl_encrypt($text, $cipher, encrypt_2way_key($value_salt), 0, $iv, $tag),
            'cipher' => $cipher,
            'iv' => bin2hex($iv),
            'tag' => bin2hex($tag),
            'salt' => bin2hex($value_salt)
        ];
    return $result;
}

function decrypt_2way($encrypted_data, $salt)
{
    if (empty($salt)) {
        throw new Exception('Empty salt');
    }
    // a value without its own salt was stored with the old key
    $key = isset($encrypted_data['salt'])
        ? encrypt_2way_key((string)hex2bin($encrypted_data['salt']))
        : $salt . $_SERVER['PEPPER'];
    $result =  openssl_decrypt(
        $encrypted_data['encrypted_text'],
        $encrypted_data['cipher'],
        $key,
        0,
        hex2bin($encrypted_data['iv']),
        hex2bin($encrypted_data['tag'])
    );
    return $result;
}
