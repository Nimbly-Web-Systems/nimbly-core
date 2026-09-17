<?php

$fatal_test_root = sys_get_temp_dir() . '/nimbly-fatal-test-' . bin2hex(random_bytes(6));
mkdir($fatal_test_root . '/ext/data/.state', 0700, true);
define('BASE_DIR', $fatal_test_root . '/');
$fatal_test_records = [];

function load_libraries($names): void {}
function load_library($name): void {}
function env($name, $default = '') { return $name === 'SYSTEM_ALERT_EMAIL' ? 'operator@example.test' : $default; }
function data_lookup($resource, $uuid, $field, $default = '') { return 'Fixture Site'; }
function set_variable($name, $value): void {}
function t($value) { return $value; }
function email($message): bool
{
    $GLOBALS['fatal_test_mail_calls']++;
    return $GLOBALS['fatal_test_mail_calls'] > 1;
}
function data_read($resource, $uuid)
{
    return $GLOBALS['fatal_test_records'][$resource][$uuid] ?? null;
}
function data_create($resource, $uuid, $record): bool
{
    $GLOBALS['fatal_test_records'][$resource][$uuid] = $record;
    return true;
}
function job_enqueue($type, $payload, $options): string
{
    $uuid = 'test-job-' . count($GLOBALS['fatal_test_records']['.jobs'] ?? []);
    $GLOBALS['fatal_test_records']['.jobs'][$uuid] = [
        'status' => 'queued', 'type' => $type, 'payload' => $payload, 'options' => $options,
    ];
    return $uuid;
}
function fatal_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

require_once dirname(__DIR__) . '/lib/fatal-alert.php';
$_SERVER['SERVER_NAME'] = 'staging.example.test';
$_SERVER['REQUEST_URI'] = '/some/path?token=secret';
$error = ['type' => E_ERROR, 'message' => 'password=private form value',
    'file' => $fatal_test_root . '/ext/uri/widgets/(id)/route.inc', 'line' => 9];
$start = 1789000000;
for ($index = 0; $index < 4; $index++) {
    fatal_alert_enqueue($error, $start + $index * 60);
}
fatal_test_assert(count($fatal_test_records['.jobs']) === 1, 'duplicates queue one first alert');
$first_job = $fatal_test_records['.jobs']['test-job-0'];
fatal_test_assert($first_job['payload']['url'] === 'http://staging.example.test/some/path',
    'queued alert captures the request URL without its query string');
fatal_test_assert($first_job['payload']['host'] === (gethostname() ?: ''),
    'queued alert captures the server hostname');
fatal_test_assert($first_job['payload']['file'] === 'ext/uri/widgets/(id)/route.inc',
    'queued alert keeps the route file path relative to the install root');
fatal_alert_enqueue($error, $start + 240);
fatal_test_assert(count($fatal_test_records['.jobs']) === 2, 'fifth repeat in 15 minutes queues escalation');
$incident = reset($fatal_test_records['.state']);
fatal_test_assert($incident['count'] === 5 && count($incident['events']) === 5,
    'occurrences persist even before email succeeds');
fatal_test_assert(empty($incident['notifications']['first']['sent_at'])
    && empty($incident['notifications']['escalation']['sent_at']),
    'queued alerts are not marked sent');
fatal_test_assert(!str_contains(json_encode([$incident, $fatal_test_records['.jobs']]), 'private form value')
    && !str_contains(json_encode([$incident, $fatal_test_records['.jobs']]), '/var/www/'),
    'diagnostic state and jobs omit form values and host paths');
$fatal_test_records['.jobs']['test-job-0']['status'] = 'failed';
fatal_alert_enqueue($error, $start + 300);
fatal_test_assert(count($fatal_test_records['.jobs']) === 3, 'failed delivery requeues on recurrence');
$fatal_test_mail_calls = 0;
require_once dirname(__DIR__) . '/modules/system/lib/fatal-error-alert.php';
$retry_job = $fatal_test_records['.jobs']['test-job-2'];
$retry_job['uuid'] = 'test-job-2';
try {
    fatal_error_alert_job($retry_job);
    fatal_test_assert(false, 'provider rejection must fail the job');
} catch (Exception $exception) {
    fatal_test_assert($exception->getMessage() === 'Fatal error alert email could not be sent',
        'mail failure remains retryable');
}
$incident = reset($fatal_test_records['.state']);
fatal_test_assert(empty($incident['notifications']['first']['sent_at']),
    'failed mail leaves delivery unsent');
fatal_error_alert_job($retry_job);
$incident = reset($fatal_test_records['.state']);
fatal_test_assert(!empty($incident['notifications']['first']['sent_at']),
    'successful retry records delivery');
fatal_alert_enqueue($error, $start + 31 * 86400);
$incident = reset($fatal_test_records['.state']);
fatal_test_assert($incident['count'] === 1 && count($incident['events']) === 1,
    'incident history expires after 30 days');
$expired_path = BASE_DIR . 'ext/data/.state/fatal-incident-expired';
file_put_contents($expired_path, json_encode(['last_at' => $start]));
fatal_test_assert(fatal_alert_prune($start + 31 * 86400) === 1
    && !is_file($expired_path), 'daily pruning removes expired history');
unlink(BASE_DIR . 'ext/data/.state/fatal-alert.lock');
rmdir(BASE_DIR . 'ext/data/.state');
rmdir(BASE_DIR . 'ext/data');
rmdir(BASE_DIR . 'ext');
rmdir(BASE_DIR);
echo "Fatal alert tests passed.\n";
