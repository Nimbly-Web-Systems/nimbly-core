<?php

if (php_sapi_name() !== 'cli') {
    exit(77);
}

function action_gateway_canonical_json(array $value): string
{
    ksort($value);
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function action_gateway_decode(string $encoded): array
{
    if ($encoded === '' || strlen($encoded) > 12000 || preg_match('/^[A-Za-z0-9_-]+$/', $encoded) !== 1) {
        throw new RuntimeException('Invalid envelope encoding');
    }
    $padding = (4 - strlen($encoded) % 4) % 4;
    $json = base64_decode(strtr($encoded . str_repeat('=', $padding), '-_', '+/'), true);
    $envelope = is_string($json) ? json_decode($json, true) : null;
    if (!is_array($envelope)) {
        throw new RuntimeException('Invalid envelope');
    }
    return $envelope;
}

function action_gateway_hard_floor(string $command): bool
{
    foreach ([
        '/\b(?:drop|truncate|delete)\b.*\b(?:database|table|from)\b/i',
        '/\b(?:passwd|useradd|userdel|usermod|groupadd|visudo|authorized_keys|sshd_config)\b/i',
        '/\b(?:ufw disable|iptables -F|setenforce 0|systemctl (?:stop|disable) fail2ban)\b/i',
        '/\b(?:rm|shred)\b.*(?:log|audit)/i',
        '/\b(?:rm\s+-[^ ]*r|mkfs|wipefs|dd\s+if=|git\s+reset\s+--hard)\b/i',
        '/(?:^|\s)(?:curl|wget)\b.*\|\s*(?:sh|bash)\b/i',
        '/\b(?:eval|exec)\b|`|\$\(/',
    ] as $pattern) {
        if (preg_match($pattern, $command) === 1) {
            return true;
        }
    }
    return false;
}

function action_gateway_run(array $envelope, array $environment, ?callable $runner = null): array
{
    $required = ['target', 'command', 'action_digest', 'expires_at', 'rollback', 'signature'];
    foreach ($required as $key) {
        if (!array_key_exists($key, $envelope)) {
            throw new RuntimeException('Incomplete authorization envelope');
        }
    }
    $key = (string)($environment['NIMBLY_AGENT_GATEWAY_KEY'] ?? '');
    $server_id = (string)($environment['NIMBLY_AGENT_SERVER_ID'] ?? '');
    if ($key === '' || $server_id === '' || !hash_equals($server_id, (string)$envelope['target'])) {
        throw new RuntimeException('Authorization target is invalid');
    }
    $signature = (string)$envelope['signature'];
    unset($envelope['signature']);
    $expected = hash_hmac('sha256', action_gateway_canonical_json($envelope), $key);
    if (!hash_equals($expected, $signature)) {
        throw new RuntimeException('Authorization signature is invalid');
    }
    if ((int)$envelope['expires_at'] < time() || (int)$envelope['expires_at'] > time() + 600) {
        throw new RuntimeException('Authorization envelope has expired');
    }
    $command = trim((string)$envelope['command']);
    if ($command === '' || strlen($command) > 2000 || trim((string)$envelope['rollback']) === '' || action_gateway_hard_floor($command)) {
        throw new RuntimeException('Action violates the deterministic safety floor');
    }
    $state_dir = (string)($environment['NIMBLY_AGENT_AUTHORIZATION_DIR'] ?? '/var/lib/nimbly-agent/authorizations');
    if (!is_dir($state_dir) || !is_writable($state_dir)) {
        throw new RuntimeException('Authorization state directory is unavailable');
    }
    $digest = (string)$envelope['action_digest'];
    if (preg_match('/^[a-f0-9]{64}$/', $digest) !== 1) {
        throw new RuntimeException('Action digest is invalid');
    }
    $replay_file = $state_dir . '/' . $digest;
    $handle = @fopen($replay_file, 'x');
    if ($handle === false) {
        throw new RuntimeException('Authorization envelope was already consumed');
    }
    fwrite($handle, (string)time());
    fclose($handle);
    $runner = $runner ?? function (array $argv): array {
        $pipes = [];
        $process = proc_open($argv, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start authorized action');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return ['exit_code' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
    };
    $result = $runner(['/bin/sh', '-lc', $command]);
    return [
        'exit_code' => (int)($result['exit_code'] ?? 1),
        'stdout' => substr((string)($result['stdout'] ?? ''), 0, 12000),
        'stderr' => substr((string)($result['stderr'] ?? ''), 0, 3000),
        'executed_at' => time(),
        'action_digest' => $digest,
    ];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $environment = $_SERVER;
        $config_file = '/etc/nimbly/agent-action-gateway.json';
        if (is_readable($config_file)) {
            $config = json_decode((string)file_get_contents($config_file), true);
            if (is_array($config)) {
                $environment = array_merge($environment, $config);
            }
        }
        $result = action_gateway_run(action_gateway_decode((string)($argv[1] ?? '')), $environment);
        echo json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";
        exit(0);
    } catch (Throwable) {
        fwrite(STDERR, "Authorized action denied\n");
        exit(77);
    }
}
