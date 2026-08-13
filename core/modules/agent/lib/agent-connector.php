<?php

function agent_connector_targets(array $dependencies, array $connector): array
{
    $targets = agent_config($dependencies, (string)($connector['targets'] ?? ''), []);
    if (!is_array($targets)) {
        throw new RuntimeException('Agent connector targets are invalid');
    }
    $authority = (string)($connector['authority'] ?? '');
    $result = [];
    foreach ($targets as $target) {
        if (!is_array($target) || ($authority !== '' && ($target['authority'] ?? '') !== $authority)) {
            continue;
        }
        $key = (string)($target['server'] ?? '');
        if ($key !== '') {
            $result[$key] = $target;
        }
    }
    return $result;
}

function agent_connector_inventory(array $dependencies, array $connector): array
{
    if (isset($dependencies['ssh_inventory']) && is_array($dependencies['ssh_inventory'])) {
        return $dependencies['ssh_inventory'];
    }
    load_library('env');
    $env_key = (string)($connector['inventory_env'] ?? '');
    $path = $env_key === '' ? '' : env($env_key);
    if ($path === '' || !str_starts_with($path, '/') || !is_readable($path)) {
        throw new RuntimeException('Agent connector inventory is unavailable');
    }
    $inventory = json_decode((string)file_get_contents($path), true);
    if (!is_array($inventory)) {
        throw new RuntimeException('Agent connector inventory is invalid');
    }
    return $inventory;
}

function agent_connector_ssh_target(string $server, array $dependencies, array $connector): array
{
    $targets = agent_connector_targets($dependencies, $connector);
    if (!isset($targets[$server])) {
        throw new RuntimeException('Agent connector target is not permitted');
    }
    $target = agent_connector_inventory($dependencies, $connector)[$server] ?? null;
    if (!is_array($target)) {
        throw new RuntimeException('Agent connector target is not configured');
    }
    foreach (['ssh_target', 'identity_file', 'known_hosts_file'] as $key) {
        if (trim((string)($target[$key] ?? '')) === '') {
            throw new RuntimeException('Agent connector target configuration is incomplete');
        }
    }
    if (preg_match('/^[A-Za-z0-9._-]+@[A-Za-z0-9.-]+$/', (string)$target['ssh_target']) !== 1
        || !str_starts_with((string)$target['identity_file'], '/')
        || !str_starts_with((string)$target['known_hosts_file'], '/')) {
        throw new RuntimeException('Agent connector target configuration is invalid');
    }
    return $target;
}

function agent_connector_ssh_gateway(array $target, array $arguments, int $timeout, array $dependencies): array
{
    $command = [
        '/usr/bin/timeout', (string)$timeout, '/usr/bin/ssh', '-T',
        '-o', 'BatchMode=yes', '-o', 'IdentitiesOnly=yes', '-o', 'ConnectTimeout=10',
        '-o', 'StrictHostKeyChecking=yes', '-o', 'UserKnownHostsFile=' . $target['known_hosts_file'],
        '-i', $target['identity_file'], $target['ssh_target'], ...$arguments,
    ];
    $runner = $dependencies['process_runner'] ?? 'agent_connector_run_process';
    $result = $runner($command);
    if (!is_array($result) || (int)($result['exit_code'] ?? 1) !== 0) {
        throw new RuntimeException('Agent connector SSH gateway command failed');
    }
    return $result;
}

function agent_connector_ssh_gateway_tool(array $arguments, array $dependencies): array
{
    $connector = $dependencies['tool_definition']['connector'] ?? null;
    if (!is_array($connector) || ($connector['type'] ?? '') !== 'ssh_gateway') {
        throw new RuntimeException('SSH gateway connector configuration is invalid');
    }
    $target_argument = (string)($connector['target_argument'] ?? 'server');
    $server = (string)($arguments[$target_argument] ?? '');
    $target = agent_connector_ssh_target($server, $dependencies, $connector);
    $gateway = $connector['gateway'] ?? [];
    $verb = (string)($gateway['verb'] ?? '');
    if ($verb === '' || preg_match('/^[a-z][a-z0-9_-]*$/', $verb) !== 1) {
        throw new RuntimeException('SSH gateway connector verb is invalid');
    }
    $gateway_arguments = [$verb];
    foreach ((array)($gateway['arguments'] ?? []) as $key) {
        $value = (string)($arguments[$key] ?? '');
        if (($gateway['base64url_argument'] ?? '') === $key) {
            $value = rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        }
        $gateway_arguments[] = $value;
    }
    $response = agent_connector_ssh_gateway(
        $target,
        $gateway_arguments,
        (int)($gateway['timeout'] ?? 30),
        $dependencies
    );
    $decoded = json_decode((string)($response['stdout'] ?? ''), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('SSH gateway connector returned invalid JSON');
    }
    foreach ((array)($connector['required_result'] ?? []) as $key) {
        if (!array_key_exists($key, $decoded)) {
            throw new RuntimeException('SSH gateway connector result is incomplete');
        }
    }
    $result = [$target_argument => $server];
    foreach ((array)($connector['copy_arguments'] ?? []) as $key) {
        $result[$key] = $arguments[$key] ?? null;
    }
    foreach ((array)($connector['result_fields'] ?? []) as $key => $rule) {
        $rule = is_array($rule) ? $rule : [];
        $value = $decoded[$key] ?? ($rule['default'] ?? null);
        $type = (string)($rule['type'] ?? 'string');
        if ($type === 'int') {
            $value = (int)$value;
        } elseif ($type === 'bool') {
            $value = (bool)$value;
        } elseif ($type === 'array') {
            $value = is_array($value) ? $value : [];
        } else {
            $value = substr((string)$value, 0, (int)($rule['max_length'] ?? 2000));
        }
        $result[$key] = $value;
    }
    return $result;
}

function agent_connector_run_process(array $command): array
{
    $pipes = [];
    $process = proc_open($command, [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start agent connector process');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [
        'exit_code' => proc_close($process),
        'stdout' => substr((string)$stdout, 0, 24000),
        'stderr' => substr((string)$stderr, 0, 4000),
    ];
}
