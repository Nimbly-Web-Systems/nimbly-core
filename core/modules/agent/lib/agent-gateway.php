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
        'inspect_host_detail' => ['arguments' => 1, 'callback' => fn($args, $runner) => agent_gateway_inspect_host_detail($args[0], $runner)],
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
    $audit = agent_gateway_run_host_audit($runner);
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
            'host' => substr((string)($finding['host'] ?? ''), 0, 160),
            'expected' => agent_gateway_bounded_value($finding['expected'] ?? null),
            'observed' => agent_gateway_bounded_value($finding['observed'] ?? null),
            'first_seen' => substr((string)($finding['first_seen'] ?? ''), 0, 40),
            'last_seen' => substr((string)($finding['last_seen'] ?? ''), 0, 40),
        ];
    }
    return [
        'overall' => substr((string)$audit['overall'], 0, 20),
        'summary' => array_intersect_key((array)($audit['summary'] ?? []), array_flip(['critical', 'warning', 'ok', 'unknown'])),
        'findings' => $findings,
        'audit_version' => substr((string)($audit['audit_version'] ?? ''), 0, 80),
        'generated_at' => substr((string)($audit['generated_at'] ?? ''), 0, 40),
        'runtime' => agent_gateway_runtime_evidence($audit),
        'observed_at' => time(),
    ];
}

function agent_gateway_inspect_host_detail(string $check, ?callable $runner = null): array
{
    if (!in_array($check, ['runtime', 'releases', 'applications', 'apache', 'scheduler', 'storage', 'certificates'], true)) {
        throw new RuntimeException('Gateway host detail is not permitted');
    }
    if ($check === 'releases') {
        return [
            'check' => $check,
            'details' => agent_gateway_release_evidence($runner),
            'audit_version' => '',
            'generated_at' => gmdate(DATE_ATOM),
            'observed_at' => time(),
        ];
    }
    $audit = agent_gateway_run_host_audit($runner);
    $system = (array)($audit['checks']['system'] ?? []);
    $details = match ($check) {
        'runtime' => agent_gateway_runtime_evidence($audit),
        'applications' => agent_gateway_application_evidence($audit),
        'apache' => (array)($audit['checks']['apache'] ?? []),
        'scheduler' => (array)($audit['checks']['scheduler'] ?? []),
        'certificates' => (array)($audit['checks']['certificates'] ?? []),
        'storage' => array_intersect_key($system, array_flip([
            'uptime_seconds', 'cpu_count', 'load_average', 'disk_root_used_percent',
            'memory', 'failed_units', 'services', 'config_tests',
        ])),
    };
    return [
        'check' => $check,
        'details' => agent_gateway_bounded_value($details, 12000),
        'audit_version' => substr((string)($audit['audit_version'] ?? ''), 0, 80),
        'generated_at' => substr((string)($audit['generated_at'] ?? ''), 0, 40),
        'observed_at' => time(),
    ];
}

function agent_gateway_release_evidence(?callable $runner = null): array
{
    $runner = $runner ?? 'agent_gateway_run_process';
    $sources = [
        'php_latest' => 'https://www.php.net/releases/index.php?json&version=8&max=1',
        'php_support' => 'https://www.php.net/supported-versions.php',
        'ubuntu_lts' => 'https://ubuntu.com/download/server',
    ];
    $documents = [];
    foreach ($sources as $key => $url) {
        $result = $runner(['/usr/bin/curl', '--fail', '--silent', '--show-error', '--location', '--max-time', '15', $url]);
        if (!is_array($result) || (int)($result['exit_code'] ?? 1) !== 0 || trim((string)($result['stdout'] ?? '')) === '') {
            throw new RuntimeException('Official release evidence is unavailable');
        }
        $documents[$key] = substr((string)$result['stdout'], 0, 500000);
    }

    $php_releases = json_decode($documents['php_latest'], true);
    $php_latest = is_array($php_releases) ? (string)array_key_first($php_releases) : '';
    if (preg_match('/^8\.\d+\.\d+$/', $php_latest) !== 1) {
        throw new RuntimeException('Official PHP release evidence is invalid');
    }

    $supported = [];
    preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $documents['php_support'], $rows);
    foreach ($rows[1] ?? [] as $row) {
        preg_match_all('/<t[dh]\b[^>]*>(.*?)<\/t[dh]>/is', $row, $cell_matches);
        $cells = array_map(
            fn($cell) => trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML5))),
            $cell_matches[1] ?? []
        );
        if (count($cells) < 4) {
            continue;
        }
        $branch = $cells[0];
        preg_match_all('/\b\d{1,2}\s+[A-Za-z]+\s+\d{4}\b/', implode(' ', array_slice($cells, 1)), $dates);
        if (preg_match('/^8\.\d+$/', $branch) === 1 && count($dates[0] ?? []) >= 3) {
            $supported[] = ['branch' => $branch, 'active_support_until' => $dates[0][1], 'security_support_until' => $dates[0][2]];
        }
    }
    if ($supported === []) {
        throw new RuntimeException('Official PHP support evidence is incomplete');
    }
    if (preg_match('/Ubuntu\s+(\d{2}\.\d{2}(?:\.\d+)?)\s+LTS/i', $documents['ubuntu_lts'], $ubuntu) !== 1) {
        throw new RuntimeException('Official Ubuntu release evidence is invalid');
    }
    return [
        'php_latest_stable' => $php_latest,
        'php_supported_branches' => $supported,
        'ubuntu_latest_lts' => $ubuntu[1] . ' LTS',
        'sources' => array_values($sources),
    ];
}

