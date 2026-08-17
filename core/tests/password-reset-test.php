<?php

$password_reset_test_logs = [];

function load_library($library): void
{
}

function load_libraries($libraries): void
{
}

function t($message): string
{
    return $message;
}

function find_user_by_email($email): array
{
    return [];
}

function log_system($message): void
{
    global $password_reset_test_logs;
    $password_reset_test_logs[] = $message;
}

require_once __DIR__ . '/../modules/user/lib/password-reset.php';

$result = password_reset_request('unknown@example.com');
$message = $password_reset_test_logs[0] ?? '';

if (($result['sent'] ?? null) !== false
    || $message !== 'Password reset requested for unknown email unknown@example.com'
    || stripos($message, 'error') !== false) {
    fwrite(STDERR, "FAIL: unknown-email password reset must be informational\n");
    exit(1);
}

// A multi-language site stores site.name as an i18n array ({"nl": "...",
// "en": "..."}), not a plain string. The reset email body already resolves
// this correctly via [#get#], but the subject line used to concatenate the
// raw array straight into a string — producing a literal "Array" in the
// subject the user actually sees. Regression coverage for that.
$site_config = [
    'name' => ['nl' => 'JE reis', 'en' => 'JE reis'],
];

function data_lookup($resource, $uuid, $key, $default = null)
{
    global $site_config;
    if ($resource === '.config' && $uuid === 'site' && $key === 'name') {
        return $site_config['name'];
    }
    return $default;
}

function get_i18n_resolve(array $val, $lang = 'auto')
{
    // Mirrors core/lib/get.php's real resolution shape closely enough for
    // this test: pick a configured translation deterministically.
    return $val['en'] ?? current($val);
}

function set_variable($key, $value): void
{
}

function env($key, $default = null)
{
    return $default;
}

$email_test_cfg = null;
function email($cfg): bool
{
    global $email_test_cfg;
    $email_test_cfg = $cfg;
    return true;
}

password_reset_job([
    'payload' => [
        'email' => 'someone@example.com',
        'name' => 'Someone',
        'reset_url' => 'https://example.com/password-reset/uuid/token',
    ],
]);

if (($email_test_cfg['subject'] ?? '') !== 'Reset your JE reis password') {
    fwrite(STDERR, "FAIL: password reset subject did not resolve the i18n site name, got: " . ($email_test_cfg['subject'] ?? '(none)') . "\n");
    exit(1);
}

echo "Password reset tests passed\n";
