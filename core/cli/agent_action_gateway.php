#!/usr/bin/php
<?php

if (php_sapi_name() !== 'cli') {
    exit(77);
}

const ACTION_GATEWAY_DEFAULT_STATE_DIR = '/var/lib/nimbly-agent/maintenance';
const ACTION_GATEWAY_DEFAULT_TIMEZONE = 'UTC';
const ACTION_GATEWAY_DEFAULT_WINDOW_START = '22:00';
const ACTION_GATEWAY_DEFAULT_WINDOW_END = '01:00';

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

function action_gateway_actions(): array
{
    return [
        'install_patch_updates' => [
            'arguments' => [],
            'callback' => 'action_gateway_install_patch_updates',
        ],
    ];
}

function action_gateway_run(array $envelope, array $environment, ?callable $runner = null, ?int $now = null): array
{
    if (array_key_exists('command', $envelope)) {
        throw new RuntimeException('Legacy command actions are unsupported');
    }
    foreach (['target', 'action', 'arguments', 'action_digest', 'expires_at', 'signature'] as $key) {
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
    $now = $now ?? time();
    if ((int)$envelope['expires_at'] < $now || (int)$envelope['expires_at'] > $now + 600) {
        throw new RuntimeException('Authorization envelope has expired');
    }
    $action = (string)$envelope['action'];
    $definition = action_gateway_actions()[$action] ?? null;
    $arguments = $envelope['arguments'];
    if (!is_array($definition) || !is_array($arguments) || array_keys($arguments) !== $definition['arguments']) {
        throw new RuntimeException('Action is not registered or its arguments are invalid');
    }
    $digest = (string)$envelope['action_digest'];
    if (preg_match('/^[a-f0-9]{64}$/', $digest) !== 1) {
        throw new RuntimeException('Action digest is invalid');
    }
    $runner = $runner ?? 'action_gateway_run_process';
    $existing = action_gateway_transaction_by_digest($digest, $environment);
    if (is_array($existing)) {
        return action_gateway_public_transaction($existing);
    }
    try {
        if ($action === 'install_patch_updates') {
            if (!action_gateway_in_maintenance_window($environment, $now)) {
                throw new RuntimeException('Patch maintenance is outside the approved window');
            }
            action_gateway_assert_maintenance_ready($environment, $runner);
            $audit = action_gateway_host_audit($runner);
            if ((int)($audit['checks']['system']['platform']['security_updates'] ?? 0) < 1) {
                throw new RuntimeException('No current security update finding permits patch maintenance');
            }
            $environment['_preflight_audit'] = $audit;
        }
    } catch (Throwable $error) {
        return [
            'status' => 'blocked',
            'action_digest' => $digest,
            'reason' => substr($error->getMessage(), 0, 1000),
            'retryable' => false,
            'observed_at' => $now,
        ];
    }
    action_gateway_consume_authorization($digest, $environment, $now);
    return ($definition['callback'])($arguments, $environment, $runner, $now, $digest);
}

function action_gateway_consume_authorization(string $digest, array $environment, int $now): void
{
    $state_dir = (string)($environment['NIMBLY_AGENT_AUTHORIZATION_DIR'] ?? '/var/lib/nimbly-agent/authorizations');
    if (!is_dir($state_dir) || !is_writable($state_dir)) {
        throw new RuntimeException('Authorization state directory is unavailable');
    }
    $replay_file = $state_dir . '/' . $digest;
    $handle = @fopen($replay_file, 'x');
    if ($handle === false) {
        if (is_file($replay_file)) {
            return;
        }
        throw new RuntimeException('Authorization state could not be reserved');
    }
    fwrite($handle, (string)$now);
    fclose($handle);
    @chmod($replay_file, 0600);
}

function action_gateway_install_patch_updates(
    array $_arguments,
    array $environment,
    callable $runner,
    int $now,
    string $digest
): array {
    $audit = $environment['_preflight_audit'] ?? action_gateway_host_audit($runner);
    $security_updates = (int)($audit['checks']['system']['platform']['security_updates'] ?? 0);
    if ($security_updates < 1) {
        throw new RuntimeException('No current security update finding permits patch maintenance');
    }
    foreach ((array)($audit['findings'] ?? []) as $finding) {
        if (($finding['severity'] ?? '') === 'critical'
            && ($finding['id'] ?? '') !== 'system:security-updates') {
            throw new RuntimeException('An unrelated critical host finding blocks patch maintenance');
        }
    }

    $transaction = [
        'transaction_id' => substr($digest, 0, 16),
        'action' => 'install_patch_updates',
        'target' => (string)$environment['NIMBLY_AGENT_SERVER_ID'],
        'status' => 'running',
        'security_updates_before' => $security_updates,
        'started_at' => $now,
        'updated_at' => $now,
        'boot_id_before' => action_gateway_boot_id(),
        'reboot_required' => false,
        'verification' => [],
        'error' => '',
    ];
    action_gateway_write_transaction($transaction, $environment);

    try {
        $update = $runner(['/usr/bin/apt-get', 'update']);
        action_gateway_require_success($update, 'Package index refresh failed');
        $transaction['pending_updates_before'] = action_gateway_pending_update_count($runner);
        $upgrade = $runner([
            '/usr/bin/env',
            'DEBIAN_FRONTEND=noninteractive',
            'NEEDRESTART_MODE=a',
            '/usr/bin/apt-get',
            '-y',
            '-o',
            'APT::Get::Always-Include-Phased-Updates=true',
            '-o',
            'Dpkg::Options::=--force-confold',
            '--with-new-pkgs',
            'upgrade',
        ]);
        action_gateway_require_success($upgrade, 'Package upgrade failed');
        $transaction['package_output'] = substr((string)($upgrade['stdout'] ?? ''), 0, 12000);
        $transaction['package_errors'] = substr((string)($upgrade['stderr'] ?? ''), 0, 3000);
        $transaction['pending_updates_after'] = action_gateway_pending_update_count($runner);
        $transaction['reboot_required'] = is_file('/var/run/reboot-required');
        $transaction['reboot_packages'] = is_readable('/var/run/reboot-required.pkgs')
            ? array_values(array_filter(array_map('trim', file('/var/run/reboot-required.pkgs', FILE_IGNORE_NEW_LINES) ?: [])))
            : [];
        $transaction['updated_at'] = time();
        if ($transaction['reboot_required']) {
            $transaction['status'] = 'reboot_scheduled';
            action_gateway_write_transaction($transaction, $environment);
            $scheduled = $runner([
                '/usr/bin/systemd-run',
                '--unit=nimbly-agent-maintenance-reboot-' . $transaction['transaction_id'],
                '--on-active=15s',
                '/usr/bin/systemctl',
                'reboot',
            ]);
            action_gateway_require_success($scheduled, 'Maintenance reboot could not be scheduled');
            return action_gateway_public_transaction($transaction);
        }
        return action_gateway_complete_transaction($transaction, $environment, $runner);
    } catch (Throwable $error) {
        $transaction['status'] = 'failed';
        $transaction['error'] = substr($error->getMessage(), 0, 1000);
        $transaction['updated_at'] = time();
        action_gateway_write_transaction($transaction, $environment);
        throw $error;
    }
}

function action_gateway_transaction_by_digest(string $digest, array $environment): ?array
{
    $state_dir = (string)($environment['NIMBLY_AGENT_MAINTENANCE_DIR'] ?? ACTION_GATEWAY_DEFAULT_STATE_DIR);
    $path = $state_dir . '/' . substr($digest, 0, 16) . '.json';
    if (!is_file($path)) {
        return null;
    }
    $transaction = json_decode((string)file_get_contents($path), true);
    return is_array($transaction) ? $transaction : null;
}

function action_gateway_resume(array $environment, ?callable $runner = null, ?int $now = null): array
{
    $transaction = action_gateway_latest_transaction($environment);
    if (!is_array($transaction)
        || !in_array(($transaction['status'] ?? ''), ['reboot_scheduled', 'verification_pending'], true)) {
        return ['status' => 'idle', 'observed_at' => $now ?? time()];
    }
    $boot_id = action_gateway_boot_id();
    if (($transaction['status'] ?? '') === 'reboot_scheduled'
        && ($boot_id === '' || hash_equals((string)($transaction['boot_id_before'] ?? ''), $boot_id))) {
        throw new RuntimeException('Maintenance reboot has not completed');
    }
    $transaction['boot_id_after'] = $boot_id;
    $transaction['resumed_at'] = $now ?? time();
    $runner = $runner ?? 'action_gateway_run_process';
    try {
        return action_gateway_complete_transaction($transaction, $environment, $runner);
    } catch (Throwable $error) {
        $transaction['verification_attempts'] = (int)($transaction['verification_attempts'] ?? 0) + 1;
        $transaction['verification_started_at'] = (int)($transaction['verification_started_at'] ?? 0)
            ?: ($now ?? time());
        $exhausted = $transaction['verification_attempts'] >= 10
            || ($now ?? time()) - $transaction['verification_started_at'] >= 600;
        $transaction['status'] = $exhausted ? 'verification_failed' : 'verification_pending';
        $transaction['error'] = substr($error->getMessage(), 0, 1000);
        $transaction['updated_at'] = time();
        action_gateway_write_transaction($transaction, $environment);
        throw $error;
    }
}

function action_gateway_pending_update_count(callable $runner): int
{
    $result = $runner([
        '/usr/bin/apt-get', '--simulate',
        '-o', 'APT::Get::Always-Include-Phased-Updates=true',
        '--with-new-pkgs', 'upgrade',
    ]);
    action_gateway_require_success($result, 'Package update plan could not be inspected');
    preg_match_all('/^Inst\s+/m', (string)($result['stdout'] ?? ''), $matches);
    return count($matches[0] ?? []);
}

function action_gateway_complete_transaction(array $transaction, array $environment, callable $runner): array
{
    $services = $environment['NIMBLY_AGENT_REQUIRED_SERVICES'] ?? ['apache2', 'cron', 'fail2ban'];
    if (!is_array($services) || $services === []) {
        throw new RuntimeException('Maintenance service verification is not configured');
    }
    $service_results = [];
    foreach ($services as $service) {
        if (!is_string($service) || preg_match('/^[a-z0-9@_.-]+$/', $service) !== 1) {
            throw new RuntimeException('Maintenance service verification is invalid');
        }
        $result = $runner(['/usr/bin/systemctl', 'is-active', $service]);
        if ((int)($result['exit_code'] ?? 1) !== 0 || trim((string)($result['stdout'] ?? '')) !== 'active') {
            throw new RuntimeException('Required service is not healthy after maintenance: ' . $service);
        }
        $service_results[$service] = 'active';
    }
    $endpoints = $environment['NIMBLY_AGENT_MAINTENANCE_ENDPOINTS'] ?? [];
    if (!is_array($endpoints) || $endpoints === []) {
        throw new RuntimeException('Maintenance endpoint verification is not configured');
    }
    $endpoint_results = [];
    foreach ($endpoints as $endpoint) {
        if (!is_string($endpoint) || preg_match('#^https://[^\s]+$#', $endpoint) !== 1) {
            throw new RuntimeException('Maintenance endpoint is invalid');
        }
        $result = $runner([
            '/usr/bin/curl', '--silent', '--show-error', '--fail', '--location',
            '--max-time', '20', '--output', '/dev/null', '--write-out', '%{http_code}', $endpoint,
        ]);
        action_gateway_require_success($result, 'Maintenance endpoint is unhealthy');
        $endpoint_results[$endpoint] = trim((string)($result['stdout'] ?? ''));
    }
    $audit = action_gateway_host_audit($runner);
    $remaining_security = (int)($audit['checks']['system']['platform']['security_updates'] ?? 0);
    if ($remaining_security > 0) {
        throw new RuntimeException('Security updates remain after package maintenance');
    }
    $transaction['status'] = 'completed';
    $transaction['completed_at'] = time();
    $transaction['updated_at'] = time();
    $transaction['security_updates_after'] = $remaining_security;
    $transaction['verification'] = [
        'services' => $service_results,
        'endpoints' => $endpoint_results,
        'audit_overall' => (string)($audit['overall'] ?? 'unknown'),
        'audit_observed_at' => time(),
    ];
    action_gateway_write_transaction($transaction, $environment);
    return action_gateway_public_transaction($transaction);
}

function action_gateway_assert_maintenance_ready(array $environment, callable $runner): void
{
    $processes = $environment['NIMBLY_AGENT_MAINTENANCE_BLOCKING_PROCESSES'] ?? [
        'apt', 'apt-get', 'dpkg', 'unattended-upgr', 'git', 'rsync', 'restic',
        'borg', 'mysqldump', 'do-release-upgr',
    ];
    if (!is_array($processes) || $processes === []) {
        throw new RuntimeException('Maintenance blocker configuration is invalid');
    }
    foreach ($processes as $process) {
        if (!is_string($process) || preg_match('/^[a-zA-Z0-9_.-]{1,15}$/', $process) !== 1) {
            throw new RuntimeException('Maintenance blocker process is invalid');
        }
        $result = $runner(['/usr/bin/pgrep', '-x', $process]);
        if ((int)($result['exit_code'] ?? 1) === 0) {
            throw new RuntimeException('A package, deployment, backup, or migration operation is active');
        }
    }
    $blocking_services = $environment['NIMBLY_AGENT_MAINTENANCE_BLOCKING_SERVICES'] ?? [
        'apt-daily.service', 'apt-daily-upgrade.service', 'unattended-upgrades.service',
    ];
    if (!is_array($blocking_services) || $blocking_services === []) {
        throw new RuntimeException('Maintenance blocker service configuration is invalid');
    }
    foreach ($blocking_services as $service) {
        if (!is_string($service) || preg_match('/^[a-zA-Z0-9@_.-]+\.service$/', $service) !== 1) {
            throw new RuntimeException('Maintenance blocker service is invalid');
        }
        $result = $runner(['/usr/bin/systemctl', 'is-active', $service]);
        if ((int)($result['exit_code'] ?? 1) === 0) {
            throw new RuntimeException('A blocking maintenance service is active');
        }
    }
    $dpkg = $runner(['/usr/bin/dpkg', '--audit']);
    if ((int)($dpkg['exit_code'] ?? 1) !== 0 || trim((string)($dpkg['stdout'] ?? '')) !== '') {
        throw new RuntimeException('The package database requires repair');
    }
    $existing = action_gateway_latest_transaction($environment);
    if (is_array($existing) && in_array(($existing['status'] ?? ''), ['running', 'reboot_scheduled'], true)) {
        throw new RuntimeException('Another maintenance transaction is active');
    }
}

function action_gateway_host_audit(callable $runner): array
{
    $result = $runner(['/usr/local/bin/nimbly-host-audit', '--format=json']);
    if (!in_array((int)($result['exit_code'] ?? 3), [0, 1, 2], true)) {
        throw new RuntimeException('Fresh host audit failed');
    }
    $audit = json_decode((string)($result['stdout'] ?? ''), true);
    if (!is_array($audit) || !isset($audit['overall'], $audit['findings'], $audit['checks'])) {
        throw new RuntimeException('Fresh host audit returned invalid evidence');
    }
    return $audit;
}

function action_gateway_in_maintenance_window(array $environment, int $now): bool
{
    $timezone_name = (string)($environment['NIMBLY_AGENT_MAINTENANCE_TIMEZONE'] ?? ACTION_GATEWAY_DEFAULT_TIMEZONE);
    $start = (string)($environment['NIMBLY_AGENT_MAINTENANCE_START'] ?? ACTION_GATEWAY_DEFAULT_WINDOW_START);
    $end = (string)($environment['NIMBLY_AGENT_MAINTENANCE_END'] ?? ACTION_GATEWAY_DEFAULT_WINDOW_END);
    if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start) !== 1
        || preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end) !== 1) {
        throw new RuntimeException('Maintenance window configuration is invalid');
    }
    try {
        $timezone = new DateTimeZone($timezone_name);
    } catch (Throwable) {
        throw new RuntimeException('Maintenance timezone configuration is invalid');
    }
    $local = (new DateTimeImmutable('@' . $now))->setTimezone($timezone);
    $minute = (int)$local->format('H') * 60 + (int)$local->format('i');
    [$start_hour, $start_minute] = array_map('intval', explode(':', $start));
    [$end_hour, $end_minute] = array_map('intval', explode(':', $end));
    $start_value = $start_hour * 60 + $start_minute;
    $end_value = $end_hour * 60 + $end_minute;
    if ($start_value < $end_value) {
        return $minute >= $start_value && $minute < $end_value;
    }
    return $minute >= $start_value || $minute < $end_value;
}

