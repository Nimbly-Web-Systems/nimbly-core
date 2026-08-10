<?php

function agent_gateway_execute(string $original_command, ?callable $runner = null): array
{
    $parts = preg_split('/\s+/', trim($original_command));
    if (count($parts) !== 2 || $parts[0] !== 'inspect_service') {
        throw new RuntimeException('Gateway command is not permitted');
    }
    $service = $parts[1];
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
    return ['exit_code' => proc_close($process), 'stdout' => substr((string)$stdout, 0, 8000), 'stderr' => substr((string)$stderr, 0, 1000)];
}
