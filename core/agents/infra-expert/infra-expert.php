<?php

require_once BASE_DIR . 'core/modules/agent/lib/agent-connector.php';

function infra_expert_configure(array $dependencies = []): array
{
    $configured = function_exists('agent_config')
        ? agent_config($dependencies, 'infrastructure', [])
        : [];
    $defaults = [
        'targets' => [],
        'inventory_env' => 'INFRA_AGENT_INVENTORY',
        'report_recipient_env' => 'INFRA_EXPERT_REPORT_RECIPIENT',
        'report_recipient_default' => '',
        'greeting' => '',
    ];
    $GLOBALS['INFRA_EXPERT_CONFIG'] = array_replace($defaults, is_array($configured) ? $configured : []);
    return $GLOBALS['INFRA_EXPERT_CONFIG'];
}

function infra_expert_config(): array
{
    return $GLOBALS['INFRA_EXPERT_CONFIG'] ?? infra_expert_configure();
}

function infra_expert_targets(?string $authority = null): array
{
    return array_values(array_filter(
        (array)(infra_expert_config()['targets'] ?? []),
        fn($target) => is_array($target)
            && ($authority === null || ($target['authority'] ?? '') === $authority)
    ));
}

function infra_expert_target_map(): array
{
    $result = [];
    foreach (infra_expert_targets() as $target) {
        $result[(string)($target['server'] ?? '')] = (string)($target['environment'] ?? '');
    }
    return array_filter($result, fn($environment, $server) => $server !== '' && $environment !== '', ARRAY_FILTER_USE_BOTH);
}