function action_gateway_state_dir(array $environment): string
{
    return rtrim((string)($environment['NIMBLY_AGENT_MAINTENANCE_DIR'] ?? ACTION_GATEWAY_DEFAULT_STATE_DIR), '/');
}

function action_gateway_write_transaction(array $transaction, array $environment): void
{
    $state_dir = action_gateway_state_dir($environment);
    if (!is_dir($state_dir) && !mkdir($state_dir, 0700, true) && !is_dir($state_dir)) {
        throw new RuntimeException('Maintenance state directory is unavailable');
    }
    $path = $state_dir . '/' . $transaction['transaction_id'] . '.json';
    $temporary = $path . '.tmp';
    $encoded = json_encode($transaction, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (file_put_contents($temporary, $encoded . "\n", LOCK_EX) === false
        || !chmod($temporary, 0600)
        || !rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('Maintenance transaction could not be stored');
    }
}

function action_gateway_latest_transaction(array $environment): ?array
{
    $files = glob(action_gateway_state_dir($environment) . '/*.json') ?: [];
    if ($files === []) {
        return null;
    }
    usort($files, fn($a, $b) => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
    $decoded = json_decode((string)file_get_contents($files[0]), true);
    return is_array($decoded) ? $decoded : null;
}

function action_gateway_status(array $environment, ?int $now = null): array
{
    $transaction = action_gateway_latest_transaction($environment);
    if (!is_array($transaction)) {
        return ['status' => 'idle', 'observed_at' => $now ?? time()];
    }
    $public = action_gateway_public_transaction($transaction);
    $public['observed_at'] = $now ?? time();
    return $public;
}

function action_gateway_public_transaction(array $transaction): array
{
    return array_intersect_key($transaction, array_flip([
        'transaction_id', 'action', 'target', 'status', 'security_updates_before',
        'security_updates_after', 'started_at', 'updated_at', 'completed_at',
        'pending_updates_before', 'pending_updates_after', 'reboot_required',
        'reboot_packages', 'resumed_at', 'verification', 'verification_attempts', 'error',
    ]));
}

function action_gateway_boot_id(): string
{
    return is_readable('/proc/sys/kernel/random/boot_id')
        ? trim((string)file_get_contents('/proc/sys/kernel/random/boot_id'))
        : '';
}

function action_gateway_require_success(array $result, string $message): void
{
    if ((int)($result['exit_code'] ?? 1) !== 0) {
        $error = substr(trim((string)($result['stderr'] ?? '')), 0, 500);
        throw new RuntimeException($message . ($error === '' ? '' : ': ' . $error));
    }
}

function action_gateway_run_process(array $argv): array
{
    $pipes = [];
    $process = proc_open($argv, [
        0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start registered action');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [
        'exit_code' => proc_close($process),
        'stdout' => substr((string)$stdout, 0, 20000),
        'stderr' => substr((string)$stderr, 0, 4000),
    ];
}

function action_gateway_environment(): array
{
    $environment = $_SERVER;
    $config_file = '/etc/nimbly/agent-action-gateway.json';
    if (is_readable($config_file)) {
        $config = json_decode((string)file_get_contents($config_file), true);
        if (is_array($config)) {
            $environment = array_merge($environment, $config);
        }
    }
    return $environment;
}

function action_gateway_install(): void
{
    if (function_exists('posix_geteuid') && posix_geteuid() !== 0) {
        throw new RuntimeException('Action gateway installation requires root');
    }
    $library = '/usr/local/lib/nimbly-agent-action-gateway.php';
    $binary = '/usr/local/bin/nimbly-agent-action-gateway';
    $service = '/etc/systemd/system/nimbly-agent-maintenance-resume.service';
    $sudoers = '/etc/sudoers.d/nimbly-agent-action-gateway';
    if (!is_dir('/etc/nimbly')) {
        mkdir('/etc/nimbly', 0750, true);
    }
    foreach (['/var/lib/nimbly-agent/authorizations', ACTION_GATEWAY_DEFAULT_STATE_DIR] as $directory) {
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
    }
    copy(__FILE__, $library);
    chmod($library, 0644);
    file_put_contents($binary, "#!/bin/sh\nexec /usr/bin/php {$library} \"\$@\"\n");
    chmod($binary, 0755);
    file_put_contents($service, implode("\n", [
        '[Unit]',
        'Description=Resume Nimbly agent maintenance verification after boot',
        'After=network-online.target apache2.service cron.service fail2ban.service',
        'Wants=network-online.target',
        'StartLimitIntervalSec=900',
        'StartLimitBurst=20',
        '',
        '[Service]',
        'Type=oneshot',
        'ExecStart=' . $binary . ' resume',
        'Restart=on-failure',
        'RestartSec=30s',
        '',
        '[Install]',
        'WantedBy=multi-user.target',
        '',
    ]));
    chmod($service, 0644);
    file_put_contents($sudoers, "nimbly-agent ALL=(root) NOPASSWD: {$binary} *\n");
    chmod($sudoers, 0440);
    $sudoers_check = action_gateway_run_process(['/usr/sbin/visudo', '-cf', $sudoers]);
    if ((int)($sudoers_check['exit_code'] ?? 1) !== 0) {
        @unlink($sudoers);
        throw new RuntimeException('Action gateway sudo rule is invalid');
    }
    action_gateway_require_success(
        action_gateway_run_process(['/usr/bin/systemctl', 'daemon-reload']),
        'Systemd could not reload the maintenance service'
    );
    action_gateway_require_success(
        action_gateway_run_process(['/usr/bin/systemctl', 'enable', 'nimbly-agent-maintenance-resume.service']),
        'Maintenance resume service could not be enabled'
    );
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__
    || (($GLOBALS['command'] ?? '') === 'agent:action-gateway:install')) {
    try {
        $mode = (string)($argv[1] ?? '');
        if ($mode === 'install' || (($GLOBALS['command'] ?? '') === 'agent:action-gateway:install')) {
            action_gateway_install();
            echo "Installed registered agent action gateway.\n";
            exit(0);
        }
        $environment = action_gateway_environment();
        if ($mode === 'resume') {
            echo json_encode(action_gateway_resume($environment), JSON_UNESCAPED_SLASHES) . "\n";
            exit(0);
        }
        if ($mode === 'status') {
            echo json_encode(action_gateway_status($environment), JSON_UNESCAPED_SLASHES) . "\n";
            exit(0);
        }
        $encoded = $mode === 'execute' ? (string)($argv[2] ?? '') : $mode;
        echo json_encode(action_gateway_run(action_gateway_decode($encoded), $environment), JSON_UNESCAPED_SLASHES) . "\n";
        exit(0);
    } catch (Throwable $error) {
        fwrite(STDERR, "Registered action denied: " . $error->getMessage() . "\n");
        exit(77);
    }
}
