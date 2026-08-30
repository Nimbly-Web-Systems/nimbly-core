<?php

require_once __DIR__ . '/agent-connector.php';
require_once __DIR__ . '/agent-report.php';

function agent_tool_executor(string $id): callable
{
    $executors = [
        'ssh_gateway' => 'agent_connector_ssh_gateway_tool',
        'registered_action' => 'agent_execute_registered_action',
        'health_resolution' => 'agent_execute_health_resolution',
    ];
    if (!isset($executors[$id]) || !is_callable($executors[$id])) {
        throw new RuntimeException('Agent tool executor is not registered: ' . $id);
    }
    return $executors[$id];
}

function agent_tool_authorizer(string $id): callable
{
    $authorizers = [
        'delegated_patch_maintenance' => 'agent_authorize_patch_maintenance',
        'fresh_evidence_resolution' => 'agent_authorize_health_resolution',
    ];
    if (!isset($authorizers[$id]) || !is_callable($authorizers[$id])) {
        throw new RuntimeException('Agent tool authorizer is not registered: ' . $id);
    }
    return $authorizers[$id];
}

function agent_autonomous_targets(array $dependencies): array
{
    $targets = agent_config($dependencies, 'targets', []);
    return array_values(array_map(
        fn($target) => (string)$target['identity'],
        array_filter((array)$targets, fn($target) => is_array($target)
            && ($target['authority'] ?? '') === 'autonomous_remediation')
    ));
}

function agent_authorize_patch_maintenance(array $arguments, string $run_uuid, array $dependencies): array
{
    $target = (string)($arguments['server'] ?? '');
    $decision_time = (int)($dependencies['now'] ?? time());
    $policy = agent_config($dependencies, 'action_policy.install_patch_updates', []);
    $timezone = new DateTimeZone((string)($policy['timezone'] ?? 'UTC'));
    $local = (new DateTimeImmutable('@' . $decision_time))->setTimezone($timezone);
    $minute = (int)$local->format('H') * 60 + (int)$local->format('i');
    $start = agent_policy_minute((string)($policy['window_start'] ?? '22:00'));
    $end = agent_policy_minute((string)($policy['window_end'] ?? '01:00'));
    $inside_window = $start < $end
        ? $minute >= $start && $minute < $end
        : $minute >= $start || $minute < $end;
    $fresh_audit = false;
    foreach (data_read('.agent_events') ?: [] as $event) {
        if (($event['run_uuid'] ?? '') !== $run_uuid
            || ($event['type'] ?? '') !== 'tool_completed'
            || ($event['payload']['tool'] ?? '') !== 'inspect_host_health'
            || ($event['payload']['result']['server'] ?? '') !== $target) {
            continue;
        }
        foreach ((array)($event['payload']['result']['findings'] ?? []) as $finding) {
            if (($finding['id'] ?? '') === 'system:security-updates') {
                $fresh_audit = true;
                break 2;
            }
        }
    }
    $authorized = in_array($target, agent_autonomous_targets($dependencies), true)
        && $inside_window
        && $fresh_audit;
    return [
        'status' => $authorized ? 'authorized' : 'denied',
        'reason' => $authorized
            ? 'Registered staging maintenance is within delegated authority'
            : 'Registered maintenance requires an autonomous target, approved window, and fresh security finding',
        'action_digest' => agent_tool_action_digest($run_uuid, 'apply_patch_updates', $arguments),
        'target' => $target,
        'authorized_at' => $decision_time,
        'expires_at' => $decision_time + 300,
    ];
}

function agent_policy_minute(string $time): int
{
    if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
        throw new RuntimeException('Agent action policy window is invalid');
    }
    [$hour, $minute] = array_map('intval', explode(':', $time));
    return $hour * 60 + $minute;
}

