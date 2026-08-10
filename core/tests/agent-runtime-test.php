<?php

define('BASE_DIR', realpath(__DIR__ . '/..' . '/..') . '/');

$test_data = [];
$test_jobs = [];

function load_library($_name) {}
function load_libraries($_names) {}
function data_exists($resource, $uuid = '')
{
    global $test_data;
    return $uuid === '' ? isset($test_data[$resource]) : isset($test_data[$resource][$uuid]);
}
function data_create_resource($resource, $meta)
{
    global $test_data;
    $test_data[$resource] = ['.meta' => $meta];
    return true;
}
function data_create($resource, $uuid, $data)
{
    global $test_data;
    $test_data[$resource] = $test_data[$resource] ?? [];
    if (isset($test_data[$resource][$uuid])) {
        return false;
    }
    $test_data[$resource][$uuid] = array_merge($data, ['uuid' => $uuid]);
    return true;
}
function data_read($resource, $uuid = null)
{
    global $test_data;
    return $uuid === null || $uuid === '' ? ($test_data[$resource] ?? []) : ($test_data[$resource][$uuid] ?? null);
}
function data_update($resource, $uuid, $changes)
{
    global $test_data;
    if (!isset($test_data[$resource][$uuid])) {
        return false;
    }
    $test_data[$resource][$uuid] = array_merge($test_data[$resource][$uuid], $changes);
    return true;
}
function job_enqueue($type, $payload = [], $options = [])
{
    global $test_jobs;
    $uuid = $options['uuid'] ?? uniqid();
    $test_jobs[$uuid] = compact('type', 'payload', 'options');
    return $uuid;
}
function env($_key, $default = '') { return $default; }
function email_result($data)
{
    if (!empty($data['request'])) {
        $response = $data['request']('/emails', $data, $data);
        return ['success' => !empty($response['success']), 'id' => $response['body']['id'] ?? '', 'error' => $response['error'] ?? null];
    }
    return ['success' => false, 'id' => '', 'error' => 'No test request'];
}

require_once BASE_DIR . 'core/modules/agent/lib/agent-runtime.php';
require_once BASE_DIR . 'core/modules/agent/lib/agent-gateway.php';

function agent_test_assert($condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function agent_test_prepare_input(array $_run, array $_dependencies): array
{
    return [
        'source_report_uuids' => ['stage-report', 'prod-report'],
        'input' => [['role' => 'user', 'content' => [['type' => 'input_text', 'text' => '{"review":true}']]]],
    ];
}

function agent_test_inspect_service(array $arguments, array $dependencies): array
{
    $command = ['/usr/bin/timeout', '30', '/usr/bin/ssh', '-T', 'agent@' . $arguments['server'], 'inspect_service', $arguments['service']];
    $result = ($dependencies['process_runner'])($command);
    $decoded = json_decode($result['stdout'], true);
    return array_merge(['server' => $arguments['server']], $decoded);
}

function agent_test_validate_result(array $result): array
{
    if (count($result['environments'] ?? []) !== 2) {
        throw new RuntimeException('Expected two environments');
    }
    return $result;
}

function agent_test_deliver(array $result, string $run_uuid, array $dependencies): array
{
    $deliveries = [];
    foreach ($result['environments'] as $environment) {
        $response = ($dependencies['email_request'])('/emails', [
            'idempotency_key' => $run_uuid . ':' . $environment['environment'],
        ]);
        $deliveries[$environment['environment']] = [
            'accepted' => !empty($response['success']),
            'provider_message_id' => $response['body']['id'] ?? '',
        ];
    }
    return ['success' => count($deliveries) === 2, 'environments' => $deliveries];
}

$GLOBALS['AGENT_TEST_DEFINITIONS']['test-agent'] = [
    'id' => 'test-agent',
    'version' => '1.0.0',
    'instructions' => __FILE__,
    'timezone' => 'America/Sao_Paulo',
    'deadline_at' => '09:30',
    'model' => 'gpt-5.6-terra',
    'reasoning_effort' => 'medium',
    'pricing' => ['input_per_million' => 2.5, 'cached_input_per_million' => 0.25, 'output_per_million' => 15],
    'tools' => [
        'inspect_service' => [
            'description' => 'Inspect a test service.',
            'risk' => 'read_only',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'server' => ['type' => 'string', 'enum' => ['nimbly1.stage', 'nimbly2.prod']],
                    'service' => ['type' => 'string', 'enum' => ['apache2']],
                ],
                'required' => ['server', 'service'],
                'additionalProperties' => false,
            ],
            'execute' => 'agent_test_inspect_service',
        ],
    ],
    'prepare_input' => 'agent_test_prepare_input',
    'validate_result' => 'agent_test_validate_result',
    'deliver' => 'agent_test_deliver',
];

