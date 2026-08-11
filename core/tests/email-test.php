<?php

function load_library($name) {}
function load_libraries($names) {}
function env($key, $default = null) { return $default; }
function plain_text($html) { return trim(strip_tags($html)); }
function find_template($name) { return false; }
function run_buffered($template) { return ''; }
function set_variable($key, $value) {}
function log_system($message) {}

require_once __DIR__ . '/../lib/email/email.php';

function email_test_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$captured = [];
$request = function ($path, $payload, $options) use (&$captured) {
    $captured = compact('path', 'payload', 'options');
    return ['success' => true, 'body' => ['id' => 'email_123'], 'error' => null];
};
$result = email_result([
    'service' => 'resend',
    'recipient' => 'person@example.com',
    'subject' => 'Rendered HTML',
    'html' => '<p>Hello</p>',
    'from' => 'news@example.com',
    'reply_to' => 'reply@example.com',
    'headers' => ['List-Unsubscribe' => '<https://example.com/unsubscribe>'],
    'tags' => [['name' => 'campaign', 'value' => 'campaign-1']],
    'idempotency_key' => 'campaign-1-batch-0',
    'request' => $request,
]);
email_test_assert($result['success'] && $result['id'] === 'email_123', 'result-oriented send returns provider ID');
email_test_assert($captured['path'] === '/emails', 'single send uses Resend email endpoint');
email_test_assert($captured['payload']['to'] === ['person@example.com'], 'recipient is normalized as an array');
email_test_assert($captured['payload']['reply_to'] === 'reply@example.com', 'reply-to is forwarded');
email_test_assert(isset($captured['payload']['headers']['List-Unsubscribe']), 'custom headers are forwarded');
email_test_assert($captured['options']['idempotency_key'] === 'campaign-1-batch-0', 'idempotency key reaches transport');

$batch_request = function ($path, $payload) {
    return ['success' => true, 'body' => ['data' => array_map(fn($ix) => ['id' => 'batch_' . $ix], array_keys($payload))], 'error' => null];
};
$batch = email_batch_result([
    ['recipient' => 'one@example.com', 'subject' => 'One', 'html' => '<p>One</p>'],
    ['recipient' => 'two@example.com', 'subject' => 'Two', 'html' => '<p>Two</p>'],
], ['service' => 'resend', 'from' => 'news@example.com', 'request' => $batch_request]);
email_test_assert($batch['success'] && count($batch['ids']) === 2, 'batch returns all provider IDs');
email_test_assert(!email_batch_result(array_fill(0, 101, []))['success'], 'batch rejects more than 100 messages');

$error = email_result([
    'service' => 'resend',
    'recipient' => 'person@example.com',
    'subject' => 'Failure',
    'html' => '<p>Failure</p>',
    'request' => fn() => ['success' => false, 'body' => [], 'error' => 'Safe provider error'],
]);
email_test_assert(!$error['success'] && $error['error'] === 'Safe provider error', 'provider errors remain safe and actionable');

$sent = email([
    'service' => 'resend', 'recipient' => 'person@example.com', 'subject' => 'Failure', 'html' => '<p>Failure</p>',
    'request' => fn() => ['success' => false, 'body' => [], 'error' => 'Safe provider error'],
]);
email_test_assert($sent === false, 'boolean email API remains backwards compatible');
email_test_assert(email_last_result()['error'] === 'Safe provider error', 'the latest structured failure remains available to the job runner');
email_clear_last_result();
email_test_assert(email_last_result() === [], 'email failure context can be reset between jobs');

echo "Email adapter tests passed.\n";
