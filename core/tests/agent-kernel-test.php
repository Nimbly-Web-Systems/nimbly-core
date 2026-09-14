<?php

define('BASE_DIR', dirname(__DIR__, 2) . '/');

$agent_test_data = [];
$agent_test_calls = ['input' => 0, 'transform' => 0, 'delivery' => 0];
$agent_test_jobs = [];

function load_library($_name): void {}
function job_enqueue($type, $payload, $options): bool
{
    $GLOBALS['agent_test_jobs'][] = compact('type', 'payload', 'options');
    return true;
}
function data_exists($resource, $uuid): bool
{
    return isset($GLOBALS['agent_test_data'][$resource][$uuid]);
}
function data_create_resource($resource, $meta): bool
{
    $GLOBALS['agent_test_data'][$resource]['.meta'] = $meta;
    return true;
}
function data_create($resource, $uuid, $record): bool
{
    if (isset($GLOBALS['agent_test_data'][$resource][$uuid])) {
        return false;
    }
    $record['uuid'] = $uuid;
    $GLOBALS['agent_test_data'][$resource][$uuid] = $record;
    return true;
}
function data_update($resource, $uuid, $changes): bool
{
    if (!isset($GLOBALS['agent_test_data'][$resource][$uuid])) {
        return false;
    }
    $GLOBALS['agent_test_data'][$resource][$uuid] = array_merge(
        $GLOBALS['agent_test_data'][$resource][$uuid], $changes
    );
    return true;
}
function data_read($resource, $selector = null)
{
    if (is_array($selector)) {
        throw new RuntimeException('data_read UUID must not be a filter array');
    }
    $records = $GLOBALS['agent_test_data'][$resource] ?? [];
    if (is_string($selector)) {
        return $records[$selector] ?? null;
    }
    $records = array_filter($records, fn($_record, $uuid) => $uuid !== '.meta', ARRAY_FILTER_USE_BOTH);
    return array_values($records);
}

require_once BASE_DIR . 'core/modules/agent/lib/agent.php';

function agent_connector_fixture_input(array $_source, array $_config, array $_context): array
{
    $GLOBALS['agent_test_calls']['input']++;
    return agent_artifact('fixture.input', 1, ['topic' => 'quantum moss']);
}
function agent_connector_fixture_transform(array $source, array $_config, array $_context): array
{
    $GLOBALS['agent_test_calls']['transform']++;
    return agent_artifact('fixture.article', 1, [
        'title' => strtoupper(agent_artifact_data($source)['topic']),
    ]);
}
function agent_connector_fixture_delivery(array $_source, array $_config, array $_context): array
{
    $GLOBALS['agent_test_calls']['delivery']++;
    if ($GLOBALS['agent_test_calls']['delivery'] === 1) {
        throw new AgentTransientException('Temporary fixture delivery failure');
    }
    return agent_artifact('delivery.receipt', 1, ['success' => true, 'deliveries' => ['draft' => ['accepted' => true]]]);
}
function agent_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$instructions = __DIR__ . '/fixtures/agent-instructions.md';
$GLOBALS['AGENT_TEST_DEFINITIONS']['scientific-writer'] = [
    'id' => 'scientific-writer', 'version' => '1.0.0', 'instructions' => $instructions,
    'pipeline' => [
        'version' => 3,
        'input' => [['id' => 'research', 'connector' => 'fixture-input']],
        'agent' => [['id' => 'article', 'connector' => 'fixture-transform', 'from' => 'research']],
        'output' => [['id' => 'delivery', 'connector' => 'fixture-delivery', 'from' => 'article']],
        'result_from' => 'article', 'delivery_from' => 'delivery',
    ],
    'tools' => [],
];

$enqueue = agent_enqueue_result('scientific-writer', 1788052800, ['idempotency_suffix' => 'kernel-test']);
$run_uuid = $enqueue['run_uuid'];
agent_test_assert($enqueue['created'], 'the first enqueue reports a newly created run');
$duplicate = agent_enqueue_result('scientific-writer', 1788052800, ['idempotency_suffix' => 'kernel-test']);
agent_test_assert(!$duplicate['created'] && $duplicate['run_uuid'] === $run_uuid,
    'a duplicate enqueue reports the existing run');
