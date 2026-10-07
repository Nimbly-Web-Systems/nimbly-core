<?php

// Passwords: a hash made by the old encrypt() still logs in and is replaced
// on that login; a stored value that is no hash never matches.
// Two-way fields: a value stored with the old key still decrypts; a new value
// needs PEPPER and survives a change of the record's salt.

$test_updates = [];

function load_library($library): void {}
function data_update($resource, $uuid, $data) {
    global $test_updates;
    $test_updates[] = [$resource, $uuid, $data];
    return $data;
}

$root = dirname(__DIR__, 2) . '/';
require $root . 'core/lib/encrypt.php';
require $root . 'core/modules/user/lib/access.php';

function password_hash_test_assert($condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}

$_SERVER['PEPPER'] = 'test-pepper-0123456789';
$salt = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQ';
$old_hash = crypt('typed-password', '$2a$07$' . $salt . '$');
password_hash_test_assert(strlen($old_hash) === 60, 'the fixture is not an old-format hash');

// passwords
password_hash_test_assert(password_matches('typed-password', $old_hash) === true, 'an old hash no longer matches its password');
password_hash_test_assert(password_matches('other-password', $old_hash) === false, 'a wrong password matched an old hash');
password_hash_test_assert(password_is_outdated($old_hash) === true, 'an old hash is not marked for replacement');

$new_hash = encrypt('typed-password', $salt);
password_hash_test_assert($new_hash !== encrypt('typed-password', $salt), 'two hashes of one password are equal');
password_hash_test_assert(str_starts_with($new_hash, '$2y$12$'), 'a new hash does not use the current settings');
password_hash_test_assert(password_matches('typed-password', $new_hash) === true, 'a new hash does not match its password');
password_hash_test_assert(password_matches('other-password', $new_hash) === false, 'a wrong password matched a new hash');
password_hash_test_assert(password_is_outdated($new_hash) === false, 'a new hash is marked for replacement');

$long = str_repeat('long-password-', 8);
password_hash_test_assert(password_matches($long, encrypt($long, $salt)) === true, 'a password over 72 bytes does not match its own hash');
password_hash_test_assert(password_matches($long, crypt($long, '$2a$07$' . $salt . '$')) === true, 'a password over 72 bytes no longer matches its old hash');

// a stored value that is not a hash never matches
foreach (['*0', '*1', 'plain-text', 'typed-password'] as $not_a_hash) {
    foreach (['typed-password', 'anything', $not_a_hash, ''] as $typed) {
        password_hash_test_assert(password_matches($typed, $not_a_hash) === false, 'a stored value that is not a hash matched a password');
    }
}
password_hash_test_assert(password_matches('typed-password', '') === false, 'an empty stored password matched');
password_hash_test_assert(password_matches('typed-password', null) === false, 'a missing stored password matched');
password_hash_test_assert(password_matches("typed-password\0tail", $old_hash) === false, 'a password with a NUL byte matched');

$GLOBALS['SYSTEM']['file_base'] = sys_get_temp_dir() . '/nimbly-password-hash-test-' . getmypid() . '/';
register_shutdown_function(fn() => exec('rm -rf ' . escapeshellarg($GLOBALS['SYSTEM']['file_base'])));

// login replaces an old hash once, and only after a match
$user = ['uuid' => 'user-1', 'salt' => $salt, 'password' => $old_hash];
password_hash_test_assert(user_password_check($user, 'other-password') === false, 'login accepted a wrong password');
password_hash_test_assert($test_updates === [], 'a failed login wrote to the user record');
password_hash_test_assert(user_password_check($user, 'typed-password') === true, 'login refused the right password');
password_hash_test_assert(count($test_updates) === 1 && $test_updates[0][0] === 'users' && $test_updates[0][1] === 'user-1', 'login did not replace the old hash');
password_hash_test_assert(array_keys($test_updates[0][2]) === ['password'], 'replacing the hash changed other fields');
$user['password'] = $test_updates[0][2]['password'];
password_hash_test_assert(password_is_outdated($user['password']) === false, 'the replaced hash is still an old one');
password_hash_test_assert(user_password_check($user, 'typed-password') === true, 'login refused the password after the hash was replaced');
password_hash_test_assert(count($test_updates) === 1, 'a current hash was replaced again');
password_hash_test_assert(user_password_check(['uuid' => 'user-2', 'salt' => $salt, 'password' => 'plain-text'], 'plain-text') === false, 'login accepted a stored value that is not a hash');

// after five wrong passwords the right one is refused too, until the oldest is 15 minutes old
$user['uuid'] = 'user-3';
for ($i = 0; $i < 4; $i++) {
    user_password_check($user, 'other-password');
}
password_hash_test_assert(user_password_check($user, 'typed-password') === true, 'login refused the right password after four wrong ones');
password_hash_test_assert(user_login_failures('user-3') === [], 'a login did not clear the wrong passwords before it');
for ($i = 0; $i < 5; $i++) {
    user_password_check($user, 'other-password');
}
password_hash_test_assert(user_password_check($user, 'typed-password') === false, 'login accepted a password after five wrong ones');
password_hash_test_assert(count(user_login_failures('user-3')) === 5, 'a refused attempt was counted');
password_hash_test_assert(user_password_check(['uuid' => 'user-4'] + $user, 'typed-password') === true, 'wrong passwords on one account blocked another');
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
password_hash_test_assert(user_password_check($user, 'typed-password') === true, 'wrong passwords from one address kept the owner out at another');
unset($_SERVER['REMOTE_ADDR']);
password_hash_test_assert(user_password_check($user, 'typed-password') === false, 'a login from another address opened the blocked one');
file_put_contents(user_login_failures_path('user-3'), implode("\n", array_fill(0, 5, time() - LOGIN_FAILURES_SECONDS - 1)) . "\n");
password_hash_test_assert(user_password_check($user, 'typed-password') === true, 'login still refused after the wait');
user_password_check($user, 'other-password');
user_login_failures_clear('user-3');
password_hash_test_assert(user_login_failures('user-3') === [], 'clearing left wrong passwords behind');

// two-way: a value stored with the old key
$iv = random_bytes(12);
$old_value = [
    'encrypted_text' => openssl_encrypt('old secret', 'aes-128-gcm', $salt . $_SERVER['PEPPER'], 0, $iv, $tag),
    'cipher' => 'aes-128-gcm',
    'iv' => bin2hex($iv),
    'tag' => bin2hex($tag),
];
password_hash_test_assert(decrypt_2way($old_value, $salt) === 'old secret', 'a value stored with the old key no longer decrypts');

// two-way: new values
$value = encrypt_2way('new secret', $salt);
password_hash_test_assert($value['cipher'] === 'aes-256-gcm' && strlen($value['salt']) === 32, 'a new value is not in the new format');
password_hash_test_assert(decrypt_2way($value, $salt) === 'new secret', 'a new value does not decrypt');
password_hash_test_assert(decrypt_2way($value, 'another-record-salt') === 'new secret', 'a new value is lost when the record salt changes');
$_SERVER['PEPPER'] = 'another-pepper';
password_hash_test_assert(decrypt_2way($value, $salt) === false, 'a new value decrypts without the right PEPPER');
$_SERVER['PEPPER'] = '';
foreach (['encrypt' => fn() => encrypt_2way('x', $salt), 'decrypt' => fn() => decrypt_2way($value, $salt)] as $name => $call) {
    $thrown = false;
    try { $call(); } catch (Exception $e) { $thrown = true; }
    password_hash_test_assert($thrown, $name . ' ran without PEPPER');
}

echo "password-hash-test: ok\n";
