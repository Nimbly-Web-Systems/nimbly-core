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

$test_data['.agent_runs'][$run_uuid]['status'] = 'failed';
$terminal_job_count = count($test_jobs);
agent_test_assert(agent_enqueue('test-agent', $now, ['trigger' => 'test']) === $run_uuid, 'terminal enqueue returns the existing run');
agent_test_assert(count($test_jobs) === $terminal_job_count, 'terminal run is not re-enqueued');
$test_data['.agent_runs'][$run_uuid]['status'] = 'scheduled';

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

$event_uuid = agent_enqueue('test-agent', $now, [
    'trigger' => 'uptime',
    'target' => 'nimbly2.prod',
    'read_only' => true,
    'idempotency_suffix' => 'uptime-incident-1',
    'event_context' => ['incident_id' => 'incident-1', 'monitor_id' => 'monitor-2'],
]);
agent_test_assert(
    ($test_data['.agent_runs'][$event_uuid]['event_context']['incident_id'] ?? '') === 'incident-1',
    'event context is persisted on the scoped run'
);

$failed_uuid = 'failed-scheduled';
$test_data['.agent_runs'][$failed_uuid] = [
    'agent_id' => 'test-agent', 'agent_version' => '1.0.0', 'trigger' => 'scheduled',
    'scheduled_at' => $now, 'scheduled_occurrence' => '2026-08-10',
    'timezone' => 'America/Sao_Paulo', 'status' => 'failed',
    'idempotency_key' => 'test-agent:2026-08-10',
];
$retry_uuid = agent_retry($failed_uuid);
agent_test_assert(($test_data['.agent_runs'][$retry_uuid]['retry_of'] ?? '') === $failed_uuid, 'failed scheduled run creates an auditable retry');
agent_test_assert(($test_data['.agent_runs'][$retry_uuid]['trigger'] ?? '') === 'scheduled_retry', 'retry retains scheduled watchdog authority');
$test_data['.agent_runs'][$retry_uuid]['status'] = 'completed';
agent_test_assert(agent_watchdog_status('test-agent', $now)['state'] === 'completed', 'successful retry restores the same occurrence watchdog');

$invalid_suffix_denied = false;
try {
    agent_enqueue('test-agent', $now, ['idempotency_suffix' => '../invalid']);
} catch (InvalidArgumentException) {
    $invalid_suffix_denied = true;
}
agent_test_assert($invalid_suffix_denied, 'manual idempotency suffix is strictly validated');

$governed_executions = 0;
$governed_arguments = ['server' => 'nimbly1.stage', 'command' => 'systemctl start apache2'];
$governed_tools = [
    'remediate' => [
        'description' => 'Test a governed action.',
        'risk' => 'governed',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'server' => ['type' => 'string', 'enum' => ['nimbly1.stage']],
                'command' => ['type' => 'string'],
            ],
            'required' => ['server', 'command'],
            'additionalProperties' => false,
        ],
        'authorize' => function (array $arguments, string $authorized_run_uuid) {
            return [
                'status' => 'authorized',
                'action_digest' => agent_tool_action_digest($authorized_run_uuid, 'remediate', $arguments),
                'target' => $arguments['server'],
                'authorized_at' => time(),
                'expires_at' => time() + 60,
                'reason' => 'Bounded reversible test action',
            ];
        },
        'execute' => function (array $_arguments, array $dependencies) use (&$governed_executions) {
            $governed_executions++;
            agent_test_assert(($dependencies['authorization']['status'] ?? '') === 'authorized', 'executor receives the exact authorization');
            return ['status' => 'executed'];
        },
    ],
];
$governed_call = ['call_id' => 'governed-1', 'name' => 'remediate', 'arguments' => json_encode($governed_arguments)];
$governed_result = agent_execute_tool($manual_uuid, $governed_tools, $governed_call, []);
agent_test_assert(($governed_result['status'] ?? '') === 'executed' && $governed_executions === 1, 'authorized governed action executes once');

$replayed_result = agent_execute_tool($manual_uuid, $governed_tools, $governed_call, []);
agent_test_assert(($replayed_result['status'] ?? '') === 'executed' && $governed_executions === 1, 'governed action replay returns recorded evidence without re-execution');

