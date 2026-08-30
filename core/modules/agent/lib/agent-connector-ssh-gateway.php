<?php

// Generic client for fixed remote gateway verbs; the remote verb catalog is Ext-owned.
function agent_connector_ssh_gateway(array $source, array $config, array $context): array
{
    $request = agent_artifact_data($source);
    $arguments = (array)($request['arguments'] ?? $request);
    $target_key = (string)($config['target_argument'] ?? 'server');
    $target_id = (string)($arguments[$target_key] ?? '');
    $target = agent_ssh_target($target_id, $config, $context);
    $verb = (string)($config['verb'] ?? '');
    if (preg_match('/^[a-z][a-z0-9_-]*$/', $verb) !== 1) {
        throw new RuntimeException('SSH gateway verb is invalid');
    }
    $gateway_arguments = [$verb];
    foreach ((array)($config['arguments'] ?? []) as $key) {
        $value = (string)($arguments[$key] ?? '');
        if (($config['base64url_argument'] ?? '') === $key) {
            $value = rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        }
        $gateway_arguments[] = $value;
    }
    $command = [
        '/usr/bin/timeout', (string)((int)($config['timeout'] ?? 30)), '/usr/bin/ssh', '-T',
        '-o', 'BatchMode=yes', '-o', 'IdentitiesOnly=yes', '-o', 'ConnectTimeout=10',
        '-o', 'StrictHostKeyChecking=yes', '-o', 'UserKnownHostsFile=' . $target['known_hosts_file'],
        '-i', $target['identity_file'], $target['ssh_target'], ...$gateway_arguments,
    ];
    $runner = $context['process_runner'] ?? 'agent_ssh_run_process';
    $response = $runner($command);
    if (!is_array($response) || (int)($response['exit_code'] ?? 1) !== 0) {
        $reason = trim((string)($response['stderr'] ?? ''));
        throw new RuntimeException('SSH gateway failed' . ($reason === '' ? '' : ': ' . substr($reason, 0, 300)));
    }
    $decoded = json_decode((string)($response['stdout'] ?? ''), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('SSH gateway returned invalid JSON');
    }
    foreach ((array)($config['required_result'] ?? []) as $key) {
        if (!array_key_exists($key, $decoded)) {
            throw new RuntimeException('SSH gateway result is incomplete');
        }
    }
    $result = [$target_key => $target_id];
    foreach ((array)($config['copy_arguments'] ?? []) as $key) {
        $result[$key] = $arguments[$key] ?? null;
    }
    foreach ((array)($config['result_fields'] ?? []) as $key => $rule) {
        $rule = is_array($rule) ? $rule : [];
        $value = $decoded[$key] ?? ($rule['default'] ?? null);
        $result[$key] = agent_ssh_bounded_field($value, $rule);
    }
    if (($config['result_fields'] ?? []) === []) {
        $result += $decoded;
    }
    return agent_artifact((string)($context['step']['produces'] ?? 'ssh.result'), 1, $result, [[
        'kind' => 'ssh_gateway', 'target' => $target_id, 'verb' => $verb, 'observed_at' => time(),
    ]]);
}

function agent_ssh_target(string $identity, array $config, array $context): array
{
    $targets = agent_config($context, (string)($config['targets'] ?? 'targets'), []);
    $allowed = false;
    foreach ((array)$targets as $target) {
        if (is_array($target) && ($target['identity'] ?? '') === $identity
            && (empty($config['authority']) || ($target['authority'] ?? '') === $config['authority'])) {
            $allowed = true;
        }
    }
    if (!$allowed) {
        throw new RuntimeException('SSH target is not permitted');
    }
    $inventory = $context['ssh_inventory'] ?? null;
    if (!is_array($inventory)) {
        load_library('env');
        $path = env((string)($config['inventory_env'] ?? ''));
        $inventory = $path !== '' && is_readable($path)
            ? json_decode((string)file_get_contents($path), true) : null;
    }
    $target = is_array($inventory) ? ($inventory[$identity] ?? null) : null;
    foreach (['ssh_target', 'identity_file', 'known_hosts_file'] as $key) {
        if (!is_array($target) || trim((string)($target[$key] ?? '')) === '') {
            throw new RuntimeException('SSH target configuration is incomplete');
        }
    }
    if (preg_match('/^[A-Za-z0-9._-]+@[A-Za-z0-9.-]+$/', $target['ssh_target']) !== 1
        || !str_starts_with($target['identity_file'], '/') || !str_starts_with($target['known_hosts_file'], '/')) {
        throw new RuntimeException('SSH target configuration is invalid');
    }
    return $target;
}

function agent_ssh_bounded_field($value, array $rule)
{
    return match ((string)($rule['type'] ?? 'string')) {
        'int' => (int)$value,
        'bool' => (bool)$value,
        'array' => is_array($value) ? $value : [],
        default => substr((string)$value, 0, (int)($rule['max_length'] ?? 2000)),
    };
}

function agent_ssh_run_process(array $command): array
{
    $process = proc_open($command, [
        0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start SSH gateway process');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['exit_code' => proc_close($process), 'stdout' => substr((string)$stdout, 0, 24000),
        'stderr' => substr((string)$stderr, 0, 4000)];
}