function agent_gateway_application_evidence(array $audit): array
{
    $checks = (array)($audit['checks'] ?? []);
    $apache = (array)($checks['apache'] ?? []);
    $scheduler = (array)($checks['scheduler'] ?? []);
    $applications = [];
    foreach (array_slice((array)($checks['projects'] ?? []), 0, 50, true) as $name => $project) {
        if (!is_array($project)) {
            continue;
        }
        $path = substr((string)($project['path'] ?? ''), 0, 300);
        $git = (array)($project['git'] ?? []);
        $applications[] = [
            'name' => substr((string)$name, 0, 160),
            'path' => $path,
            'available' => !empty($project['available']),
            'environment' => substr((string)($project['environment'] ?? ''), 0, 40),
            'status' => substr((string)($project['status'] ?? ''), 0, 40),
            'scheduler' => substr((string)($project['scheduler'] ?? ''), 0, 80),
            'core_branch' => substr((string)($git['core']['branch'] ?? ''), 0, 120),
            'ext_branch' => substr((string)($git['ext']['branch'] ?? ''), 0, 120),
            'application_log' => $path === '' ? '' : $path . '/ext/data/.tmp/logs/system.log',
            'requests' => max(0, (int)($project['requests'] ?? 0)),
            'http_5xx' => max(0, (int)($project['http_5xx'] ?? 0)),
            'php_errors' => max(0, (int)($project['php_errors'] ?? 0)),
        ];
    }
    return [
        'applications' => $applications,
        'apache_access_logs' => array_slice((array)($apache['access_logs'] ?? []), 0, 20),
        'apache_error_logs' => array_slice((array)($apache['error_logs'] ?? []), 0, 20),
        'scheduler_log' => substr((string)($scheduler['log'] ?? ''), 0, 300),
    ];
}

function agent_gateway_run_host_audit(?callable $runner = null): array
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
    return $audit;
}

function agent_gateway_runtime_evidence(array $audit): array
{
    $system = (array)($audit['checks']['system'] ?? []);
    $platform = (array)($system['platform'] ?? []);
    $php = (array)($system['php'] ?? []);
    $policy = (array)($system['runtime_policy'] ?? []);
    $baseline_findings = [];
    foreach ((array)($audit['findings'] ?? []) as $finding) {
        if (!is_array($finding) || !str_starts_with((string)($finding['id'] ?? ''), 'runtime:')) {
            continue;
        }
        $baseline_findings[] = [
            'id' => substr((string)($finding['id'] ?? ''), 0, 120),
            'title' => substr((string)($finding['title'] ?? ''), 0, 160),
            'expected' => agent_gateway_bounded_value($finding['expected'] ?? null),
            'observed' => agent_gateway_bounded_value($finding['observed'] ?? null),
        ];
    }
    return [
        'ubuntu_version' => substr((string)($platform['version_id'] ?? ''), 0, 40),
        'ubuntu_name' => substr((string)($platform['name'] ?? ''), 0, 120),
        'ubuntu_upgrade_target' => substr((string)($platform['release_upgrade']['target'] ?? ''), 0, 40),
        'web_php_version' => substr((string)($php['version'] ?? ''), 0, 40),
        'cli_php_version' => substr((string)($php['cli_version'] ?? ''), 0, 40),
        'php_handler' => substr((string)($php['handler'] ?? ''), 0, 80),
        'cli_extensions' => array_slice(array_values((array)($php['cli_extensions'] ?? [])), 0, 200),
        'policy' => agent_gateway_bounded_value($policy, 1000),
        'baseline_findings' => array_slice($baseline_findings, 0, 10),
    ];
}

function agent_gateway_bounded_value(mixed $value, int $max_length = 2000): mixed
{
    if (is_array($value)) {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || strlen($encoded) > $max_length) {
            return ['truncated' => true];
        }
        return $value;
    }
    if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
        return $value;
    }
    return substr((string)$value, 0, $max_length);
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
    return ['exit_code' => proc_close($process), 'stdout' => substr((string)$stdout, 0, 1048576), 'stderr' => substr((string)$stderr, 0, 1000)];
}