function infra_expert_prepare_input(array $_run, array $_dependencies): array
{
    load_library('data');
    infra_expert_configure($_dependencies);
    $expected = infra_expert_targets();
    $environments = data_read('.infra_health_environments') ?: [];
    $all_reports = data_read('.infra_health_reports') ?: [];
    $input = [];
    $source_uuids = [];
    foreach ($expected as $target) {
        $environment_record = null;
        foreach ($environments as $candidate) {
            if (($candidate['environment'] ?? '') === $target['environment']
                && ($candidate['server'] ?? '') === $target['server']) {
                $environment_record = $candidate;
                break;
            }
        }
        $report_uuid = (string)($environment_record['last_report_uuid'] ?? '');
        $report = $report_uuid === '' ? null : data_read('.infra_health_reports', $report_uuid);
        $late_after = (int)($environment_record['late_after'] ?? 93600);
        $received_at = (int)($report['received_at'] ?? 0);
        $review_status = !is_array($report) ? 'missing' : (time() - $received_at > $late_after ? 'stale' : 'reviewed');
        if (is_array($report)) {
            $source_uuids[] = $report_uuid;
        }
        $findings = [];
        foreach (($report['audit']['findings'] ?? []) as $finding) {
            if (!is_array($finding)) {
                continue;
            }
            $findings[] = [
                'id' => (string)($finding['id'] ?? ''),
                'severity' => (string)($finding['severity'] ?? ''),
                'scope' => (string)($finding['scope'] ?? ''),
                'evidence' => (string)($finding['evidence'] ?? ''),
            ];
        }
        $input[] = [
            'environment' => $target['environment'],
            'server' => $target['server'],
            'source_report_uuid' => $report_uuid,
            'review_status' => $review_status,
            'generated_at' => (int)($report['generated_at'] ?? 0),
            'overall' => (string)($report['overall'] ?? 'unknown'),
            'findings' => $findings,
            'recent_reports' => infra_expert_recent_report_history($all_reports, $target['server'], 7),
        ];
    }
    return [
        'source_report_uuids' => array_values(array_unique($source_uuids)),
        'input' => [[
            'role' => 'user',
            'content' => [[
                'type' => 'input_text',
                'text' => json_encode([
                    'expected_environments' => $input,
                    'recent_agent_actions' => infra_expert_recent_action_history(30),
                    'authority' => array_reduce(
                        $expected,
                        function (array $carry, array $target): array {
                            $carry[(string)($target['authority'] ?? 'inspection_only')][] = (string)$target['server'];
                            return $carry;
                        },
                        []
                    ),
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]],
        ]],
    ];
}

function infra_expert_recent_report_history(array $reports, string $server, int $limit): array
{
    $history = [];
    foreach ($reports as $report) {
        if (!is_array($report) || ($report['server'] ?? '') !== $server) {
            continue;
        }
        $history[] = [
            'uuid' => (string)($report['uuid'] ?? ''),
            'generated_at' => (int)($report['generated_at'] ?? 0),
            'overall' => (string)($report['overall'] ?? 'unknown'),
            'findings' => array_map(
                fn($finding) => [
                    'id' => substr((string)($finding['id'] ?? ''), 0, 160),
                    'severity' => substr((string)($finding['severity'] ?? ''), 0, 20),
                    'evidence' => substr((string)($finding['evidence'] ?? ''), 0, 500),
                ],
                array_slice((array)($report['audit']['findings'] ?? []), 0, 50)
            ),
        ];
    }
    usort($history, fn($a, $b) => $b['generated_at'] <=> $a['generated_at']);
    return array_slice($history, 0, $limit);
}

function infra_expert_recent_action_history(int $limit): array
{
    $history = [];
    foreach (data_read('.agent_events') ?: [] as $event) {
        if (!in_array(($event['type'] ?? ''), ['risk_decision', 'tool_completed'], true)) {
            continue;
        }
        $payload = (array)($event['payload'] ?? []);
        if (($event['type'] ?? '') === 'tool_completed'
            && !in_array(($payload['tool'] ?? ''), ['execute_remediation', 'run_diagnostic'], true)) {
            continue;
        }
        $history[] = [
            'occurred_at' => (int)($event['occurred_at'] ?? 0),
            'type' => (string)$event['type'],
            'tool' => (string)($payload['tool'] ?? ''),
            'payload' => $payload,
        ];
    }
    usort($history, fn($a, $b) => $b['occurred_at'] <=> $a['occurred_at']);
    return array_slice($history, 0, $limit);
}

function infra_expert_inspect_service(array $arguments, array $dependencies): array
{
    infra_expert_configure($dependencies);
    $server = (string)($arguments['server'] ?? '');
    $service = (string)($arguments['service'] ?? '');
    if (!isset(infra_expert_target_map()[$server])
        || !in_array($service, ['apache2', 'cron', 'fail2ban'], true)) {
        throw new RuntimeException('SSH tool target is not permitted');
    }
    $inventory = $dependencies['ssh_inventory'] ?? infra_expert_ssh_inventory();
    $target = $inventory[$server] ?? null;
    if (!is_array($target)) {
        throw new RuntimeException('SSH target is not configured');
    }
    foreach (['ssh_target', 'identity_file', 'known_hosts_file'] as $key) {
        if (trim((string)($target[$key] ?? '')) === '') {
            throw new RuntimeException('SSH target configuration is incomplete');
        }
    }
    if (preg_match('/^[A-Za-z0-9._-]+@[A-Za-z0-9.-]+$/', $target['ssh_target']) !== 1
        || !str_starts_with($target['identity_file'], '/')
        || !str_starts_with($target['known_hosts_file'], '/')) {
        throw new RuntimeException('SSH target configuration is invalid');
    }
    $command = [
        '/usr/bin/timeout', '30', '/usr/bin/ssh', '-T',
        '-o', 'BatchMode=yes',
        '-o', 'IdentitiesOnly=yes',
        '-o', 'ConnectTimeout=10',
        '-o', 'StrictHostKeyChecking=yes',
        '-o', 'UserKnownHostsFile=' . $target['known_hosts_file'],
        '-i', $target['identity_file'],
        $target['ssh_target'],
        'inspect_service', $service,
    ];
    $runner = $dependencies['process_runner'] ?? 'infra_expert_run_process';
    $result = $runner($command);
    if (!is_array($result) || (int)($result['exit_code'] ?? 1) !== 0) {
        throw new RuntimeException('Restricted SSH service inspection failed');
    }
    $decoded = json_decode((string)($result['stdout'] ?? ''), true);
    if (!is_array($decoded)
        || ($decoded['service'] ?? '') !== $service
        || !isset($decoded['active'])) {
        throw new RuntimeException('Restricted SSH gateway returned invalid evidence');
    }
    return [
        'server' => $server,
        'service' => $service,
        'active' => (bool)$decoded['active'],
        'sub_state' => substr((string)($decoded['sub_state'] ?? ''), 0, 80),
        'observed_at' => (int)($decoded['observed_at'] ?? time()),
        'evidence' => substr((string)($decoded['evidence'] ?? ''), 0, 2000),
    ];
}

function infra_expert_inspect_host_health(array $arguments, array $dependencies): array
{
    infra_expert_configure($dependencies);
    $server = (string)($arguments['server'] ?? '');
    if (!isset(infra_expert_target_map()[$server])) {
        throw new RuntimeException('SSH host-audit target is not permitted');
    }
    $inventory = $dependencies['ssh_inventory'] ?? infra_expert_ssh_inventory();
    $target = $inventory[$server] ?? null;
    if (!is_array($target)) {
        throw new RuntimeException('SSH target is not configured');
    }
    $command = [
        '/usr/bin/timeout', '60', '/usr/bin/ssh', '-T', '-o', 'BatchMode=yes', '-o', 'IdentitiesOnly=yes',
        '-o', 'ConnectTimeout=10', '-o', 'StrictHostKeyChecking=yes',
        '-o', 'UserKnownHostsFile=' . $target['known_hosts_file'], '-i', $target['identity_file'],
        $target['ssh_target'], 'inspect_host_health',
    ];
    $runner = $dependencies['process_runner'] ?? 'infra_expert_run_process';
    $result = $runner($command);
    $decoded = json_decode((string)($result['stdout'] ?? ''), true);
    if (!is_array($result) || (int)($result['exit_code'] ?? 1) !== 0 || !is_array($decoded) || !isset($decoded['overall'], $decoded['findings'])) {
        throw new RuntimeException('Restricted SSH host audit failed');
    }
    return [
        'server' => $server,
        'overall' => substr((string)$decoded['overall'], 0, 20),
        'summary' => (array)($decoded['summary'] ?? []),
        'findings' => array_values((array)$decoded['findings']),
        'observed_at' => (int)($decoded['observed_at'] ?? time()),
    ];
}

function infra_expert_authorize_resolution(array $arguments, string $run_uuid, array $_dependencies): array
{
    infra_expert_configure($_dependencies);
    $server = (string)($arguments['server'] ?? '');
    $environment = (string)($arguments['environment'] ?? '');
    $report_uuid = trim((string)($arguments['report_uuid'] ?? ''));
    $finding_id = trim((string)($arguments['finding_id'] ?? ''));
    $classification = (string)($arguments['classification'] ?? '');
    $resolution = trim((string)($arguments['resolution'] ?? ''));
    $verification = trim((string)($arguments['verification'] ?? ''));
    $expected = infra_expert_target_map();
    $authorized = isset($expected[$server])
        && $expected[$server] === $environment
        && in_array($classification, ['recovered', 'historical', 'false_positive'], true)
        && strlen($resolution) >= 30
        && strlen($verification) >= 30;
    $report = $authorized ? data_read('.infra_health_reports', $report_uuid) : null;
    $latest_report_uuid = '';
    foreach (data_read('.infra_health_environments') ?: [] as $environment_record) {
        if (($environment_record['server'] ?? '') === $server
            && ($environment_record['environment'] ?? '') === $environment) {
            $latest_report_uuid = (string)($environment_record['last_report_uuid'] ?? '');
            break;
        }
    }
    $finding = null;
    foreach ((array)($report['audit']['findings'] ?? []) as $candidate) {
        if (($candidate['id'] ?? '') === $finding_id) {
            $finding = $candidate;
            break;
        }
    }
    $fresh_audits = infra_expert_completed_host_audits($run_uuid);
    $authorized = $authorized
        && is_array($report)
        && $latest_report_uuid === $report_uuid
        && ($report['server'] ?? '') === $server
        && ($report['environment'] ?? '') === $environment
        && is_array($finding)
        && isset($fresh_audits[$server]);
    return [
        'status' => $authorized ? 'authorized' : 'denied',
        'reason' => $authorized
            ? 'Canonical finding and fresh investigation evidence are present'
            : 'Resolution lacks canonical finding provenance or fresh investigation evidence',
        'action_digest' => agent_tool_action_digest($run_uuid, 'resolve_health_finding', $arguments),
        'target' => 'infra-health:' . $server,
        'authorized_at' => time(),
        'expires_at' => time() + 300,
    ];
}

function infra_expert_resolve_health_finding(array $arguments, array $dependencies): array
{
    $authorization = $dependencies['authorization'] ?? null;
    if (!is_array($authorization) || ($authorization['status'] ?? '') !== 'authorized') {
        throw new RuntimeException('Health finding resolution authorization is missing');
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
        (string)$arguments['server'],
        (string)$arguments['report_uuid'],
        (string)$arguments['finding_id'],
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

function infra_expert_run_diagnostic(array $arguments, array $dependencies): array
{
    infra_expert_configure($dependencies);
    $server = (string)($arguments['server'] ?? '');
    $command_text = trim((string)($arguments['command'] ?? ''));
    $diagnostic_servers = array_column(infra_expert_targets('autonomous_remediation'), 'server');
    if (!in_array($server, $diagnostic_servers, true)) {
        throw new RuntimeException('Diagnostic target is not permitted');
    }
    if ($command_text === '' || strlen($command_text) > 2000 || str_contains($command_text, "\0")) {
        throw new RuntimeException('Diagnostic command is invalid');
    }
    $target = infra_expert_ssh_target($server, $dependencies);
    $encoded = rtrim(strtr(base64_encode($command_text), '+/', '-_'), '=');
    $result = infra_expert_run_ssh_gateway($target, ['diagnose', $encoded], 60, $dependencies);
    $decoded = json_decode((string)($result['stdout'] ?? ''), true);
    if (!is_array($decoded) || !isset($decoded['exit_code'], $decoded['observed_at'])) {
        throw new RuntimeException('Diagnostic gateway returned invalid evidence');
    }
    return [
        'server' => $server,
        'command' => $command_text,
        'hypothesis' => substr(trim((string)($arguments['hypothesis'] ?? '')), 0, 500),
        'exit_code' => (int)$decoded['exit_code'],
        'stdout' => substr((string)($decoded['stdout'] ?? ''), 0, 20000),
        'stderr' => substr((string)($decoded['stderr'] ?? ''), 0, 4000),
        'observed_at' => (int)$decoded['observed_at'],
    ];
}

function infra_expert_execute_remediation(array $arguments, array $dependencies): array
{
    infra_expert_configure($dependencies);
    $server = (string)($arguments['server'] ?? '');
    $remediation_servers = array_column(infra_expert_targets('autonomous_remediation'), 'server');
    if (!in_array($server, $remediation_servers, true)) {
        throw new RuntimeException('Remediation target is not configured for autonomous authority');
    }
    $authorization = $dependencies['authorization'] ?? null;
    if (!is_array($authorization) || ($authorization['status'] ?? '') !== 'authorized') {
        throw new RuntimeException('Remediation authorization is missing');
    }
    $target = infra_expert_ssh_target($server, $dependencies);
    $signing_key = (string)($target['gateway_signing_key'] ?? '');
    if ($signing_key === '') {
        throw new RuntimeException('Remediation gateway signing key is unavailable');
    }
    $envelope = [
        'target' => $server,
        'command' => (string)$arguments['command'],
        'action_digest' => (string)$authorization['action_digest'],
        'expires_at' => (int)$authorization['expires_at'],
        'rollback' => (string)$arguments['rollback'],
    ];
    $canonical = agent_canonical_json($envelope);
    $envelope['signature'] = hash_hmac('sha256', $canonical, $signing_key);
    $encoded = rtrim(strtr(base64_encode(json_encode($envelope, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
    $result = infra_expert_run_ssh_gateway($target, ['execute_action', $encoded], 90, $dependencies);
    $decoded = json_decode((string)($result['stdout'] ?? ''), true);
    if (!is_array($decoded) || !isset($decoded['exit_code'], $decoded['executed_at'])) {
        throw new RuntimeException('Remediation gateway returned invalid evidence');
    }
    return [
        'server' => $server,
        'action_digest' => (string)$authorization['action_digest'],
        'command' => (string)$arguments['command'],
        'exit_code' => (int)$decoded['exit_code'],
        'stdout' => substr((string)($decoded['stdout'] ?? ''), 0, 12000),
        'stderr' => substr((string)($decoded['stderr'] ?? ''), 0, 3000),
        'executed_at' => (int)$decoded['executed_at'],
        'rollback' => (string)$arguments['rollback'],
    ];
}

function infra_expert_ssh_target(string $server, array $dependencies): array
{
    $inventory = $dependencies['ssh_inventory'] ?? infra_expert_ssh_inventory();
    $target = $inventory[$server] ?? null;
    if (!is_array($target)) {
        throw new RuntimeException('SSH target is not configured');
    }
    foreach (['ssh_target', 'identity_file', 'known_hosts_file'] as $key) {
        if (trim((string)($target[$key] ?? '')) === '') {
            throw new RuntimeException('SSH target configuration is incomplete');
        }
    }
    return $target;
}

function infra_expert_run_ssh_gateway(array $target, array $gateway_arguments, int $timeout, array $dependencies): array
{
    $command = [
        '/usr/bin/timeout', (string)$timeout, '/usr/bin/ssh', '-T',
        '-o', 'BatchMode=yes', '-o', 'IdentitiesOnly=yes', '-o', 'ConnectTimeout=10',
        '-o', 'StrictHostKeyChecking=yes', '-o', 'UserKnownHostsFile=' . $target['known_hosts_file'],
        '-i', $target['identity_file'], $target['ssh_target'], ...$gateway_arguments,
    ];
    $runner = $dependencies['process_runner'] ?? 'infra_expert_run_process';
    $result = $runner($command);
    if (!is_array($result) || (int)($result['exit_code'] ?? 1) !== 0) {
        throw new RuntimeException('Infrastructure SSH gateway command failed');
    }
    return $result;
}

function infra_expert_ssh_inventory(): array
{
    load_library('env');
    $path = env((string)(infra_expert_config()['inventory_env'] ?? 'INFRA_AGENT_INVENTORY'));
    if ($path === '' || !str_starts_with($path, '/') || !is_readable($path)) {
        throw new RuntimeException('Infrastructure agent inventory is unavailable');
    }
    $inventory = json_decode((string)file_get_contents($path), true);
    if (!is_array($inventory)) {
        throw new RuntimeException('Infrastructure agent inventory is invalid');
    }
    return $inventory;
}

function infra_expert_run_process(array $command): array
{
    $pipes = [];
    $process = proc_open($command, [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start restricted SSH process');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit_code = proc_close($process);
    return [
        'exit_code' => $exit_code,
        'stdout' => substr((string)$stdout, 0, 12000),
        'stderr' => substr((string)$stderr, 0, 2000),
    ];
}

function infra_expert_validate_result(array $result, string $run_uuid = '', array $_dependencies = []): array
{
    infra_expert_configure($_dependencies);
    $items = $result['environments'] ?? null;
    $expected = infra_expert_target_map();
    if (!is_array($items) || count($items) !== count($expected)) {
        throw new RuntimeException('Infrastructure result must contain every configured environment');
    }
    $canonical_reviews = infra_expert_canonical_reviews();
    $observed = $run_uuid === '' ? [] : infra_expert_completed_inspections($run_uuid);
    $fresh_audits = $run_uuid === '' ? [] : infra_expert_completed_host_audits($run_uuid);
    $remediations = $run_uuid === '' ? [] : infra_expert_completed_remediations($run_uuid);
    $diagnostics = $run_uuid === '' ? [] : infra_expert_completed_diagnostics($run_uuid);
    $validated = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            throw new RuntimeException('Infrastructure environment result is invalid');
        }
        $server = (string)($item['server'] ?? '');
        if (!isset($expected[$server]) || ($item['environment'] ?? '') !== $expected[$server]) {
            throw new RuntimeException('Infrastructure result contains an unexpected environment');
        }
        $canonical_review = $canonical_reviews[$server] ?? null;
        if (!is_array($canonical_review)) {
            throw new RuntimeException('Infrastructure canonical review is unavailable');
        }
        $item['review_status'] = $canonical_review['review_status'];
        $item['source_report_uuid'] = $canonical_review['source_report_uuid'];
        $item['source_report'] = $canonical_review;
        if ($item['review_status'] !== 'missing' && $item['source_report_uuid'] === '') {
            throw new RuntimeException('Infrastructure source report UUID is missing');
        }
        foreach (['automatically_fixed', 'still_needs_fixing', 'what_you_can_do'] as $section) {
            if (!isset($item[$section]) || !is_array($item[$section])) {
                throw new RuntimeException('Infrastructure report section is invalid');
            }
            $item[$section] = array_values(array_map(
                fn($value) => substr(trim((string)$value), 0, 500),
                $item[$section]
            ));
        }
        $item['status'] = (string)($item['status'] ?? 'warning');
        if (!in_array($item['status'], ['healthy', 'warning', 'degraded', 'critical'], true)) {
            throw new RuntimeException('Infrastructure status is invalid');
        }
        $item['email_subject'] = substr(trim((string)($item['email_subject'] ?? '')), 0, 160);
        $item['executive_summary'] = substr(trim((string)($item['executive_summary'] ?? '')), 0, 6000);
        $greeting = (string)(infra_expert_config()['greeting'] ?? 'Hi team,');
        if ($item['email_subject'] === '' || !str_starts_with($item['executive_summary'], $greeting)) {
            throw new RuntimeException('Infrastructure executive email is invalid');
        }
        if (!is_array($item['action_evidence'] ?? null)) {
            throw new RuntimeException('Infrastructure action evidence is invalid');
        }
        $item['action_evidence'] = array_values(array_unique(array_map(
            fn($digest) => strtolower(trim((string)$digest)),
            $item['action_evidence']
        )));
        if (empty($item['automatically_fixed'])) {
            $item['action_evidence'] = [];
        }
        if (!is_array($item['verification'] ?? null)) {
            throw new RuntimeException('Infrastructure verification is missing');
        }
        if ($run_uuid !== '') {
            if (($canonical_review['overall'] ?? 'unknown') !== 'ok' && !isset($fresh_audits[$server])) {
                throw new RuntimeException('A non-healthy infrastructure report requires a fresh host audit');
            }
            if (isset($observed[$server])) {
                $item['verification'] = $observed[$server];
            }
            if (isset($fresh_audits[$server])) {
                $item['fresh_audit'] = $fresh_audits[$server];
            }
            $server_remediations = $remediations[$server] ?? [];
            $executed_digests = array_values(array_filter(array_map(
                fn($remediation) => strtolower((string)($remediation['action_digest'] ?? '')),
                $server_remediations
            )));
            if (empty($server_remediations)) {
                $item['automatically_fixed'] = [];
                $item['action_evidence'] = [];
            }
            foreach ($item['action_evidence'] as $digest) {
                if (preg_match('/^[a-f0-9]{64}$/', $digest) !== 1 || !in_array($digest, $executed_digests, true)) {
                    throw new RuntimeException('Infrastructure report references unknown action evidence');
                }
            }
            if (!empty($item['automatically_fixed']) && empty($item['action_evidence'])) {
                throw new RuntimeException('Automatic recovery claim has no executed action evidence');
            }
            foreach ($server_remediations as $remediation) {
                $executed_at = (int)($remediation['executed_at'] ?? 0);
                if ((int)($fresh_audits[$server]['observed_at'] ?? 0) <= $executed_at) {
                    throw new RuntimeException('Executed remediation has no fresh post-action host audit');
                }
                $post_diagnostics = array_filter(
                    $diagnostics[$server] ?? [],
                    fn($diagnostic) => (int)($diagnostic['observed_at'] ?? 0) > $executed_at
                );
                if (empty($post_diagnostics)) {
                    throw new RuntimeException('Executed remediation has no post-action endpoint, service, or log verification');
                }
            }
            $item['still_needs_fixing'] = array_values(array_unique($item['still_needs_fixing']));
        }
        $validated[$server] = $item;
    }
    if (count($validated) !== count($expected)) {
        throw new RuntimeException('Infrastructure result duplicates an environment');
    }
    return ['environments' => array_values($validated)];
}

function infra_expert_completed_remediations(string $run_uuid): array
{
    return infra_expert_completed_tool_results($run_uuid, 'execute_remediation');
}

function infra_expert_completed_diagnostics(string $run_uuid): array
{
    return infra_expert_completed_tool_results($run_uuid, 'run_diagnostic');
}

function infra_expert_completed_tool_results(string $run_uuid, string $tool): array
{
    $result = [];
    foreach (data_read('.agent_events') ?: [] as $event) {
        $payload = $event['payload'] ?? [];
        $evidence = $payload['result'] ?? [];
        $server = (string)($evidence['server'] ?? '');
        if (($event['run_uuid'] ?? '') === $run_uuid && ($event['type'] ?? '') === 'tool_completed'
            && ($payload['tool'] ?? '') === $tool
            && isset(infra_expert_target_map()[$server])) {
            $result[$server][] = $evidence;
        }
    }
    return $result;
}

function infra_expert_digest_findings(array $findings): array
{
    $job_groups = [];
    $other = [];
    foreach ($findings as $finding) {
        $id = (string)($finding['id'] ?? 'health finding');
        $severity = strtolower((string)($finding['severity'] ?? 'unknown'));
        if (preg_match('/^job:failed:([^:]+):[0-9a-f]+$/i', $id, $match) === 1) {
            $key = $severity . ':' . $match[1];
            $job_groups[$key]['severity'] = $severity;
            $job_groups[$key]['project'] = $match[1];
            $job_groups[$key]['count'] = (int)($job_groups[$key]['count'] ?? 0) + 1;
            $evidence = trim((string)($finding['evidence'] ?? ''));
            if ($evidence !== '' && strcasecmp($evidence, 'Job handler returned false') !== 0) {
                $job_groups[$key]['reasons'][$evidence] = true;
            }
            continue;
        }
        $other[] = substr(ucfirst($severity) . ': ' . preg_replace('/:[0-9a-f]{24,}$/i', '', $id), 0, 500);
    }
    foreach ($job_groups as $group) {
        $text = sprintf(
            '%d %s job %s for %s',
            $group['count'],
            $group['severity'],
            $group['count'] === 1 ? 'failure' : 'failures',
            $group['project']
        );
        $reasons = array_keys($group['reasons'] ?? []);
        $text .= count($reasons) === 1
            ? ': ' . $reasons[0]
            : '; no specific cause was recorded';
        $other[] = substr($text, 0, 500);
    }
    return $other;
}

function infra_expert_canonical_reviews(): array
{
    $reviews = [];
    foreach (data_read('.infra_health_environments') ?: [] as $environment) {
        $server = (string)($environment['server'] ?? '');
        if (!isset(infra_expert_target_map()[$server])) {
            continue;
        }
        $report_uuid = (string)($environment['last_report_uuid'] ?? '');
        $report = $report_uuid === '' ? null : data_read('.infra_health_reports', $report_uuid);
        $received_at = (int)($report['received_at'] ?? 0);
        $late_after = (int)($environment['late_after'] ?? 93600);
        $reviews[$server] = [
            'source_report_uuid' => is_array($report) ? $report_uuid : '',
            'review_status' => !is_array($report) ? 'missing' : (time() - $received_at > $late_after ? 'stale' : 'reviewed'),
            'overall' => (string)($report['overall'] ?? 'unknown'),
            'generated_at' => (int)($report['generated_at'] ?? 0),
            'age_seconds' => max(0, time() - (int)($report['generated_at'] ?? time())),
        ];
    }
    return $reviews;
}

function infra_expert_completed_host_audits(string $run_uuid): array
{
    $result = [];
    foreach (data_read('.agent_events') ?: [] as $event) {
        $payload = $event['payload'] ?? [];
        $evidence = $payload['result'] ?? [];
        if (($event['run_uuid'] ?? '') === $run_uuid && ($event['type'] ?? '') === 'tool_completed'
            && ($payload['tool'] ?? '') === 'inspect_host_health'
            && isset(infra_expert_target_map()[$evidence['server'] ?? ''])) {
            $result[$evidence['server']] = $evidence;
        }
    }
    return $result;
}

function infra_expert_completed_inspections(string $run_uuid): array
{
    $result = [];
    foreach (data_read('.agent_events') ?: [] as $event) {
        if (($event['run_uuid'] ?? '') !== $run_uuid || ($event['type'] ?? '') !== 'tool_completed') {
            continue;
        }
        $payload = $event['payload'] ?? [];
        $evidence = $payload['result'] ?? [];
        if (($payload['tool'] ?? '') !== 'inspect_service'
            || ($evidence['service'] ?? '') !== 'apache2'
            || !isset(infra_expert_target_map()[$evidence['server'] ?? ''])) {
            continue;
        }
        $result[$evidence['server']] = $evidence;
    }
    return $result;
}

function infra_expert_deliver(array $result, string $run_uuid, array $dependencies): array
{
    infra_expert_configure($dependencies);
    $run = data_read('.agent_runs', $run_uuid);
    if (($run['trigger'] ?? '') === 'manual' && empty($dependencies['force_delivery'])) {
        $shadow = [];
        foreach (infra_expert_targets() as $target) {
            $shadow[(string)$target['environment']] = [
                'accepted' => false,
                'shadow' => true,
                'provider_message_id' => '',
            ];
        }
        return [
            'success' => true,
            'environments' => $shadow,
        ];
    }
    load_libraries(['email', 'env']);
    $config = infra_expert_config();
    $recipient = env(
        (string)($config['report_recipient_env'] ?? 'INFRA_EXPERT_REPORT_RECIPIENT'),
        (string)($config['report_recipient_default'] ?? '')
    );
    $deliveries = [];
    foreach ($result['environments'] as $environment) {
        $renderer = $dependencies['render_report'] ?? 'infra_expert_render_report';
        $html = $renderer($environment);
        $email_data = [
            'service' => 'resend',
            'recipient' => $recipient,
            'subject' => $environment['email_subject'],
            'html' => $html,
            'idempotency_key' => $run_uuid . ':' . $environment['environment'],
        ];
        if (!empty($dependencies['email_request'])) {
            $email_data['request'] = $dependencies['email_request'];
        }
        $sent = email_result($email_data);
        $deliveries[$environment['environment']] = [
            'accepted' => !empty($sent['success']),
            'provider_message_id' => $sent['id'] ?? '',
        ];
        if (empty($sent['success']) || empty($sent['id'])) {
            return ['success' => false, 'environments' => $deliveries, 'error' => 'Resend did not accept both reports'];
        }
    }
    return ['success' => count($deliveries) === 2, 'environments' => $deliveries];
}

function infra_expert_render_report(array $environment): string
{
    load_library('set');
    load_library('run');
    set_variable('infra_report.server', htmlspecialchars($environment['server'], ENT_QUOTES, 'UTF-8'));
    set_variable(
        'infra_report.executive_summary',
        nl2br(htmlspecialchars((string)$environment['executive_summary'], ENT_QUOTES, 'UTF-8'), false)
    );
    $fresh_overall = (string)($environment['fresh_audit']['overall'] ?? 'unknown');
    $timezone = new DateTimeZone('America/Sao_Paulo');
    $generated_at = (int)($environment['source_report']['generated_at'] ?? 0);
    $observed_at = (int)($environment['fresh_audit']['observed_at'] ?? 0);
    $source_time = $generated_at > 0
        ? (new DateTimeImmutable('@' . $generated_at))->setTimezone($timezone)->format('d M Y, H:i T')
        : 'timestamp unavailable';
    $fresh_time = $observed_at > 0
        ? (new DateTimeImmutable('@' . $observed_at))->setTimezone($timezone)->format('d M Y, H:i T')
        : 'timestamp unavailable';
    set_variable('infra_report.source_summary', htmlspecialchars(ucfirst($environment['review_status']) . ' · ' . $source_time, ENT_QUOTES, 'UTF-8'));
    set_variable('infra_report.fresh_summary', htmlspecialchars(ucfirst($fresh_overall) . ' · ' . $fresh_time, ENT_QUOTES, 'UTF-8'));
    set_variable('infra_report.status_label', htmlspecialchars(ucfirst((string)$environment['status']), ENT_QUOTES, 'UTF-8'));
    $template = find_template('email-infra-expert');
    if (!$template) {
        throw new RuntimeException('Infrastructure report template is unavailable');
    }
    return run_buffered($template);
}
