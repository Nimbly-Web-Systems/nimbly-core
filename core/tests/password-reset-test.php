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

echo "Password reset tests passed\n";
