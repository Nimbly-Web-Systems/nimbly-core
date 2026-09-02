<?php

$log_messages = [];

function load_library($_name) {}
function honeypot_field_name() { return 'website'; }
function log_system($message)
{
    global $log_messages;
    $log_messages[] = $message;
}

require_once __DIR__ . '/../modules/api/lib/api.php';

function api_honeypot_log_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$_SERVER['REQUEST_URI'] = '/api/v1/contact-messages?token=secret';
$_SERVER['HTTP_REFERER'] = 'https://example.com/nl/contact/?private=value';
$data = ['website' => " https://spam.example/buy-now\nignored "];

api_honeypot_log_assert(api_honeypot_check($data, 'contact-messages'), 'filled honeypot is rejected');
$message = $log_messages[0] ?? '';
api_honeypot_log_assert(
    str_starts_with($message, 'Spam caught: honeypot field filled on contact form'),
    'operator-facing event name is logged'
);
api_honeypot_log_assert(str_contains($message, '"resource":"contact-messages"'), 'resource is logged');
api_honeypot_log_assert(str_contains($message, '"path":"/api/v1/contact-messages"'), 'request path is logged without query data');
api_honeypot_log_assert(str_contains($message, '"referrer":"example.com/nl/contact/"'), 'referrer is logged without query data');
api_honeypot_log_assert(str_contains($message, 'https://spam.example/buy-now ignored'), 'payload excerpt is normalized');
api_honeypot_log_assert(!str_contains($message, 'token=secret'), 'request query data is not logged');
api_honeypot_log_assert(!str_contains($message, 'private=value'), 'referrer query data is not logged');

$clean_data = ['website' => '', 'message' => 'Hello'];
api_honeypot_log_assert(!api_honeypot_check($clean_data, 'contact-messages'), 'empty honeypot passes');
api_honeypot_log_assert(!isset($clean_data['website']), 'empty honeypot is removed');

$_SERVER['REQUEST_URI'] = '/api/v1/contact-messages?key=private';
$_SERVER['HTTP_REFERER'] = 'https://example.com/nl/contact/?tracking=private';
$validation_message = api_validation_failure_log('contact-messages', 'name:required', [
    'email' => 'private@example.com',
    'message' => 'Private message contents',
    'unsafe field name' => 'value',
]);
api_honeypot_log_assert(str_starts_with(
    $validation_message,
    'Resource create failed for contact-messages: validation failed (name:required)'
), 'validation failure keeps a readable summary');
api_honeypot_log_assert(str_contains($validation_message, '"path":"/api/v1/contact-messages"'), 'validation request path excludes query data');
api_honeypot_log_assert(str_contains($validation_message, '"referrer":"example.com/nl/contact/"'), 'validation referrer excludes query data');
api_honeypot_log_assert(str_contains($validation_message, '"submitted_fields":["email","message","unsafefieldname"]'), 'validation log contains sanitized field names');
api_honeypot_log_assert(!str_contains($validation_message, 'private@example.com'), 'validation log excludes field values');
api_honeypot_log_assert(!str_contains($validation_message, 'Private message contents'), 'validation log excludes message contents');
api_honeypot_log_assert(!str_contains($validation_message, 'key=private'), 'validation log excludes request query data');
api_honeypot_log_assert(!str_contains($validation_message, 'tracking=private'), 'validation log excludes referrer query data');

echo "API honeypot log tests passed.\n";
