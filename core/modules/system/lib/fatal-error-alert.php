<?php

function fatal_error_alert_job($job)
{
    $payload = $job['payload'] ?? [];
    $message = trim((string)($payload['message'] ?? ''));
    if ($message === '') {
        throw new Exception('Fatal error alert message is missing');
    }

    require_once __DIR__ . '/system-alert.php';
    load_libraries(['data', 'email', 'env', 'lookup', 'set', 'text']);

    $recipient = system_alert_require_recipient();
    $site_name = data_lookup('.config', 'site', 'name', env('MAIL_FROM_NAME', 'Nimbly'));

    set_variable('site_name', system_alert_html($site_name));
    set_variable('fatal_type', system_alert_html($payload['type'] ?? ''));
    set_variable('fatal_message', system_alert_html($message));
    set_variable('fatal_file', system_alert_html($payload['file'] ?? ''));
    set_variable('fatal_line', system_alert_html($payload['line'] ?? ''));
    set_variable('fatal_url', system_alert_html($payload['url'] ?? ''));
    set_variable('fatal_signature', system_alert_html($payload['signature'] ?? ''));

    $sent = email([
        'service' => env('MAIL_SERVICE', 'resend'),
        'from' => env('MAIL_FROM'),
        'from_name' => env('MAIL_FROM_NAME', 'Nimbly'),
        'recipient' => $recipient,
        'subject' => '[' . $site_name . '] ' . t('Fatal PHP error'),
        'tpl' => 'email-fatal-error-alert',
    ]);
    if (!$sent) {
        throw new Exception('Fatal error alert email could not be sent');
    }

    return true;
}
