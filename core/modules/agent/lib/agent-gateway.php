<?php

function agent_gateway_execute(string $original_command, ?callable $runner = null): array
{
    $parts = preg_split('/\s+/', trim($original_command));
    $verb = (string)($parts[0] ?? '');
    $adapters = agent_gateway_adapters();
    $adapter = $adapters[$verb] ?? null;
    if (!is_array($adapter) || count($parts) - 1 !== (int)$adapter['arguments']) {
        throw new RuntimeException('Gateway command is not permitted');
    }
    return ($adapter['callback'])(array_slice($parts, 1), $runner);
}

function agent_gateway_adapters(): array
{
    return [
        'diagnose' => ['arguments' => 1, 'callback' => fn($args, $runner) => agent_gateway_diagnose($args[0], $runner)],
        'execute_action' => ['arguments' => 1, 'callback' => fn($args, $runner) => agent_gateway_execute_action($args[0], $runner)],
        'inspect_host_health' => ['arguments' => 0, 'callback' => fn($_args, $runner) => agent_gateway_inspect_host_health($runner)],
        'inspect_service' => ['arguments' => 1, 'callback' => fn($args, $runner) => agent_gateway_inspect_service($args[0], $runner)],
    ];
}

function agent_gateway_inspect_service(string $service, ?callable $runner = null): array
{
    if (!in_array($service, ['apache2', 'cron', 'fail2ban'], true)) {
        throw new RuntimeException('Gateway service is not permitted');
    }
    $command = [
        '/usr/bin/systemctl', 'show', $service,
        '--property=Id,ActiveState,SubState,LoadState,UnitFileState',
        '--no-pager',
    ];
    $runner = $runner ?? 'agent_gateway_run_process';
    $result = $runner($command);
    if (!is_array($result) || (int)($result['exit_code'] ?? 1) !== 0) {
        throw new RuntimeException('Gateway inspection failed');
    }
    $properties = [];
    foreach (preg_split('/\r?\n/', trim((string)($result['stdout'] ?? ''))) as $line) {
        [$key, $value] = explode('=', $line, 2) + [1 => ''];
        if ($key !== '') {
            $properties[$key] = $value;
        }
    }
    if (($properties['Id'] ?? '') !== $service . '.service') {
        throw new RuntimeException('Gateway returned an unexpected service');
    }
    return [
        'service' => $service,
        'active' => ($properties['ActiveState'] ?? '') === 'active',
        'sub_state' => (string)($properties['SubState'] ?? ''),
        'observed_at' => time(),
        'evidence' => sprintf(
            'load=%s active=%s sub=%s unit_file=%s',
            $properties['LoadState'] ?? 'unknown',
            $properties['ActiveState'] ?? 'unknown',
            $properties['SubState'] ?? 'unknown',
            $properties['UnitFileState'] ?? 'unknown'
        ),
    ];
}

function agent_gateway_diagnose(string $encoded_command, ?callable $runner = null): array
{
    $command = agent_gateway_decode($encoded_command, 2000);
    if ($command === '' || str_contains($command, "\0")) {
        throw new RuntimeException('Diagnostic command is invalid');
    }
    $runner = $runner ?? 'agent_gateway_run_process';
    $result = $runner(['/bin/bash', '-lc', $command]);
    if (!is_array($result)) {
        throw new RuntimeException('Diagnostic command returned invalid evidence');
    }
    return [
        'exit_code' => (int)($result['exit_code'] ?? 1),
        'stdout' => substr((string)($result['stdout'] ?? ''), 0, 20000),
        'stderr' => substr((string)($result['stderr'] ?? ''), 0, 4000),
        'observed_at' => time(),
    ];
}

function agent_gateway_execute_action(string $encoded_envelope, ?callable $runner = null): array
{
    if (strlen($encoded_envelope) > 12000) {
        throw new RuntimeException('Action envelope is too large');
    }
    $runner = $runner ?? 'agent_gateway_run_process';
    $result = $runner(['/usr/bin/sudo', '-n', '/usr/local/bin/nimbly-agent-action-gateway', $encoded_envelope]);
    if (!is_array($result) || (int)($result['exit_code'] ?? 1) !== 0) {
        throw new RuntimeException('Privileged action gateway denied execution');
    }
    $decoded = json_decode((string)($result['stdout'] ?? ''), true);
    if (!is_array($decoded) || !isset($decoded['exit_code'], $decoded['executed_at'])) {
        throw new RuntimeException('Privileged action gateway returned invalid evidence');
    }
    return $decoded;
}

function agent_gateway_decode(string $encoded, int $max_length): string
{
    if ($encoded === '' || strlen($encoded) > $max_length * 2 || preg_match('/^[A-Za-z0-9_-]+$/', $encoded) !== 1) {
        throw new RuntimeException('Gateway payload encoding is invalid');
    }
    $padding = (4 - strlen($encoded) % 4) % 4;
    $decoded = base64_decode(strtr($encoded . str_repeat('=', $padding), '-_', '+/'), true);
    if (!is_string($decoded) || strlen($decoded) > $max_length) {
        throw new RuntimeException('Gateway payload is invalid');
    }
    return $decoded;
}

function agent_gateway_inspect_host_health(?callable $runner = null): array
{
    $runner = $runner ?? 'agent_gateway_run_process';
    $result = $runner(['/usr/bin/sudo', '-n', '/usr/local/bin/nimbly-host-audit', '--format=json']);
    if (!is_array($result) || !in_array((int)($result['exit_code'] ?? 3), [0, 1, 2], true)) {
        throw new RuntimeException('Gateway host audit failed');
    }
    $audit = json_decode((string)($result['stdout'] ?? ''), true);
    if (!is_array($audit) || !isset($audit['overall'], $audit['findings'])) {
        throw new RuntimeException('Gateway host audit returned invalid evidence');
    }
    $findings = [];
    foreach (array_slice((array)$audit['findings'], 0, 50) as $finding) {
        if (!is_array($finding)) {
            continue;
        }
        $findings[] = [
            'id' => substr((string)($finding['id'] ?? $finding['check'] ?? 'finding'), 0, 120),
            'severity' => substr((string)($finding['severity'] ?? 'unknown'), 0, 20),
            'scope' => substr((string)($finding['scope'] ?? 'host'), 0, 80),
            'title' => substr((string)($finding['title'] ?? ''), 0, 160),
            'evidence' => substr((string)($finding['evidence'] ?? $finding['message'] ?? ''), 0, 500),
            'count' => max(1, (int)($finding['count'] ?? 1)),
            'project' => substr((string)($finding['project'] ?? ''), 0, 160),
            'first_seen' => substr((string)($finding['first_seen'] ?? ''), 0, 40),
            'last_seen' => substr((string)($finding['last_seen'] ?? ''), 0, 40),
        ];
    }
    return [
        'overall' => substr((string)$audit['overall'], 0, 20),
        'summary' => array_intersect_key((array)($audit['summary'] ?? []), array_flip(['critical', 'warning', 'ok', 'unknown'])),
        'findings' => $findings,
        'observed_at' => time(),
    ];
}

function agent_gateway_run_process(array $command): array
{
    $pipes = [];
    $process = proc_open($command, [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        throw new RuntimeException('Gateway process could not start');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['exit_code' => proc_close($process), 'stdout' => substr((string)$stdout, 0, 250000), 'stderr' => substr((string)$stderr, 0, 1000)];
}
