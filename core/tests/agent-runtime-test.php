<?php

define('BASE_DIR', dirname(__DIR__, 2) . '/');
$test_data = [];
$test_jobs = [];

function load_library($_name): void {}
function load_libraries($_names): void {}
function data_exists($resource, $uuid = ''): bool
{
    global $test_data;
    return $uuid === '' ? isset($test_data[$resource]) : isset($test_data[$resource][$uuid]);
}
function data_create_resource($resource, $meta): bool
{
    global $test_data;
    $test_data[$resource] = ['.meta' => $meta];
    return true;
}
function data_create($resource, $uuid, $data): bool
{
    global $test_data;
    if (isset($test_data[$resource][$uuid])) {
        return false;
    }
    $test_data[$resource][$uuid] = $data + ['uuid' => $uuid];
    return true;
}
function data_read($resource, $uuid = null)
{
    global $test_data;
    return $uuid === null || $uuid === '' ? ($test_data[$resource] ?? []) : ($test_data[$resource][$uuid] ?? null);
}
function data_update($resource, $uuid, $changes): bool
{
    global $test_data;
    if (!isset($test_data[$resource][$uuid])) {
        return false;
    }
    $test_data[$resource][$uuid] = array_merge($test_data[$resource][$uuid], $changes);
    return true;
}
function job_enqueue($type, $payload = [], $options = []): string
{
    global $test_jobs;
    $uuid = (string)($options['uuid'] ?? uniqid());
    $test_jobs[$uuid] = compact('type', 'payload', 'options');
    return $uuid;
}
function env($_key, $default = '') { return $default; }
function email_result($data): array
{
    $response = ($data['request'])('/emails', $data, $data);
    return [
        'success' => !empty($response['success']),
        'id' => (string)($response['body']['id'] ?? ''),
        'error' => $response['error'] ?? null,
    ];
}

require_once BASE_DIR . 'core/modules/agent/lib/agent-runtime.php';

function agent_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$tool_calls = 0;
$inspect = function (array $arguments) use (&$tool_calls): array {
    $tool_calls++;
    return [
        'server' => (string)$arguments['server'],
        'overall' => 'ok',
        'findings' => [],
        'observed_at' => time(),
    ];
};
$schema = [
    'type' => 'object',
    'properties' => [
        'subject' => ['type' => 'string'],
        'overall_state' => ['type' => 'string'],
        'completed_work' => ['type' => 'array', 'items' => ['type' => 'string']],
        'blocked_work' => ['type' => 'array', 'items' => ['type' => 'string']],
        'production_recommendations' => ['type' => 'array', 'items' => ['type' => 'string']],
        'colleague_requests' => ['type' => 'array', 'items' => ['type' => 'string']],
        'targets' => ['type' => 'array', 'items' => ['type' => 'object']],
    ],
    'required' => [
        'subject', 'overall_state', 'completed_work', 'blocked_work',
        'production_recommendations', 'colleague_requests', 'targets',
    ],
    'additionalProperties' => false,
];
$GLOBALS['AGENT_TEST_DEFINITIONS']['durable-agent'] = [
    'id' => 'durable-agent',
    'name' => 'Durable Agent',
    'version' => '2.0.0',
    'instructions' => __FILE__,
    'instruction_files' => [__FILE__],
    'timezone' => 'UTC',
    'model' => 'test-model',
    'targets' => [['scope' => 'stage', 'identity' => 'stage.test', 'authority' => 'autonomous_remediation']],
    'report' => [
        'targets_path' => 'targets',
        'scope_resource' => '.unused',
        'source' => [
            'scope_resource' => '.health_environments',
            'report_resource' => '.health_reports',
            'scope_field' => 'environment',
            'identity_field' => 'server',
            'report_uuid_field' => 'last_report_uuid',
        ],
    ],
    'report_delivery' => [
        'items_key' => 'briefings',
        'item_key' => 'id',
        'subject_field' => 'subject',
        'html_field' => 'html',
        'recipient_default' => 'test@example.com',
        'service' => 'resend',
        'shadow_triggers' => [],
    ],
    'tools' => [
        'inspect_host_health' => [
            'description' => 'Collect health.',
            'risk' => 'read_only',
            'parameters' => [
                'type' => 'object',
                'properties' => ['server' => ['type' => 'string']],
                'required' => ['server'],
                'additionalProperties' => false,
            ],
            'execute' => $inspect,
        ],
    ],
    'pipeline' => [
        'version' => 2,
        'input' => [
            ['id' => 'history', 'type' => 'resource_snapshot'],
            ['id' => 'observations', 'type' => 'connector_collect', 'from' => 'history', 'config' => ['tool' => 'inspect_host_health']],
        ],
        'agent' => [[
            'id' => 'decision', 'type' => 'model', 'from' => 'observations',
            'instructions' => __FILE__, 'output_schema' => $schema,
        ]],
        'output' => [
            ['id' => 'grounded', 'type' => 'evidence_guard', 'from' => 'decision'],
            ['id' => 'briefing', 'type' => 'render_report', 'from' => 'grounded'],
            ['id' => 'delivery', 'type' => 'deliver', 'from' => 'briefing'],
        ],
        'result_from' => 'grounded',
        'delivery_from' => 'delivery',
    ],
];