$now = (new DateTimeImmutable('2026-08-10 08:00:00', new DateTimeZone('America/Sao_Paulo')))->getTimestamp();
$test_data['.infra_health_environments'] = [
    'stage' => ['environment' => 'stage', 'server' => 'nimbly1.stage', 'last_report_uuid' => 'stage-report', 'last_received_at' => $now - 60, 'late_after' => 93600],
    'prod' => ['environment' => 'prod', 'server' => 'nimbly2.prod', 'last_report_uuid' => 'prod-report', 'last_received_at' => $now - 60, 'late_after' => 93600],
];
$test_data['.infra_health_reports'] = [
    'stage-report' => ['uuid' => 'stage-report', 'received_at' => $now - 60, 'generated_at' => $now - 70, 'overall' => 'ok', 'audit' => ['findings' => []]],
    'prod-report' => ['uuid' => 'prod-report', 'received_at' => $now - 60, 'generated_at' => $now - 70, 'overall' => 'warning', 'audit' => ['findings' => [[
        'id' => 'system:reboot-required', 'severity' => 'warning', 'scope' => 'host', 'evidence' => 'Planned maintenance required',
    ]]]],
];

$run_uuid = agent_enqueue('test-agent', $now, ['trigger' => 'test']);
$duplicate_uuid = agent_enqueue('test-agent', $now, ['trigger' => 'test']);
agent_test_assert($run_uuid === $duplicate_uuid, 'scheduled enqueue is idempotent');
agent_test_assert(count($test_data['.agent_runs']) === 2, 'one run plus meta exists');
agent_test_assert(count($test_jobs) === 1, 'one deterministic job exists');

$manual_uuid = agent_enqueue('test-agent', $now, [
    'trigger' => 'manual',
    'idempotency_suffix' => 'manual-shadow-1',
]);
$manual_duplicate_uuid = agent_enqueue('test-agent', $now, [
    'trigger' => 'manual',
    'idempotency_suffix' => 'manual-shadow-1',
]);
agent_test_assert($manual_uuid === $manual_duplicate_uuid, 'manual enqueue is idempotent for its explicit key');
agent_test_assert($manual_uuid !== $run_uuid, 'manual enqueue does not consume the scheduled occurrence');

$invalid_suffix_denied = false;
try {
    agent_enqueue('test-agent', $now, ['idempotency_suffix' => '../invalid']);
} catch (InvalidArgumentException) {
    $invalid_suffix_denied = true;
}
agent_test_assert($invalid_suffix_denied, 'manual idempotency suffix is strictly validated');

