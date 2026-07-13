<?php

function job_failure_alert_job($job)
{
    $payload = $job['payload'] ?? [];
    $failed_type = trim((string)($payload['failed_type'] ?? ''));
    $failed_uuid = trim((string)($payload['failed_uuid'] ?? ''));
    if ($failed_type === '' || $failed_uuid === '') {
        throw new Exception('Failed job type or UUID is missing');
    }

    require_once __DIR__ . '/system-alert.php';
    load_libraries(['email', 'env', 'set', 'text']);

    $recipient = system_alert_require_recipient();
    $payload_json = json_encode($payload['failed_payload'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload_json === false) {
        $payload_json = '';
    }

    set_variable('failed_type', system_alert_html($failed_type));
    set_variable('failed_uuid', system_alert_html($failed_uuid));
    set_variable('failed_attempts', system_alert_html($payload['failed_attempts'] ?? ''));
    set_variable('failed_error', system_alert_html($payload['failed_error'] ?? ''));
    set_variable('failed_payload', system_alert_html($payload_json));

    $sent = email([
        'service' => env('MAIL_SERVICE', 'resend'),
        'from' => env('MAIL_FROM'),
        'from_name' => env('MAIL_FROM_NAME', 'Nimbly'),
        'recipient' => $recipient,
        'subject' => t('Nimbly job failed') . ': ' . $failed_type,
        'tpl' => 'email-job-failure-alert',
    ]);
    if (!$sent) {
        throw new Exception('Job failure alert email could not be sent');
    }

    return true;
}
