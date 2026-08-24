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
        $key = (string)($target['identity'] ?? $target['server'] ?? '');
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

function agent_connector_run_triggers(string $run_uuid): array
{
    $triggers = [];
    $visited = [];
    for ($depth = 0; $depth < 20 && $run_uuid !== ''; $depth++) {
        if (isset($visited[$run_uuid])) {
            break;
        }
        $visited[$run_uuid] = true;
        $run = data_read('.agent_runs', $run_uuid);
        if (!is_array($run)) {
            break;
        }
        $trigger = (string)($run['trigger'] ?? '');
        if ($trigger !== '') {
            $triggers[] = $trigger;
        }
        $run_uuid = (string)($run['retry_of'] ?? '');
    }
    return array_values(array_unique($triggers));
}

function agent_connector_deliver_email(array $result, string $run_uuid, array $dependencies): array
{
    $config = agent_config($dependencies, 'report_delivery', []);
    if (!is_array($config)) {
        throw new RuntimeException('Agent email delivery configuration is invalid');
    }
    $items_key = (string)($config['items_key'] ?? 'items');
    $item_key = (string)($config['item_key'] ?? 'id');
    $items = $result[$items_key] ?? null;
    if (!is_array($items)) {
        throw new RuntimeException('Agent email delivery items are invalid');
    }
    $shadow_triggers = (array)($config['shadow_triggers'] ?? ['manual']);
    $run_triggers = agent_connector_run_triggers($run_uuid);
    if (array_intersect($run_triggers, $shadow_triggers) !== [] && empty($dependencies['force_delivery'])) {
        $shadow = [];
        foreach ($items as $item) {
            $key = (string)($item[$item_key] ?? '');
            $shadow[$key] = ['accepted' => false, 'shadow' => true, 'provider_message_id' => ''];
        }
        return ['success' => true, 'environments' => $shadow];
    }
    load_libraries(['email', 'env']);
    $recipient = env(
        (string)($config['recipient_env'] ?? ''),
        (string)($config['recipient_default'] ?? '')
    );
    $renderer = (string)($config['renderer'] ?? '');
    if ($recipient === '' || !is_callable($renderer)) {
        throw new RuntimeException('Agent email delivery is incomplete');
    }
    $deliveries = [];
    foreach ($items as $item) {
        $key = (string)($item[$item_key] ?? '');
        $email_data = [
            'service' => (string)($config['service'] ?? 'resend'),
            'recipient' => $recipient,
            'subject' => (string)($item[$config['subject_field'] ?? 'email_subject'] ?? ''),
            'html' => $renderer($item),
            'idempotency_key' => $run_uuid . ':' . $key,
        ];
        if (!empty($dependencies['email_request'])) {
            $email_data['request'] = $dependencies['email_request'];
        }
        $sent = email_result($email_data);
        $deliveries[$key] = [
            'accepted' => !empty($sent['success']),
            'provider_message_id' => $sent['id'] ?? '',
        ];
        if (empty($sent['success']) || empty($sent['id'])) {
            return ['success' => false, 'environments' => $deliveries, 'error' => 'Email provider did not accept every report'];
        }
    }
    return ['success' => count($deliveries) === count($items), 'environments' => $deliveries];
}
