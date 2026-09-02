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

load_library('agent');

$command = $argv[1] ?? '';
if ($command === 'agent:enqueue') {
    $agent_id = trim((string)($argv[2] ?? ''));
    $manual = '';
    $operator = '';
    $scheduled = false;
    $target = '';
    $read_only = false;
    foreach (array_slice($argv, 3) as $argument) {
        if (str_starts_with($argument, '--manual=')) {
            $manual = substr($argument, 9);
        } elseif (str_starts_with($argument, '--operator=')) {
            $operator = substr($argument, 11);
        } elseif (str_starts_with($argument, '--target=')) {
            $target = substr($argument, 9);
        } elseif ($argument === '--read-only') {
            $read_only = true;
        } elseif ($argument === '--scheduled') {
            $scheduled = true;
        }
    }
    $trigger_count = (int)($manual !== '') + (int)($operator !== '') + (int)$scheduled;
    if ($trigger_count !== 1) {
        throw new InvalidArgumentException('Choose exactly one of --scheduled, --manual=<key>, or --operator=<key>');
    }
    $dependencies = [];
    if ($manual !== '' || $operator !== '') {
        $dependencies = [
            'trigger' => $manual !== '' ? 'manual' : 'operator',
            'idempotency_suffix' => ($manual !== '' ? 'manual-' : 'operator-') . ($manual ?: $operator),
            'target' => $target,
            'read_only' => $read_only,
        ];
    } elseif ($scheduled) {
        $dependencies = ['trigger' => 'scheduled'];
    }
    $result = agent_enqueue_result($agent_id, null, $dependencies);
    $run_uuid = $result['run_uuid'];
    if ($result['created']) {
        echo "Agent run enqueued: {$run_uuid}\n";
    } else {
        echo "Agent run already exists ({$result['status']}): {$run_uuid}\n";
    }
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

fwrite(STDERR, "Usage: agent:enqueue <agent-id> (--scheduled|--manual=<key>|--operator=<key>) [--target=<identity>] [--read-only] | agent:run <run-uuid> | agent:retry <failed-run-uuid> | agent:recover\n");
exit(64);
