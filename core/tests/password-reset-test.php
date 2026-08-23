<?php

$test_logs = $test_users = $test_jobs = [];
$test_update_fails = $test_job_fails = false;
$test_uuid = 0;
$email_test_cfg = null;

function load_library($library): void {}
function load_libraries($libraries): void {}
function t($message): string { return $message; }
function log_system($message): void { global $test_logs; $test_logs[] = $message; }
function log_system_event(string $event, array $context = []): void {
    log_system('event=' . $event . ' ' . json_encode($context));
}
function generate_uuid(): string { global $test_uuid; return 'generated-' . ++$test_uuid; }
function generate_salt(): string { return 'salt-' . generate_uuid(); }
function encrypt($password, $salt): string { return 'encrypted:' . $salt . ':' . $password; }
function url_absolute($path): string { return 'https://example.com/' . ltrim($path, '/'); }
function validate($type, $input) { return $type === 'password' && is_string($input) && strlen($input) >= 5 && strlen($input) <= 64; }
function set_variable($key, $value): void {}
function env($key, $default = null) { return $default; }

function find_user_by_email($email): array
{
    global $test_users;
    foreach ($test_users as $user) {
        if (($user['email'] ?? '') === $email) { return $user; }
    }
    return [];
}

function data_update($resource, $uuid, $updates)
{
    global $test_users, $test_update_fails;
    if ($test_update_fails || $resource !== 'users' || !isset($test_users[$uuid])) { return false; }
    return $test_users[$uuid] = array_merge($test_users[$uuid], $updates);
}

function data_read($resource, $uuid) { global $test_users; return $test_users[$uuid] ?? false; }

function job_enqueue($type, $payload = [], $options = [])
{
    global $test_jobs, $test_job_fails;
    if ($test_job_fails) { return false; }
    $test_jobs[] = ['type' => $type, 'payload' => $payload];
    return 'job-' . count($test_jobs);
}

function data_lookup($resource, $uuid, $key, $default = null)
{
    return $resource === '.config' && $uuid === 'site' && $key === 'name'
        ? ['nl' => 'JE reis', 'en' => 'JE reis'] : $default;
}
function get_i18n_resolve(array $value, $lang = 'auto') { return $value['en'] ?? current($value); }
function email($cfg): bool { global $email_test_cfg; $email_test_cfg = $cfg; return true; }

function assert_reset($condition, $message): void
{
    if (!$condition) { fwrite(STDERR, 'FAIL: ' . $message . "\n"); exit(1); }
}

require_once __DIR__ . '/../modules/user/lib/password-reset.php';

$result = password_reset_request('unknown@example.com');
assert_reset($result['sent'] === false, 'unknown email must use generic result');
assert_reset(str_contains(($test_logs[0] ?? ''), 'event=password_reset.request_unknown'), 'unknown email event');
assert_reset(!str_contains(implode("\n", $test_logs), 'unknown@example.com'), 'unknown email is not logged');

$test_users['existing'] = ['uuid' => 'existing', 'email' => 'existing@example.com', 'name' => 'Existing', 'password' => 'hash', 'salt' => 'salt'];
$result = password_reset_request('existing@example.com');
$existing_token = $test_users['existing']['password_reset_token'] ?? '';
assert_reset($result['sent'] === true && $existing_token !== '', 'password-bearing user reset');
assert_reset($test_jobs[0]['payload']['reset_url'] === 'https://example.com/password-reset/existing/' . $existing_token, 'working reset URL');
assert_reset(str_contains(implode("\n", $test_logs), '"user_uuid":"existing"'), 'known reset logs identify the user UUID');

$test_users['imported'] = ['uuid' => 'imported', 'email' => 'imported@example.com', 'name' => ''];
$result = password_reset_request('imported@example.com');
$imported_token = $test_users['imported']['password_reset_token'] ?? '';
assert_reset($result['sent'] === true, 'passwordless user reset');
assert_reset(!empty($test_users['imported']['password']) && !empty($test_users['imported']['salt']), 'passwordless credential initialization');
password_reset_request('imported@example.com');
assert_reset($test_users['imported']['password_reset_token'] === $imported_token, 'outstanding token reuse');

$jobs_before = count($test_jobs);
$test_update_fails = true;
$result = password_reset_request('existing@example.com');
$test_update_fails = false;
assert_reset($result['sent'] === false && count($test_jobs) === $jobs_before, 'persistence failure must not enqueue');

$test_job_fails = true;
$result = password_reset_request('existing@example.com');
$test_job_fails = false;
assert_reset($result['sent'] === false && $result['message'] === password_reset_public_message(), 'queue failure generic result');
assert_reset(str_contains(implode("\n", $test_logs), 'event=password_reset.queue_failed'), 'queue failure event');
assert_reset(!str_contains(implode("\n", $test_logs), 'existing@example.com'), 'reset lifecycle logs omit email addresses');

$completed = password_reset_complete('imported', $imported_token, 'new-secure-password');
assert_reset(is_array($completed), 'passwordless user first password');
assert_reset(!password_reset_token_matches($completed, $imported_token), 'successful reset rotates token');

$valid_token = $test_users['existing']['password_reset_token'];
assert_reset(password_reset_complete('existing', $valid_token, '') === false, 'empty password rejection');
assert_reset(password_reset_complete('existing', $valid_token, 'tiny') === false, 'invalid password rejection');
assert_reset($test_users['existing']['password_reset_token'] === $valid_token, 'invalid password preserves link');
password_reset_complete('existing', 'private-invalid-token', 'valid-password');
assert_reset(!str_contains(implode("\n", $test_logs), 'private-invalid-token'), 'invalid reset token is never logged');
$test_update_fails = true;
assert_reset(password_reset_complete('existing', $valid_token, 'valid-password') === false, 'completion save failure');
$test_update_fails = false;
assert_reset($test_users['existing']['password_reset_token'] === $valid_token, 'save failure preserves link');

password_reset_job(['payload' => ['email' => 'someone@example.com', 'name' => 'Someone', 'reset_url' => 'https://example.com/reset']]);
assert_reset(($email_test_cfg['subject'] ?? '') === 'Reset your JE reis password', 'i18n site name subject');

echo "Password reset tests passed\n";