agent_test_assert($agent_test_jobs[0]['type'] === 'agent', 'the kernel is its own queue entry point');
$failed = agent_run($run_uuid);
agent_test_assert($failed['status'] === 'failed', 'transient delivery failure is durably recorded');
agent_retry($run_uuid);
$completed = agent_run($run_uuid);
agent_test_assert($completed['status'] === 'completed', 'the failed run resumes successfully');
agent_test_assert($completed['structured_result']['title'] === 'QUANTUM MOSS', 'generic artifacts carry unrelated agent data');
agent_test_assert($agent_test_calls === ['input' => 1, 'transform' => 1, 'delivery' => 2],
    'only the failed delivery connector is repeated');

$saved_runs = $agent_test_data['.agent_runs'];
$agent_test_data['.agent_runs'] = [];
$overdue = agent_watchdog_status('scientific-writer',
    (new DateTimeImmutable('today 23:59:00', new DateTimeZone('UTC')))->getTimestamp());
agent_test_assert(!$overdue['healthy'] && $overdue['state'] === 'overdue',
    'manual status returns the unavailable state after a missed deadline');
$stale_at = (new DateTimeImmutable('today 23:59:00', new DateTimeZone('UTC')))->getTimestamp();
$agent_test_data['.agent_runs'] = ['stale' => [
    'agent_id' => 'scientific-writer', 'status' => 'completed',
    'scheduled_at' => $stale_at - 86400,
]];
agent_test_assert(!agent_watchdog_status('scientific-writer', $stale_at)['healthy'],
    'yesterday\'s completed run does not hide a missed run today');
$agent_test_data['.agent_runs'] = ['failed-today' => [
    'agent_id' => 'scientific-writer', 'status' => 'failed',
    'scheduled_at' => $stale_at - 60,
]];
agent_test_assert(agent_watchdog_status('scientific-writer', $stale_at)['state'] === 'failed',
    'failed current run is unavailable');
$agent_test_data['.agent_runs'] = $saved_runs;
agent_test_assert(agent_sc(['agent' => 'scientific-writer']) === 'ok'
    && http_response_code() === 200, 'manual status route returns 200 after completed run');

function agent_connector_fixture_inspect(array $source, array $_config, array $_context): array
{
    $GLOBALS['inspection_calls']++;
    return agent_artifact('fixture.observation', 1, [
        'server' => $source['data']['arguments']['server'], 'active' => $GLOBALS['service_active'],
    ]);
}
$inspection_calls = 0;
$service_active = false;
$tools = ['inspect' => ['risk' => 'read_only', 'connector' => 'fixture-inspect', 'parameters' => [
    'type' => 'object', 'properties' => ['server' => ['type' => 'string']],
    'required' => ['server'], 'additionalProperties' => false,
]]];
$call = ['name' => 'inspect', 'call_id' => 'first', 'arguments' => '{"server":"fixture"}'];
$context = ['step' => ['id' => 'collect']];
$first = agent_execute_tool($run_uuid, $tools, $call, $context);
$service_active = true;
$call['call_id'] = 'second';
$second = agent_execute_tool($run_uuid, $tools, $call, $context);
agent_test_assert(!$first['active'] && $second['active'] && $inspection_calls === 2,
    'a new inspection sees changed service state');
agent_test_assert(agent_execute_tool($run_uuid, $tools, $call, $context) === $second && $inspection_calls === 2,
    'replaying a logical inspection returns its original observation');
agent_execute_tool($run_uuid, $tools, $call, ['step' => ['id' => 'verify']]);
agent_test_assert($inspection_calls === 3, 'separate collection steps cannot collide');
agent_test_assert(agent_latest_tool_results($run_uuid, 'inspect')[0]['active'],
    'current evidence uses the latest successful observation');
agent_test_assert(count(array_filter(data_read('.agent_events'), fn($event) =>
    $event['type'] === 'tool_completed' && $event['payload']['tool'] === 'inspect')) === 3,
    'earlier observations remain in history');

echo "Agent kernel tests passed.\n";
