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

function agent_connector_fixture_authorize(array $source, array $_config, array $_context): array
{
    $GLOBALS['authorization_calls']++;
    return agent_artifact('agent.authorization', 1, [
        'status' => $GLOBALS['fixture_authorization'], 'action_digest' => $source['data']['action_digest'],
    ]);
}
function agent_connector_fixture_mutate(array $_source, array $_config, array $_context): array
{
    $GLOBALS['mutation_calls']++;
    return agent_artifact('fixture.action', 1, ['status' => 'completed']);
}
$authorization_calls = 0;
$mutation_calls = 0;
$fixture_authorization = 'authorized';
$tools['mutate'] = ['risk' => 'governed', 'connector' => 'fixture-mutate',
    'authorizer' => 'fixture-authorize', 'parameters' => $tools['inspect']['parameters']];
foreach (['after-reservation', 'after-remote-effect'] as $interruption) {
    $arguments = ['server' => $interruption];
    $identity = agent_action_identity($run_uuid, 'mutate', $arguments);
    agent_store_action($identity, $run_uuid, 'mutate', $arguments, 'executing', [], '');
    if ($interruption === 'after-remote-effect') {
        $mutation_calls++; // The old worker changed the remote state, then died before storing a receipt.
    }
    $before = $mutation_calls;
    $lock = agent_lock('run-' . $run_uuid);
    try {
        agent_recover_actions($run_uuid);
        $call = ['name' => 'mutate', 'call_id' => $interruption, 'arguments' => json_encode($arguments)];
        $unresolved = agent_execute_tool($run_uuid, $tools, $call, $context);
        agent_test_assert($unresolved['status'] === 'uncertain' && $unresolved['action_digest'] === $identity,
            'interrupted action returns an explicit unresolved outcome');
        agent_test_assert($mutation_calls === $before && $authorization_calls === 0,
            'recovery never reauthorizes or repeats a reserved mutation');
        agent_test_assert(data_read('.agent_actions', substr($identity, 0, 16))['attempts'] === 1,
            'recovery preserves the original attempt count');
    } finally {
        agent_unlock($lock);
    }
}
foreach (['authorized', 'denied'] as $fixture_authorization) {
    $arguments = ['server' => $fixture_authorization];
    $call = ['name' => 'mutate', 'call_id' => 'receipt', 'arguments' => json_encode($arguments)];
    $receipt = agent_execute_tool($run_uuid, $tools, $call, $context);
    $counts = [$mutation_calls, $authorization_calls];
    // A distinct run in the same retry lineage must reuse the action ledger too.
    data_create('.agent_runs', 'lineage-retry', data_read('.agent_runs', $run_uuid));
    $call['call_id'] = 'new-call';
    agent_test_assert(agent_execute_tool('lineage-retry', $tools, $call, $context) === $receipt,
        'successful and blocked receipts survive retry lineage');
    agent_test_assert([$mutation_calls, $authorization_calls] === $counts,
        'receipt replay never invokes authorization or the mutation connector');
}

$nested_schema = [
    'type' => 'object', 'properties' => [
        'since' => ['type' => 'integer'], 'enabled' => ['type' => 'boolean'],
        'at' => ['type' => 'string', 'format' => 'date-time'],
        'options' => ['type' => 'object', 'properties' => [
            'mode' => ['type' => 'string', 'enum' => ['safe', 'full']],
        ], 'required' => ['mode'], 'additionalProperties' => false],
        'items' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
            'weight' => ['type' => 'number'], 'empty' => ['type' => 'null'],
        ], 'required' => ['weight', 'empty'], 'additionalProperties' => false]],
    ], 'required' => ['since', 'enabled', 'at', 'options', 'items'], 'additionalProperties' => false,
];
$valid_arguments = '{"since":1788052800,"enabled":false,"at":"2026-09-13T10:20:30Z",'
    . '"options":{"mode":"safe"},"items":[{"weight":1.5,"empty":null}]}';
agent_validate_argument_schema($nested_schema);
agent_validate_arguments(json_decode($valid_arguments), $nested_schema);
$tools['validate'] = $tools['mutate'];
$tools['validate']['parameters'] = $nested_schema;
$invalid_arguments = ['[]', '{}', 'null', '{bad json'];
foreach ([['since', 'yesterday'], ['since', true], ['since', []], ['since', 1.5], ['enabled', 'false'],
    ['enabled', 0], ['options', []], ['options', (object)['mode' => 'unknown']],
    ['options', (object)['mode' => 'safe', 'extra' => true]], ['options', (object)[]],
    ['items', (object)[]], ['items', [false]], ['items', [(object)['weight' => '1', 'empty' => null]]],
    ['at', '2026-02-30T10:20:30Z'], ['at', 'yesterday'], ['at', '2026-09-13T25:00:00Z'],
    ['extra', 1]] as [$key, $value]) {
    $invalid = json_decode($valid_arguments);
    $invalid->$key = $value;
    $invalid_arguments[] = json_encode($invalid);
}
$counts = [$authorization_calls, $mutation_calls];
foreach ($invalid_arguments as $arguments_json) {
    try {
        agent_execute_tool($run_uuid, $tools, [
            'name' => 'validate', 'call_id' => 'invalid', 'arguments' => $arguments_json,
        ], $context);
        agent_test_assert(false, 'malformed arguments must fail locally');
    } catch (RuntimeException $error) {
        agent_test_assert([$authorization_calls, $mutation_calls] === $counts,
            'invalid arguments fail before authorization or connector execution');
    }
}
$definition_fixture = $GLOBALS['AGENT_TEST_DEFINITIONS']['scientific-writer'];
foreach ([['description' => null], ['properties' => null], ['required' => null],
    ['oneOf' => []], ['pattern' => '.*'], ['additionalProperties' => ['type' => 'string']],
    ['properties' => ['nested' => ['type' => 'array']]], ['type' => 'unknown'],
    ['properties' => ['nested' => ['type' => 'string', 'format' => 'email']]]] as $unsupported) {
    $definition_fixture['tools'] = ['inspect' => $tools['inspect']];
    $definition_fixture['tools']['inspect']['parameters'] = array_replace($tools['inspect']['parameters'], $unsupported);
    try {
        agent_validate_definition($definition_fixture, 'scientific-writer');
        agent_test_assert(false, 'unsupported schemas must fail while loading the definition');
    } catch (RuntimeException $error) {
        // Expected: unsupported schema cannot reach a provider or connector.
    }
}
agent_test_assert(agent_canonical_json(['value' => (object)[]]) !== agent_canonical_json(['value' => []]),
    'canonical identity preserves empty objects versus arrays');
agent_test_assert(agent_redact((object)['password' => 'private'])->password === '[REDACTED]',
    'nested JSON objects retain redaction');

echo "Agent kernel tests passed.\n";