$openai_calls = 0;
$openai_request = function (array $request) use (&$openai_calls) {
    $openai_calls++;
    agent_test_assert($request['store'] === false, 'Responses state storage is disabled');
    agent_test_assert($request['tools'][0]['strict'] === true, 'tool schema is strict');
    if ($openai_calls === 1) {
        return [
            'id' => 'resp-tools', 'status' => 'completed',
            'usage' => ['input_tokens' => 1000, 'output_tokens' => 100, 'total_tokens' => 1100, 'input_tokens_details' => ['cached_tokens' => 200]],
            'output' => [
                ['type' => 'function_call', 'call_id' => 'call-stage', 'name' => 'inspect_service', 'arguments' => '{"server":"nimbly1.stage","service":"apache2"}'],
                ['type' => 'function_call', 'call_id' => 'call-prod', 'name' => 'inspect_service', 'arguments' => '{"server":"nimbly2.prod","service":"apache2"}'],
            ],
        ];
    }
    return [
        'id' => 'resp-final', 'status' => 'completed',
        'usage' => ['input_tokens' => 500, 'output_tokens' => 200, 'total_tokens' => 700, 'input_tokens_details' => ['cached_tokens' => 100]],
        'output_text' => json_encode(['environments' => [
            [
                'environment' => 'stage', 'server' => 'nimbly1.stage', 'source_report_uuid' => 'stage-report', 'review_status' => 'reviewed',
                'automatically_fixed' => [], 'still_needs_fixing' => [], 'what_you_can_do' => [], 'verification' => ['active' => true],
            ],
            [
                'environment' => 'prod', 'server' => 'nimbly2.prod', 'source_report_uuid' => 'prod-report', 'review_status' => 'reviewed',
                'automatically_fixed' => [], 'still_needs_fixing' => ['A reboot requires approval.'], 'what_you_can_do' => ['Review the maintenance window.'], 'verification' => ['active' => true],
            ],
        ]]),
        'output' => [],
    ];
};

$process_calls = 0;
$process_runner = function (array $command) use (&$process_calls) {
    $process_calls++;
    agent_test_assert($command[0] === '/usr/bin/timeout' && $command[2] === '/usr/bin/ssh', 'tool uses fixed timeout and SSH binaries');
    agent_test_assert(!in_array('sh', $command, true) && !in_array('bash', $command, true), 'tool never invokes a shell');
    $service = end($command);
    return ['exit_code' => 0, 'stdout' => json_encode(['service' => $service, 'active' => true, 'sub_state' => 'running', 'observed_at' => time(), 'evidence' => 'active']), 'stderr' => ''];
};

$email_calls = 0;
$email_request = function ($_path, $payload) use (&$email_calls) {
    $email_calls++;
    agent_test_assert(str_contains($payload['idempotency_key'], ':'), 'email has an idempotency key');
    return ['success' => true, 'body' => ['id' => 'email-' . $email_calls], 'error' => null];
};

$result = agent_run($run_uuid, [
    'openai_request' => $openai_request,
    'process_runner' => $process_runner,
    'email_request' => $email_request,
]);

agent_test_assert($result['status'] === 'completed', 'end-to-end run completes');
agent_test_assert($process_calls === 2, 'both environments are inspected exactly once');
agent_test_assert($email_calls === 2, 'both environment reports are accepted');
agent_test_assert(($result['usage']['total_tokens'] ?? 0) === 1800, 'usage is accumulated');
agent_test_assert(($result['estimated_cost_usd'] ?? 0) > 0, 'cost is estimated');
agent_test_assert(count($result['source_report_uuids']) === 2, 'source report UUIDs are recorded');

$rerun = agent_run($run_uuid, ['openai_request' => $openai_request]);
agent_test_assert($rerun['status'] === 'completed' && $openai_calls === 2, 'terminal rerun has no side effects');

$immutable = false;
try {
    agent_update_run($run_uuid, ['failure_reason' => 'changed']);
} catch (RuntimeException) {
    $immutable = true;
}
agent_test_assert($immutable, 'terminal run is immutable');

$gateway = agent_gateway_execute('inspect_service apache2', function (array $command) {
    agent_test_assert($command === [
        '/usr/bin/systemctl', 'show', 'apache2',
        '--property=Id,ActiveState,SubState,LoadState,UnitFileState',
        '--no-pager',
    ], 'remote gateway maps to one fixed argv command');
    return [
        'exit_code' => 0,
        'stdout' => "Id=apache2.service\nActiveState=active\nSubState=running\nLoadState=loaded\nUnitFileState=enabled\n",
        'stderr' => '',
    ];
});
agent_test_assert($gateway['active'] === true && $gateway['service'] === 'apache2', 'gateway returns structured evidence');

$denied = 0;
foreach (['inspect_service ssh', 'inspect_service apache2 extra', 'sh -c id', ''] as $command) {
    try {
        agent_gateway_execute($command, fn() => []);
    } catch (RuntimeException) {
        $denied++;
    }
}
agent_test_assert($denied === 4, 'gateway rejects unknown and shell-shaped commands');

echo "Agent runtime tests passed.\n";
