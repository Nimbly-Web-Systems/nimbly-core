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
    load_libraries(['data', 'email', 'env', 'get', 'lookup', 'set', 'text']);

    $recipient = system_alert_require_recipient();
    $failed_payload = $payload['failed_payload'] ?? [];
    $payload_json = json_encode($failed_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload_json === false) {
        $payload_json = '';
    }

    $site_name = data_lookup('.config', 'site', 'name', env('MAIL_FROM_NAME', 'Nimbly'));
    if (is_array($site_name)) {
        $site_name = get_i18n_resolve($site_name, 'auto');
    }
    $environment = trim((string)env('APP_ENV', 'unknown'));
    $origin = job_failure_alert_origin($failed_type, is_array($failed_payload) ? $failed_payload : []);

    set_variable('site_name', system_alert_html($site_name));
    set_variable('environment', system_alert_html($environment));
    set_variable('failed_origin', system_alert_html($origin));
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
        'subject' => '[' . $site_name . ' · ' . $environment . '] ' . t('Nimbly job failed') . ': ' . $failed_type,
        'tpl' => 'email-job-failure-alert',
    ]);
    if (!$sent) {
        throw new Exception('Job failure alert email could not be sent');
    }

    return true;
}

function job_failure_alert_origin(string $failed_type, array $failed_payload): string
{
    if ($failed_type !== 'agent') {
        return $failed_type;
    }

    $run_uuid = trim((string)($failed_payload['run_uuid'] ?? ''));
    $run = $run_uuid === '' ? null : data_read('.agent_runs', $run_uuid);
    if (!is_array($run)) {
        return $run_uuid === '' ? 'agent' : 'agent ' . $run_uuid;
    }

    $parts = [trim((string)($run['agent_id'] ?? 'agent'))];
    $trigger = trim((string)($run['trigger'] ?? ''));
    if ($trigger !== '') {
        $parts[] = $trigger;
    }
    $target = trim((string)($run['target'] ?? ''));
    $parts[] = $target !== '' ? $target : 'all configured targets';
    if ($run_uuid !== '') {
        $parts[] = 'run ' . $run_uuid;
    }
    return implode(' · ', $parts);
}
