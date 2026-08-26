<?php

$job_failure_alert_variables = [];
$job_failure_alert_email = [];

function load_library($name): void {}
function load_libraries($names): void {}
function env($key, $default = null) {
    return [
        'APP_ENV' => 'stage',
        'MAIL_FROM_NAME' => 'Nimbly',
        'SYSTEM_ALERT_EMAIL' => 'operator@example.com',
    ][$key] ?? $default;
}
function data_lookup($resource, $uuid, $field, $default = null) { return 'Nimbly'; }
function data_read($resource, $uuid) {
    return $uuid === 'run-123' ? [
        'agent_id' => 'infra-expert',
        'trigger' => 'scheduled',
        'target' => '',
    ] : null;
}
function get_i18n_resolve($value, $language) { return $value; }
function set_variable($key, $value): void { $GLOBALS['job_failure_alert_variables'][$key] = $value; }
function email($configuration): bool { $GLOBALS['job_failure_alert_email'] = $configuration; return true; }
function t($value) { return $value; }

require_once __DIR__ . '/../modules/system/lib/job-failure-alert.php';

function job_failure_alert_test_assert($condition, $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

job_failure_alert_job(['payload' => [
    'failed_type' => 'agent-run',
    'failed_uuid' => 'job-123',
    'failed_attempts' => 3,
    'failed_error' => 'Connector unavailable',
    'failed_payload' => ['run_uuid' => 'run-123'],
]]);

job_failure_alert_test_assert($job_failure_alert_variables['site_name'] === 'Nimbly', 'alert identifies the application');
job_failure_alert_test_assert($job_failure_alert_variables['environment'] === 'stage', 'alert identifies the environment');
job_failure_alert_test_assert(
    $job_failure_alert_variables['failed_origin'] === 'infra-expert · scheduled · all configured targets · run run-123',
    'agent alert identifies its agent, trigger, target scope, and run'
);
job_failure_alert_test_assert(
    $job_failure_alert_email['subject'] === '[Nimbly · stage] Nimbly job failed: agent-run',
    'subject identifies application and environment'
);

echo "Job failure alert tests passed.\n";