$now = strtotime('2026-08-29 08:00:00 UTC');
$test_data['.health_environments'] = [[
    'environment' => 'stage', 'server' => 'stage.test', 'last_report_uuid' => 'report-1',
]];
$test_data['.health_reports']['report-1'] = [
    'uuid' => 'report-1', 'environment' => 'stage', 'server' => 'stage.test',
    'received_at' => $now, 'generated_at' => $now, 'overall' => 'ok', 'audit' => ['findings' => []],
];

$model_calls = 0;
$openai_request = function (array $request) use (&$model_calls): array {
    $model_calls++;
    agent_test_assert(($request['text']['format']['type'] ?? '') === 'json_schema', 'model uses strict output schema');
    return [
        'id' => 'response-' . $model_calls,
        'status' => 'completed',
        'usage' => ['input_tokens' => 10, 'output_tokens' => 10, 'total_tokens' => 20],
        'output' => [],
        'output_text' => json_encode([
            'subject' => 'Daily briefing',
            'overall_state' => 'Healthy',
            'completed_work' => [],
            'blocked_work' => [],
            'production_recommendations' => [],
            'colleague_requests' => [],
            'targets' => [[
                'identity' => 'stage.test',
                'state' => 'healthy',
                'actions_completed' => [],
            ]],
        ]),
    ];
};
$email_calls = 0;
$email_request = function () use (&$email_calls): array {
    $email_calls++;
    return $email_calls === 1
        ? ['success' => false, 'error' => 'temporary provider failure']
        : ['success' => true, 'body' => ['id' => 'email-accepted']];
};

$run_uuid = agent_enqueue('durable-agent', $now, ['trigger' => 'scheduled']);
$first = agent_run($run_uuid, ['openai_request' => $openai_request, 'email_request' => $email_request]);
agent_test_assert(($first['status'] ?? '') === 'failed', 'delivery rejection fails the run');
agent_test_assert($tool_calls === 1 && $model_calls === 1 && $email_calls === 1, 'first run executes every required step once');

$retry_uuid = agent_retry($run_uuid);
agent_test_assert($retry_uuid === $run_uuid, 'retry preserves the scheduled occurrence lineage');
$second = agent_run($run_uuid, ['openai_request' => $openai_request, 'email_request' => $email_request]);
agent_test_assert(
    ($second['status'] ?? '') === 'completed',
    'delivery-only retry completes: ' . ($second['failure_reason'] ?? 'unknown')
);
agent_test_assert($tool_calls === 1, 'delivery retry does not recollect observations');
agent_test_assert($model_calls === 1, 'delivery retry does not call the model again');
agent_test_assert($email_calls === 2, 'delivery retry resumes exactly at delivery');
agent_test_assert(($second['failure_reason'] ?? 'not-cleared') === '', 'successful retry clears stale failure state');

$steps = array_values(array_filter(
    $test_data['.agent_steps'],
    fn($step, $key) => $key !== '.meta' && ($step['run_uuid'] ?? '') === $run_uuid,
    ARRAY_FILTER_USE_BOTH
));
agent_test_assert(count($steps) === 6, 'every v2 step has one durable ledger record');

echo "Agent runtime tests passed.\n";
