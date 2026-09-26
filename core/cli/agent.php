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
if ($command === 'agent:chat') {
    // Chat has its own worker lane so a long daily run never delays a reply. The scheduler runs
    // its commands one after another, so the worker is detached and outlives a daily run (max 1 h).
    $name = sys_get_temp_dir() . '/nimbly-agent-chat-' . md5(BASE_DIR);
    if (($argv[2] ?? '') !== '--worker') {
        exec(implode(' ', array_map('escapeshellarg', [PHP_BINARY, BASE_DIR . 'core/cli/nimbly.php', 'agent:chat', '--worker']))
            . ' >> ' . escapeshellarg($name . '.log') . ' 2>&1 < /dev/null &');
        exit(0);
    }
    $worker = fopen($name . '.lock', 'c');
    if (!$worker || !flock($worker, LOCK_EX | LOCK_NB)) {
        exit(0);
    }
    load_library('agent-chat');
    $until = time() + 3600;
    do {
        agent_chat_run_pending();
        usleep(2000000);
    } while (time() < $until);
    exit(0);
}
if ($command === 'agent:evidence') {
    $agent_id = trim((string)($argv[2] ?? ''));
    $run_uuid = '';
    $out = '';
    foreach (array_slice($argv, 3) as $argument) {
        if (str_starts_with($argument, '--run=')) {
            $run_uuid = substr($argument, 6);
        } elseif (str_starts_with($argument, '--out=')) {
            $out = substr($argument, 6);
        }
    }
    if ($agent_id === '' || $run_uuid === '') {
        fwrite(STDERR, "Usage: agent:evidence <agent-id> --run=<run-uuid> [--out=<path>]\n");
        exit(64);
    }
    $definition = agent_definition($agent_id);
    $input_steps = (array)$definition['pipeline']['input'];
    if ($input_steps === []) {
        throw new RuntimeException('Agent has no input pipeline steps: ' . $agent_id);
    }
    $last_step = end($input_steps);
    $last_step_id = (string)$last_step['id'];
    load_library('data');
    $key = substr(hash('sha256', $run_uuid . ':' . $last_step_id), 0, 16);
    $stored = data_read('.agent_steps', $key);
    if (!is_array($stored) || ($stored['status'] ?? '') !== 'completed' || !is_array($stored['artifact'] ?? null)) {
        throw new RuntimeException('Run has no completed evidence for step: ' . $last_step_id);
    }
    $body = "=== SYSTEM PROMPT ({$definition['instructions']}) ===\n\n"
        . agent_instructions($definition) . "\n\n"
        . "=== EVIDENCE (input to the reasoning step, run {$run_uuid}, step {$last_step_id}) ===\n\n"
        . json_encode($stored['artifact']['data'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
        . "\nPaste both sections into any chat to try different prompt wording against this real evidence. This command makes no API call and sends nothing.\n";
    if ($out !== '') {
        file_put_contents($out, $body);
        echo "Wrote evidence + instructions to: {$out}\n";
    } else {
        echo $body;
    }
    exit(0);
}

fwrite(STDERR, "Usage: agent:enqueue <agent-id> (--scheduled|--manual=<key>|--operator=<key>) [--target=<identity>] [--read-only] | agent:run <run-uuid> | agent:retry <failed-run-uuid> | agent:recover | agent:chat | agent:evidence <agent-id> --run=<run-uuid> [--out=<path>]\n");
exit(64);
