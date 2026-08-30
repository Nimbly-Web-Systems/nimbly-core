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
    $records = $GLOBALS['agent_test_data'][$resource] ?? [];
    if (is_string($selector)) {
        return $records[$selector] ?? null;
    }
    $records = array_filter($records, fn($_record, $uuid) => $uuid !== '.meta', ARRAY_FILTER_USE_BOTH);
    if (is_array($selector)) {
        $records = array_filter($records, function ($record) use ($selector) {
            foreach ($selector as $key => $value) {
                if (($record[$key] ?? null) !== $value) {
                    return false;
                }
            }
            return true;
        });
    }
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

$run_uuid = agent_enqueue('scientific-writer', 1788052800, ['idempotency_suffix' => 'kernel-test']);
agent_test_assert($agent_test_jobs[0]['type'] === 'agent', 'the kernel is its own queue entry point');
$failed = agent_run($run_uuid);
agent_test_assert($failed['status'] === 'failed', 'transient delivery failure is durably recorded');
agent_retry($run_uuid);
$completed = agent_run($run_uuid);
agent_test_assert($completed['status'] === 'completed', 'the failed run resumes successfully');
agent_test_assert($completed['structured_result']['title'] === 'QUANTUM MOSS', 'generic artifacts carry unrelated agent data');
agent_test_assert($agent_test_calls === ['input' => 1, 'transform' => 1, 'delivery' => 2],
    'only the failed delivery connector is repeated');

echo "Agent kernel tests passed.\n";
