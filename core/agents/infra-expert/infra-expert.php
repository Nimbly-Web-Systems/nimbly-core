<?php

require_once BASE_DIR . 'core/modules/agent/lib/agent-connector.php';
require_once BASE_DIR . 'core/modules/agent/lib/agent-report.php';

function infra_expert_configure(array $dependencies = []): array
{
    $configured = function_exists('agent_config') ? agent_config($dependencies, 'infrastructure', []) : [];
    $defaults = [
        'targets' => function_exists('agent_config') ? agent_config($dependencies, 'targets', []) : [],
        'inventory_env' => 'INFRA_AGENT_INVENTORY',
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
        $result[(string)($target['identity'] ?? '')] = (string)($target['scope'] ?? '');
    }
    return array_filter($result, fn($environment, $server) => $server !== '' && $environment !== '', ARRAY_FILTER_USE_BOTH);
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
    $fresh_audits = agent_report_tool_results(
        $run_uuid,
        'inspect_host_health',
        array_keys($expected),
        [],
        true
    );
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

function infra_expert_execute_remediation(array $arguments, array $dependencies): array
{
    infra_expert_configure($dependencies);
    $server = (string)($arguments['server'] ?? '');
    $remediation_servers = array_column(infra_expert_targets('autonomous_remediation'), 'identity');
    if (!in_array($server, $remediation_servers, true)) {
        throw new RuntimeException('Remediation target is not configured for autonomous authority');
    }
    $authorization = $dependencies['authorization'] ?? null;
    if (!is_array($authorization) || ($authorization['status'] ?? '') !== 'authorized') {
        throw new RuntimeException('Remediation authorization is missing');
    }
    $connector = $dependencies['tool_definition']['connector'] ?? [];
    $target = agent_connector_ssh_target($server, $dependencies, $connector);
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
    $result = agent_connector_ssh_gateway($target, ['execute_action', $encoded], 90, $dependencies);
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

function infra_expert_execute_registered_action(array $arguments, array $dependencies): array
{
    infra_expert_configure($dependencies);
    $server = (string)($arguments['server'] ?? '');
    $remediation_servers = array_column(infra_expert_targets('autonomous_remediation'), 'identity');
    if (!in_array($server, $remediation_servers, true)) {
        throw new RuntimeException('Registered action target is not configured for autonomous authority');
    }
    $authorization = $dependencies['authorization'] ?? null;
    if (!is_array($authorization) || ($authorization['status'] ?? '') !== 'authorized') {
        throw new RuntimeException('Registered action authorization is missing');
    }
    $connector = $dependencies['tool_definition']['connector'] ?? [];
    $action = (string)($connector['registered_action'] ?? '');
    if ($action === '') {
        throw new RuntimeException('Registered action is not configured');
    }
    $target = agent_connector_ssh_target($server, $dependencies, $connector);
    $signing_key = (string)($target['gateway_signing_key'] ?? '');
    if ($signing_key === '') {
        throw new RuntimeException('Registered action gateway signing key is unavailable');
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
    $result = agent_connector_ssh_gateway(
        $target,
        ['execute_registered_action', $encoded],
        (int)($connector['timeout'] ?? 1500),
        $dependencies
    );
    $decoded = json_decode((string)($result['stdout'] ?? ''), true);
    if (!is_array($decoded) || !isset($decoded['status'], $decoded['transaction_id'], $decoded['started_at'])) {
        throw new RuntimeException('Registered action gateway returned invalid evidence');
    }
    $decoded['server'] = $server;
    $decoded['action_digest'] = (string)$authorization['action_digest'];
    return $decoded;
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
    $event_context = (array)($_dependencies['run']['event_context'] ?? []);
    $uptime_event = ($event_context['type'] ?? '') === 'uptime_outage';
    $identities = array_keys($expected);
    $fresh_audits = $run_uuid === '' ? [] : agent_report_tool_results(
        $run_uuid, 'inspect_host_health', $identities, [], true
    );
    $runtime_details = $run_uuid === '' ? [] : agent_report_tool_results(
        $run_uuid, 'inspect_host_detail', $identities, ['check' => 'runtime'], true
    );
    $fresh_runtimes = [];
    foreach ($identities as $identity) {
        $fresh_runtimes[$identity] = (array)(
            $fresh_audits[$identity]['runtime']
            ?? $runtime_details[$identity]['details']
            ?? []
        );
    }
    $remediations = $run_uuid === '' ? [] : agent_report_tool_results(
        $run_uuid, 'execute_remediation', $identities
    );
    $patch_updates = $run_uuid === '' ? [] : agent_report_tool_results(
        $run_uuid, 'apply_patch_updates', $identities
    );
    $maintenance = $run_uuid === '' ? [] : agent_report_tool_results(
        $run_uuid, 'inspect_maintenance', $identities
    );
    $diagnostics = $run_uuid === '' ? [] : agent_report_tool_results(
        $run_uuid, 'run_diagnostic', $identities
    );
    $canonical_drift = array_filter(
        $canonical_reviews,
        fn($review) => in_array('runtime:environment-drift', (array)($review['runtime_finding_ids'] ?? []), true)
    );
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
        if (!is_array($canonical_review) && $uptime_event && ($event_context['server'] ?? '') === $server) {
            $canonical_review = [
                'source_report_uuid' => '',
                'review_status' => 'missing',
                'overall' => 'unknown',
                'generated_at' => 0,
                'age_seconds' => 0,
            ];
        }
        if (!is_array($canonical_review)) {
            throw new RuntimeException('Infrastructure canonical review is unavailable');
        }
        $item['review_status'] = $canonical_review['review_status'];
        $item['source_report_uuid'] = $canonical_review['source_report_uuid'];
        $item['source_report'] = $canonical_review;
        if ($item['review_status'] !== 'missing' && $item['source_report_uuid'] === '') {
            throw new RuntimeException('Infrastructure source report UUID is missing');
        }
        $item['status'] = (string)($item['status'] ?? 'warning');
        if (!in_array($item['status'], ['healthy', 'notice', 'warning', 'degraded', 'critical'], true)) {
            throw new RuntimeException('Infrastructure status is invalid');
        }
        $item['email_subject'] = substr(trim((string)($item['email_subject'] ?? '')), 0, 160);
        $item['executive_summary'] = substr(trim((string)($item['executive_summary'] ?? '')), 0, 6000);
        if ($item['email_subject'] === '' || $item['executive_summary'] === '') {
            throw new RuntimeException('Infrastructure executive email is invalid');
        }
        if ($run_uuid !== '') {
            if ($uptime_event && !isset($fresh_audits[$server])) {
                throw new RuntimeException('An uptime incident requires a fresh host audit');
            }
            if (($canonical_review['overall'] ?? 'unknown') !== 'ok' && !isset($fresh_audits[$server])) {
                throw new RuntimeException('A non-healthy infrastructure report requires a fresh host audit');
            }
            if (isset($fresh_audits[$server])) {
                $item['fresh_audit'] = $fresh_audits[$server];
            }
            infra_expert_validate_runtime_evidence(
                $server,
                $canonical_review,
                $fresh_runtimes,
                $identities,
                $canonical_drift !== []
            );
            $server_remediations = $remediations[$server] ?? [];
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
            foreach ($patch_updates[$server] ?? [] as $patch_update) {
                $started_at = (int)($patch_update['started_at'] ?? 0);
                $completed = array_values(array_filter(
                    $maintenance[$server] ?? [],
                    fn($item) => ($item['status'] ?? '') === 'completed'
                        && ($item['transaction_id'] ?? '') === ($patch_update['transaction_id'] ?? '')
                        && (int)($item['completed_at'] ?? 0) >= $started_at
                ));
                if ($completed === []) {
                    throw new RuntimeException('Patch maintenance has no completed durable transaction');
                }
                $completed_at = max(array_map(fn($item) => (int)$item['completed_at'], $completed));
                if ((int)($fresh_audits[$server]['observed_at'] ?? 0) <= $completed_at) {
                    throw new RuntimeException('Patch maintenance has no fresh post-action host audit');
                }
                $post_diagnostics = array_filter(
                    $diagnostics[$server] ?? [],
                    fn($diagnostic) => (int)($diagnostic['observed_at'] ?? 0) > $completed_at
                );
                if ($post_diagnostics === []) {
                    throw new RuntimeException('Patch maintenance has no post-action endpoint, service, or log verification');
                }
            }
        }
        $validated[$server] = $item;
    }
    if (count($validated) !== count($expected)) {
        throw new RuntimeException('Infrastructure result duplicates an environment');
    }
    return ['environments' => array_values($validated)];
}

function infra_expert_validate_runtime_evidence(
    string $server,
    array $canonical_review,
    array $fresh_runtimes,
    array $identities,
    bool $canonical_drift
): void {
    $canonical_runtime_findings = (array)($canonical_review['runtime_finding_ids'] ?? []);
    if ($canonical_runtime_findings !== [] && !infra_expert_runtime_evidence_complete((array)($fresh_runtimes[$server] ?? []))) {
        throw new RuntimeException('Runtime findings require comparable fresh evidence');
    }
    if (!$canonical_drift) {
        return;
    }
    foreach ($identities as $identity) {
        $runtime = (array)($fresh_runtimes[$identity] ?? []);
        if (!infra_expert_runtime_evidence_complete($runtime)) {
            throw new RuntimeException('Environment drift requires fresh runtime evidence from every host');
        }
    }
}

function infra_expert_runtime_evidence_complete(array $runtime): bool
{
    foreach (['ubuntu_version', 'web_php_version', 'cli_php_version', 'php_handler'] as $field) {
        if (trim((string)($runtime[$field] ?? '')) === '') {
            return false;
        }
    }
    return true;
}

function infra_expert_runtime_comparison(array $runtime): array
{
    return array_intersect_key($runtime, array_flip([
        'ubuntu_version', 'web_php_version', 'cli_php_version', 'php_handler',
    ]));
}

function infra_expert_runtime_finding_ids(array $findings): array
{
    $ids = [];
    foreach ($findings as $finding) {
        $id = is_array($finding) ? (string)($finding['id'] ?? '') : '';
        if (str_starts_with($id, 'runtime:')) {
            $ids[] = $id;
        }
    }
    return array_values(array_unique($ids));
}

function infra_expert_canonical_reviews(): array
{
    $reviews = [];
    foreach (infra_expert_target_map() as $server => $_scope) {
        $reviews[$server] = [
            'source_report_uuid' => '',
            'review_status' => 'missing',
            'overall' => 'unknown',
            'generated_at' => 0,
            'age_seconds' => 0,
            'runtime_finding_ids' => [],
        ];
    }
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
            'runtime_finding_ids' => infra_expert_runtime_finding_ids((array)($report['audit']['findings'] ?? [])),
        ];
    }
    return $reviews;
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
