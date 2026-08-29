<?php

require_once dirname(__DIR__) . '/cli/agent_action_gateway.php';

function action_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function action_test_envelope(array $environment, string $digest, int $now): array
{
    $envelope = [
        'target' => 'nimbly1.stage',
        'action' => 'install_patch_updates',
        'arguments' => [],
        'action_digest' => $digest,
        'expires_at' => $now + 300,
    ];
    $envelope['signature'] = hash_hmac(
        'sha256', action_gateway_canonical_json($envelope), $environment['NIMBLY_AGENT_GATEWAY_KEY']
    );
    return $envelope;
}

$root = sys_get_temp_dir() . '/nimbly-action-gateway-' . bin2hex(random_bytes(6));
$authorization_dir = $root . '/authorizations';
$maintenance_dir = $root . '/maintenance';
mkdir($authorization_dir, 0700, true);
mkdir($maintenance_dir, 0700, true);
$environment = [
    'NIMBLY_AGENT_GATEWAY_KEY' => 'test-secret-key',
    'NIMBLY_AGENT_SERVER_ID' => 'nimbly1.stage',
    'NIMBLY_AGENT_AUTHORIZATION_DIR' => $authorization_dir,
    'NIMBLY_AGENT_MAINTENANCE_DIR' => $maintenance_dir,
    'NIMBLY_AGENT_MAINTENANCE_TIMEZONE' => 'America/Sao_Paulo',
    'NIMBLY_AGENT_MAINTENANCE_START' => '22:00',
    'NIMBLY_AGENT_MAINTENANCE_END' => '01:00',
    'NIMBLY_AGENT_REQUIRED_SERVICES' => ['apache2', 'cron', 'fail2ban'],
    'NIMBLY_AGENT_MAINTENANCE_ENDPOINTS' => ['https://stage.example.test/'],
    'NIMBLY_AGENT_MAINTENANCE_BLOCKING_PROCESSES' => ['apt', 'apt-get', 'dpkg', 'unattended-upgr'],
    'NIMBLY_AGENT_MAINTENANCE_BLOCKING_SERVICES' => [
        'apt-daily.service', 'apt-daily-upgrade.service', 'unattended-upgrades.service',
    ],
];
$now = (new DateTimeImmutable('2026-08-27 22:15:00', new DateTimeZone('America/Sao_Paulo')))->getTimestamp();
$digest = hash('sha256', 'action-1');
$envelope = action_test_envelope($environment, $digest, $now);
$commands = [];
$audit_count = 0;
$result = action_gateway_run($envelope, $environment, function (array $command) use (&$commands, &$audit_count) {
    $commands[] = $command;
    if ($command === ['/usr/bin/pgrep', '-x', 'apt']
        || $command === ['/usr/bin/pgrep', '-x', 'apt-get']
        || $command === ['/usr/bin/pgrep', '-x', 'dpkg']
        || $command === ['/usr/bin/pgrep', '-x', 'unattended-upgr']
        || $command === ['/usr/bin/systemctl', 'is-active', 'apt-daily.service']
        || $command === ['/usr/bin/systemctl', 'is-active', 'apt-daily-upgrade.service']
        || $command === ['/usr/bin/systemctl', 'is-active', 'unattended-upgrades.service']) {
        return ['exit_code' => 1, 'stdout' => '', 'stderr' => ''];
    }
    if ($command === ['/usr/bin/dpkg', '--audit']) {
        return ['exit_code' => 0, 'stdout' => '', 'stderr' => ''];
    }
    if ($command === ['/usr/local/bin/nimbly-host-audit', '--format=json']) {
        $audit_count++;
        return [
            'exit_code' => $audit_count === 1 ? 1 : 0,
            'stdout' => json_encode([
                'overall' => $audit_count === 1 ? 'warning' : 'ok',
                'findings' => $audit_count === 1 ? [[
                    'id' => 'system:security-updates', 'severity' => 'warning',
                ]] : [],
                'checks' => ['system' => ['platform' => [
                    'security_updates' => $audit_count === 1 ? 3 : 0,
                ]]],
            ]),
            'stderr' => '',
        ];
    }
    if ($command[0] === '/usr/bin/systemctl') {
        return ['exit_code' => 0, 'stdout' => "active\n", 'stderr' => ''];
    }
    if ($command[0] === '/usr/bin/curl') {
        return ['exit_code' => 0, 'stdout' => '200', 'stderr' => ''];
    }
    return ['exit_code' => 0, 'stdout' => 'updated', 'stderr' => ''];
}, $now);

action_test_assert(($result['status'] ?? '') === 'completed', 'registered patch action completes');
action_test_assert(($result['security_updates_before'] ?? 0) === 3, 'security finding permits all patch updates');
action_test_assert(($result['security_updates_after'] ?? -1) === 0, 'fresh audit proves security updates cleared');
action_test_assert(in_array(['/usr/bin/apt-get', 'update'], $commands, true), 'package indexes refresh through fixed argv');
action_test_assert(in_array([
    '/usr/bin/env', 'DEBIAN_FRONTEND=noninteractive', 'NEEDRESTART_MODE=a',
    '/usr/bin/apt-get', '-y', '-o', 'APT::Get::Always-Include-Phased-Updates=true',
    '-o', 'Dpkg::Options::=--force-confold',
    '--with-new-pkgs', 'upgrade',
], $commands, true), 'all patch updates use fixed no-removal argv');

$replayed = action_gateway_run($envelope, $environment, fn() => [], $now);
action_test_assert(
    ($replayed['status'] ?? '') === 'completed'
        && ($replayed['transaction_id'] ?? '') === ($result['transaction_id'] ?? ''),
    'registered action replay returns the durable transaction'
);