$floor_tools = $governed_tools;
$floor_tools['remediate']['authorize'] = function (array $arguments, string $authorized_run_uuid) {
    return [
        'status' => 'human_approval_required',
        'action_digest' => agent_tool_action_digest($authorized_run_uuid, 'remediate', $arguments),
        'target' => $arguments['server'],
        'expires_at' => time() + 300,
        'reason' => 'Credential changes require a human',
    ];
};
$floor_call = ['call_id' => 'governed-floor', 'name' => 'remediate', 'arguments' => json_encode([
    'server' => 'nimbly1.stage', 'command' => 'change ssh authorization',
])];
$floor_result = agent_execute_tool($run_uuid, $floor_tools, $floor_call, []);
agent_test_assert(($floor_result['status'] ?? '') === 'human_approval_required' && $governed_executions === 1, 'human approval floor never executes the action');
agent_test_assert(agent_redact(['input_tokens' => 12, 'api_token' => 'secret']) === [
    'input_tokens' => 12,
    'api_token' => '[REDACTED]',
], 'usage counters remain auditable while credentials are redacted');

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
            ],
            [
                'environment' => 'prod', 'server' => 'nimbly2.prod', 'source_report_uuid' => 'prod-report', 'review_status' => 'reviewed',
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

require_once BASE_DIR . 'core/modules/agent/lib/agent-run.php';
$test_data['.agent_runs']['failed-handler-run'] = [
    'status' => 'failed',
    'failure_reason' => 'Specific safe validation failure',
];
$handler_reason = '';
try {
    agent_run_job(['payload' => ['run_uuid' => 'failed-handler-run'], 'attempts' => 3, 'max_attempts' => 3]);
} catch (RuntimeException $error) {
    $handler_reason = $error->getMessage();
}
agent_test_assert(
    $handler_reason === 'Specific safe validation failure',
    'failed agent jobs expose the safe underlying run reason'
);

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

$host_health = agent_gateway_execute('inspect_host_health', function (array $command) {
    agent_test_assert($command === ['/usr/bin/sudo', '-n', '/usr/local/bin/nimbly-host-audit', '--format=json'], 'host audit maps to one fixed privileged command');
    return ['exit_code' => 2, 'stdout' => json_encode([
        'audit_version' => '1.2.3', 'generated_at' => '2026-08-23T12:00:00Z',
        'overall' => 'critical', 'summary' => ['critical' => 1], 'findings' => [[
            'id' => 'jobs:failed', 'severity' => 'critical', 'scope' => 'jobs', 'title' => 'Job failed',
            'evidence' => 'One job failed', 'count' => 2, 'project' => 'Example',
            'first_seen' => '2026-08-13T10:00:00+00:00', 'last_seen' => '2026-08-13T10:05:00+00:00',
        ], [
            'id' => 'runtime:php-version', 'severity' => 'warning', 'scope' => 'host',
            'title' => 'Web PHP version differs from infrastructure policy',
            'expected' => 'Ubuntu default PHP 8.5', 'observed' => '8.2',
        ]], 'checks' => ['system' => [
            'platform' => ['name' => 'Ubuntu 26.04 LTS', 'version_id' => '26.04', 'release_upgrade' => ['target' => '']],
            'php' => ['version' => '8.2.32', 'cli_version' => '8.2.32', 'handler' => 'php-fpm'],
            'runtime_policy' => ['ubuntu_release' => 'current-lts', 'php_line' => '8.5', 'php_handler' => 'php-fpm'],
        ]],
    ]), 'stderr' => ''];
});
agent_test_assert($host_health['overall'] === 'critical' && count($host_health['findings']) === 2, 'gateway normalizes a fresh host audit');
agent_test_assert(
    $host_health['findings'][0]['count'] === 2
        && $host_health['findings'][0]['project'] === 'Example'
        && $host_health['findings'][0]['last_seen'] === '2026-08-13T10:05:00+00:00',
    'gateway preserves bounded finding chronology and occurrence evidence'
);
agent_test_assert(
    $host_health['runtime']['ubuntu_version'] === '26.04'
        && $host_health['runtime']['web_php_version'] === '8.2.32'
        && $host_health['runtime']['php_handler'] === 'php-fpm',
    'gateway preserves bounded runtime evidence from the fresh audit'
);
agent_test_assert(
    $host_health['runtime']['baseline_findings'][0]['expected'] === 'Ubuntu default PHP 8.5',
    'runtime evidence exposes resolved baseline findings independently of the general finding limit'
);

$runtime_detail = agent_gateway_execute('inspect_host_detail runtime', function (array $command) {
    agent_test_assert($command === ['/usr/bin/sudo', '-n', '/usr/local/bin/nimbly-host-audit', '--format=json'], 'runtime detail uses the fixed host audit command');
    return ['exit_code' => 1, 'stdout' => json_encode([
        'overall' => 'warning', 'findings' => [], 'checks' => ['system' => [
            'platform' => ['name' => 'Ubuntu 24.04 LTS', 'version_id' => '24.04', 'release_upgrade' => ['target' => '26.04 LTS']],
            'php' => ['version' => '8.2.0', 'cli_version' => '8.2.0', 'handler' => 'apache-module', 'cli_extensions' => ['Core', 'curl', 'json']],
            'runtime_policy' => ['ubuntu_release' => 'current-lts', 'php_line' => 'ubuntu-default', 'php_handler' => 'php-fpm'],
        ]],
    ]), 'stderr' => ''];
});
agent_test_assert(
    $runtime_detail['check'] === 'runtime'
        && $runtime_detail['details']['ubuntu_upgrade_target'] === '26.04 LTS'
        && $runtime_detail['details']['web_php_version'] === '8.2.0'
        && $runtime_detail['details']['cli_extensions'] === ['Core', 'curl', 'json'],
    'allowlisted runtime detail exposes the exact baseline evidence'
);

$application_detail = agent_gateway_execute('inspect_host_detail applications', function (array $command) {
    agent_test_assert($command === ['/usr/bin/sudo', '-n', '/usr/local/bin/nimbly-host-audit', '--format=json'], 'application detail uses the fixed host audit command');
    return ['exit_code' => 0, 'stdout' => json_encode([
        'overall' => 'ok', 'findings' => [], 'checks' => [
            'projects' => ['Nimbly Site' => [
                'path' => '/var/www/nimbly-site', 'available' => true, 'environment' => 'prod',
                'status' => 'healthy', 'scheduler' => 'Active', 'requests' => 120,
                'http_5xx' => 0, 'php_errors' => 0,
                'git' => ['core' => ['branch' => 'master'], 'ext' => ['branch' => 'live']],
            ]],
            'apache' => ['access_logs' => ['/var/log/apache2/access.log'], 'error_logs' => ['/var/log/apache2/error.log']],
            'scheduler' => ['log' => '/var/log/nimbly-scheduler.log'],
        ],
    ]), 'stderr' => ''];
});
agent_test_assert(
    $application_detail['details']['applications'][0]['ext_branch'] === 'live'
        && $application_detail['details']['applications'][0]['application_log'] === '/var/www/nimbly-site/ext/data/.tmp/logs/system.log'
        && $application_detail['details']['apache_error_logs'] === ['/var/log/apache2/error.log']
        && $application_detail['details']['scheduler_log'] === '/var/log/nimbly-scheduler.log',
    'allowlisted application detail exposes bounded deployment and log evidence'
);

$release_responses = [
    json_encode(['8.5.9' => ['date' => '30 Jul 2026']]),
    '<table><tr><td>8.2</td><td>8 Dec 2022</td><td>31 Dec 2024</td><td>31 Dec 2026</td></tr>'
        . '<tr><td>8.5</td><td>20 Nov 2025</td><td>31 Dec 2027</td><td>31 Dec 2029</td></tr></table>',
    '<h2>Ubuntu 26.04 LTS</h2>',
];
$release_calls = 0;
$release_detail = agent_gateway_execute('inspect_host_detail releases', function (array $command) use (&$release_responses, &$release_calls) {
    agent_test_assert($command[0] === '/usr/bin/wget' && str_starts_with(end($command), 'https://'), 'release detail fetches only fixed HTTPS vendor sources');
    return ['exit_code' => 0, 'stdout' => $release_responses[$release_calls++], 'stderr' => ''];
});
agent_test_assert(
    $release_calls === 3
        && $release_detail['details']['php_latest_stable'] === '8.5.9'
        && $release_detail['details']['php_supported_branches'][0]['security_support_until'] === '31 Dec 2026'
        && $release_detail['details']['ubuntu_latest_lts'] === '26.04 LTS',
    'release detail returns structured current vendor evidence'
);

$diagnostic_command = 'systemctl status apache2 --no-pager';
$diagnostic_encoded = rtrim(strtr(base64_encode($diagnostic_command), '+/', '-_'), '=');
$diagnostic = agent_gateway_execute('diagnose ' . $diagnostic_encoded, function (array $command) use ($diagnostic_command) {
    agent_test_assert($command === ['/bin/bash', '-lc', $diagnostic_command], 'diagnostic gateway executes the exact decoded command');
    return ['exit_code' => 0, 'stdout' => 'active', 'stderr' => ''];
});
agent_test_assert($diagnostic['exit_code'] === 0 && $diagnostic['stdout'] === 'active', 'diagnostic gateway returns bounded command evidence');

$action_envelope = rtrim(strtr(base64_encode('{"signed":true}'), '+/', '-_'), '=');
$action_result = agent_gateway_execute('execute_action ' . $action_envelope, function (array $command) use ($action_envelope) {
    agent_test_assert($command === ['/usr/bin/sudo', '-n', '/usr/local/bin/nimbly-agent-action-gateway', $action_envelope], 'governed actions map only to the root-owned gateway');
    return ['exit_code' => 0, 'stdout' => json_encode(['exit_code' => 0, 'executed_at' => time()]), 'stderr' => ''];
});
agent_test_assert($action_result['exit_code'] === 0, 'governed gateway result is normalized');

$denied = 0;
foreach (['inspect_service ssh', 'inspect_service apache2 extra', 'inspect_host_detail secrets', 'sh -c id', ''] as $command) {
    try {
        agent_gateway_execute($command, fn() => []);
    } catch (RuntimeException) {
        $denied++;
    }
}
agent_test_assert($denied === 5, 'gateway rejects unknown and shell-shaped commands');

echo "Agent runtime tests passed.\n";
