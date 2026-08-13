<?php

if (php_sapi_name() !== 'cli') {
    die("agent.php must be run from the command line.\n");
}

$GLOBALS['SYSTEM'] = $GLOBALS['SYSTEM'] ?? [
    'file_base' => BASE_DIR,
    'env_paths' => ['ext', 'core'],
    'modules' => ['root' => '/'],
    'variables' => [],
    'uri' => '',
];

require_once BASE_DIR . 'core/lib/find.php';

$env_file = BASE_DIR . '.env';
if (file_exists($env_file)) {
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2) + [1 => '']);
        $_SERVER[$key] = $value;
    }
}

require_once BASE_DIR . 'core/modules/agent/lib/agent-runtime.php';

$command = $argv[1] ?? '';
if ($command === 'agent:enqueue') {
    $agent_id = trim((string)($argv[2] ?? ''));
    $manual = '';
    foreach (array_slice($argv, 3) as $argument) {
        if (str_starts_with($argument, '--manual=')) {
            $manual = substr($argument, 9);
        }
    }
    $dependencies = $manual === '' ? [] : [
        'trigger' => 'manual',
        'idempotency_suffix' => 'manual-' . $manual,
    ];
    $run_uuid = agent_enqueue($agent_id, null, $dependencies);
    echo "Agent run enqueued: {$run_uuid}\n";
    exit(0);
}
if ($command === 'agent:run') {
    $run_uuid = trim((string)($argv[2] ?? ''));
    $result = agent_run($run_uuid);
    echo json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";
    exit(($result['status'] ?? '') === 'completed' ? 0 : 1);
}
if ($command === 'agent:retry') {
    $run_uuid = trim((string)($argv[2] ?? ''));
    $retry_uuid = agent_retry($run_uuid);
    echo "Agent retry enqueued: {$retry_uuid}\n";
    exit(0);
}
if ($command === 'agent:recover') {
    $count = agent_recover_expired_runs();
    echo "Recovered agent runs: {$count}\n";
    exit(0);
}

fwrite(STDERR, "Usage: agent:enqueue <agent-id> [--manual=<key>] | agent:run <run-uuid> | agent:retry <failed-run-uuid> | agent:recover\n");
exit(64);