$outside_now = (new DateTimeImmutable('2026-08-27 12:00:00', new DateTimeZone('America/Sao_Paulo')))->getTimestamp();
$outside = action_test_envelope($environment, hash('sha256', 'action-2'), $outside_now);
$outside_result = action_gateway_run($outside, $environment, fn() => [], $outside_now);
action_test_assert(
    ($outside_result['status'] ?? '') === 'blocked'
        && ($outside_result['retryable'] ?? true) === false,
    'root gateway returns a structured maintenance-window block'
);
action_test_assert(
    action_gateway_in_maintenance_window($environment, $now)
        && action_gateway_in_maintenance_window($environment, $now + 2 * 3600)
        && !action_gateway_in_maintenance_window($environment, $outside_now),
    'cross-midnight maintenance window is deterministic'
);

$unknown = action_test_envelope($environment, hash('sha256', 'action-3'), $now);
$unknown['action'] = 'run_shell';
unset($unknown['signature']);
$unknown['signature'] = hash_hmac('sha256', action_gateway_canonical_json($unknown), $environment['NIMBLY_AGENT_GATEWAY_KEY']);
$unknown_denied = false;
try {
    action_gateway_run($unknown, $environment, fn() => [], $now);
} catch (RuntimeException) {
    $unknown_denied = true;
}
action_test_assert($unknown_denied, 'unregistered actions are denied');

$legacy = [
    'target' => 'nimbly1.stage',
    'command' => 'systemctl start apache2',
    'action_digest' => hash('sha256', 'legacy-action'),
    'expires_at' => $now + 300,
    'rollback' => 'systemctl stop apache2',
];
$legacy['signature'] = hash_hmac('sha256', action_gateway_canonical_json($legacy), $environment['NIMBLY_AGENT_GATEWAY_KEY']);
$legacy_result = action_gateway_run($legacy, $environment, function (array $command) {
    action_test_assert($command === ['/bin/sh', '-lc', 'systemctl start apache2'], 'legacy recovery remains bounded by its signed command');
    return ['exit_code' => 0, 'stdout' => 'started', 'stderr' => ''];
}, $now);
action_test_assert($legacy_result['exit_code'] === 0, 'existing bounded service recovery remains available');

$legacy_package = $legacy;
$legacy_package['command'] = 'apt-get -y upgrade';
$legacy_package['action_digest'] = hash('sha256', 'legacy-package');
unset($legacy_package['signature']);
$legacy_package['signature'] = hash_hmac('sha256', action_gateway_canonical_json($legacy_package), $environment['NIMBLY_AGENT_GATEWAY_KEY']);
$legacy_package_denied = false;
try {
    action_gateway_run($legacy_package, $environment, fn() => [], $now);
} catch (RuntimeException) {
    $legacy_package_denied = true;
}
action_test_assert($legacy_package_denied, 'package maintenance cannot bypass the registered action');

$status = action_gateway_status($environment, $now + 10);
action_test_assert(($status['status'] ?? '') === 'completed' && isset($status['observed_at']), 'maintenance status is bounded evidence');

foreach (glob($maintenance_dir . '/*') ?: [] as $file) {
    unlink($file);
}
$resume_transaction = [
    'transaction_id' => 'resume-test',
    'action' => 'install_patch_updates',
    'target' => 'nimbly1.stage',
    'status' => 'reboot_scheduled',
    'security_updates_before' => 3,
    'started_at' => $now,
    'updated_at' => $now,
    'boot_id_before' => 'a-different-boot-id',
    'reboot_required' => true,
    'verification' => [],
    'error' => '',
];
action_gateway_write_transaction($resume_transaction, $environment);
$resumed_audits = 0;
$resumed = action_gateway_resume($environment, function (array $command) use (&$resumed_audits) {
    if ($command[0] === '/usr/bin/systemctl') {
        return ['exit_code' => 0, 'stdout' => "active\n", 'stderr' => ''];
    }
    if ($command[0] === '/usr/bin/curl') {
        return ['exit_code' => 0, 'stdout' => '200', 'stderr' => ''];
    }
    if ($command === ['/usr/local/bin/nimbly-host-audit', '--format=json']) {
        $resumed_audits++;
        return ['exit_code' => 0, 'stdout' => json_encode([
            'overall' => 'ok', 'findings' => [],
            'checks' => ['system' => ['platform' => ['security_updates' => 0]]],
        ]), 'stderr' => ''];
    }
    return ['exit_code' => 1, 'stdout' => '', 'stderr' => 'unexpected'];
}, $now + 120);
action_test_assert(
    $resumed['status'] === 'completed' && $resumed_audits === 1,
    'post-reboot continuation completes durable service, endpoint, and audit verification'
);

foreach (glob($maintenance_dir . '/*') ?: [] as $file) {
    unlink($file);
}
$resume_transaction['transaction_id'] = 'resume-retry';
action_gateway_write_transaction($resume_transaction, $environment);
$retry_pending = false;
try {
    action_gateway_resume($environment, fn() => ['exit_code' => 1, 'stdout' => 'inactive', 'stderr' => ''], $now + 120);
} catch (RuntimeException) {
    $retry_pending = (action_gateway_status($environment, $now + 121)['status'] ?? '') === 'verification_pending';
}
action_test_assert($retry_pending, 'post-boot verification remains retryable while services settle');

foreach (glob($authorization_dir . '/*') ?: [] as $file) {
    unlink($file);
}
foreach (glob($maintenance_dir . '/*') ?: [] as $file) {
    unlink($file);
}
rmdir($authorization_dir);
rmdir($maintenance_dir);
rmdir($root);

echo "Agent action gateway tests passed.\n";
