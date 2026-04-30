<?php

/**
 * @doc `[encrypt text="string to encrypt" salt="a salt key"]` outputs encrypted text using a salt key
 */
function encrypt_sc($params)
{
    $text = get_param_value($params, "text", current($params));
    $salt = get_param_value($params, "salt");
    return encrypt($text, $salt);
}

function encrypt($text, $salt)
{
    if (empty($salt)) {
        throw new Exception('Empty salt');
    }
    return crypt($text, '$2a$07$' . $salt . '$');
}


function encrypt_2way($text, $salt)
{
    if (empty($salt)) {
        throw new Exception('Empty salt');
    }
    $cipher = "aes-128-gcm";
    if (!in_array($cipher, openssl_get_cipher_methods())) {
        throw new Exception('Unknown cipher');
    }
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $result =
        [
            'encrypted_text' => openssl_encrypt($text, $cipher, $salt . $_SERVER['PEPPER'], 0, $iv, $tag),
            'cipher' => $cipher,
            'iv' => bin2hex($iv),
            'tag' => bin2hex($tag)
        ];
    return $result;
}

function decrypt_2way($encrypted_data, $salt)
{
    if (empty($salt)) {
        throw new Exception('Empty salt');
    }
    $result =  openssl_decrypt(
        $encrypted_data['encrypted_text'],
        $encrypted_data['cipher'],
        $salt . $_SERVER['PEPPER'],
        0,
        hex2bin($encrypted_data['iv']),
        hex2bin($encrypted_data['tag'])
    );
    return $result;
}