function agent_execute_registered_action(array $arguments, array $dependencies): array
{
    $server = (string)($arguments['server'] ?? '');
    if (!in_array($server, agent_autonomous_targets($dependencies), true)) {
        throw new RuntimeException('Registered action target lacks autonomous authority');
    }
    $authorization = $dependencies['authorization'] ?? null;
    if (!is_array($authorization) || ($authorization['status'] ?? '') !== 'authorized') {
        throw new RuntimeException('Registered action authorization is missing');
    }
    $connector = (array)($dependencies['tool_definition']['connector'] ?? []);
    $action = (string)($connector['registered_action'] ?? '');
    $target = agent_connector_ssh_target($server, $dependencies, $connector);
    $signing_key = (string)($target['gateway_signing_key'] ?? '');
    if ($action === '' || $signing_key === '') {
        throw new RuntimeException('Registered action gateway configuration is incomplete');
    }
    $envelope = [
        'target' => $server,
        'action' => $action,
        'arguments' => [],
        'action_digest' => (string)$authorization['action_digest'],
        'expires_at' => (int)$authorization['expires_at'],
    ];
    $envelope['signature'] = hash_hmac('sha256', agent_canonical_json($envelope), $signing_key);
    $encoded = rtrim(strtr(base64_encode(json_encode($envelope, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
    $transport = agent_connector_ssh_gateway(
        $target,
        ['execute_registered_action', $encoded],
        (int)($connector['timeout'] ?? 1500),
        $dependencies
    );
    $result = json_decode((string)($transport['stdout'] ?? ''), true);
    if (is_array($result) && ($result['status'] ?? '') === 'blocked') {
        return $result + ['server' => $server, 'retryable' => false];
    }
    if (!is_array($result) || !isset($result['status'], $result['transaction_id'], $result['started_at'])) {
        throw new RuntimeException('Registered action gateway returned invalid evidence');
    }
    return $result + ['server' => $server, 'action_digest' => (string)$authorization['action_digest']];
}

function agent_target_scope_map(array $dependencies): array
{
    $result = [];
    foreach ((array)agent_config($dependencies, 'targets', []) as $target) {
        if (is_array($target) && !empty($target['identity']) && !empty($target['scope'])) {
            $result[(string)$target['identity']] = (string)$target['scope'];
        }
    }
    return $result;
}

function agent_authorize_health_resolution(array $arguments, string $run_uuid, array $dependencies): array
{
    $server = (string)($arguments['server'] ?? '');
    $scope = (string)($arguments['environment'] ?? '');
    $report_uuid = (string)($arguments['report_uuid'] ?? '');
    $finding_id = (string)($arguments['finding_id'] ?? '');
    $targets = agent_target_scope_map($dependencies);
    $report = data_read('.infra_health_reports', $report_uuid);
    $latest_uuid = '';
    foreach (data_read('.infra_health_environments') ?: [] as $environment) {
        if (($environment['server'] ?? '') === $server && ($environment['environment'] ?? '') === $scope) {
            $latest_uuid = (string)($environment['last_report_uuid'] ?? '');
            break;
        }
    }
    $finding_exists = false;
    foreach ((array)($report['audit']['findings'] ?? []) as $finding) {
        if (($finding['id'] ?? '') === $finding_id) {
            $finding_exists = true;
            break;
        }
    }
    $fresh = agent_report_tool_results($run_uuid, 'inspect_host_health', array_keys($targets), [], true);
    $authorized = ($targets[$server] ?? '') === $scope
        && $latest_uuid === $report_uuid
        && is_array($report)
        && $finding_exists
        && isset($fresh[$server])
        && in_array(($arguments['classification'] ?? ''), ['recovered', 'historical', 'false_positive'], true)
        && strlen(trim((string)($arguments['resolution'] ?? ''))) >= 30
        && strlen(trim((string)($arguments['verification'] ?? ''))) >= 30;
    return [
        'status' => $authorized ? 'authorized' : 'denied',
        'reason' => $authorized
            ? 'Canonical finding and fresh evidence are present'
            : 'Resolution lacks canonical provenance or fresh evidence',
        'action_digest' => agent_tool_action_digest($run_uuid, 'resolve_health_finding', $arguments),
        'target' => 'infra-health:' . $server,
        'authorized_at' => time(),
        'expires_at' => time() + 300,
    ];
}

function agent_execute_health_resolution(array $arguments, array $dependencies): array
{
    if (($dependencies['authorization']['status'] ?? '') !== 'authorized') {
        throw new RuntimeException('Health resolution authorization is missing');
    }
    $report = data_read('.infra_health_reports', (string)$arguments['report_uuid']);
    $finding = null;
    foreach ((array)($report['audit']['findings'] ?? []) as $candidate) {
        if (($candidate['id'] ?? '') === ($arguments['finding_id'] ?? '')) {
            $finding = $candidate;
            break;
        }
    }
    if (!is_array($finding)) {
        throw new RuntimeException('Canonical health finding is unavailable');
    }
    $resolved_at = time();
    $uuid = substr(hash('sha256', implode("\n", [
        (string)$arguments['server'], (string)$arguments['report_uuid'], (string)$arguments['finding_id'],
    ])), 0, 16);
    $record = [
        'environment' => (string)$arguments['environment'],
        'server' => (string)$arguments['server'],
        'report_uuid' => (string)$arguments['report_uuid'],
        'finding_id' => (string)$arguments['finding_id'],
        'title' => (string)($finding['title'] ?? 'Infrastructure finding'),
        'evidence' => (string)($finding['evidence'] ?? ''),
        'resolution' => ucfirst((string)$arguments['classification']) . ': '
            . trim((string)$arguments['resolution']) . ' Verification: '
            . trim((string)$arguments['verification']),
        'resolved_at' => $resolved_at,
    ];
    if (!data_exists('.infra_health_resolutions', $uuid)
        && !data_create('.infra_health_resolutions', $uuid, $record)) {
        throw new RuntimeException('Health finding resolution could not be recorded');
    }
    return [
        'status' => 'resolved',
        'server' => (string)$arguments['server'],
        'report_uuid' => (string)$arguments['report_uuid'],
        'finding_id' => (string)$arguments['finding_id'],
        'classification' => (string)$arguments['classification'],
        'resolved_at' => $resolved_at,
    ];
}
