<?php

function agent_report_config(array $dependencies = []): array
{
    $config = agent_config($dependencies, 'report', []);
    if (!is_array($config)) {
        throw new RuntimeException('Agent report configuration is invalid');
    }
    return $config;
}

function agent_report_targets(array $dependencies = [], ?string $authority = null): array
{
    $config = agent_report_config($dependencies);
    $targets = !empty($config['targets_path'])
        ? agent_config($dependencies, (string)$config['targets_path'], [])
        : ($config['targets'] ?? []);
    $scope_field = (string)($config['target_scope_field'] ?? 'scope');
    $identity_field = (string)($config['target_identity_field'] ?? 'identity');
    $result = [];
    foreach ((array)$targets as $target) {
        if (!is_array($target) || ($authority !== null && ($target['authority'] ?? '') !== $authority)) {
            continue;
        }
        $target['scope'] = (string)($target[$scope_field] ?? '');
        $target['identity'] = (string)($target[$identity_field] ?? '');
        $result[] = $target;
    }
    return $result;
}

function agent_report_target_map(array $dependencies = []): array
{
    $result = [];
    foreach (agent_report_targets($dependencies) as $target) {
        $identity = (string)($target['identity'] ?? '');
        $scope = (string)($target['scope'] ?? '');
        if ($identity !== '' && $scope !== '') {
            $result[$identity] = $scope;
        }
    }
    return $result;
}

function agent_report_prepare_input(array $_run, array $dependencies): array
{
    load_library('data');
    $config = agent_report_config($dependencies);
    $source = $config['source'] ?? [];
    $scope_resource = (string)($source['scope_resource'] ?? '');
    $report_resource = (string)($source['report_resource'] ?? '');
    if ($scope_resource === '' || $report_resource === '') {
        throw new RuntimeException('Agent report source configuration is incomplete');
    }
    $scopes = data_read($scope_resource) ?: [];
    $reports = data_read($report_resource) ?: [];
    $prepared = [];
    $source_uuids = [];
    foreach (agent_report_targets($dependencies) as $target) {
        $scope_record = agent_report_find_scope($scopes, $target, $source);
        $report_uuid_field = (string)($source['report_uuid_field'] ?? 'last_report_uuid');
        $report_uuid = (string)($scope_record[$report_uuid_field] ?? '');
        $report = $report_uuid === '' ? null : data_read($report_resource, $report_uuid);
        $late_after_field = (string)($source['late_after_field'] ?? 'late_after');
        $late_after = (int)($scope_record[$late_after_field] ?? ($source['late_after_default'] ?? 93600));
        $received_at_field = (string)($source['received_at_field'] ?? 'received_at');
        $received_at = (int)($report[$received_at_field] ?? 0);
        $review_status = !is_array($report)
            ? 'missing'
            : (time() - $received_at > $late_after ? 'stale' : 'reviewed');
        if (is_array($report)) {
            $source_uuids[] = $report_uuid;
        }
        $current_findings = agent_report_project_findings(
            (array)agent_report_value($report, (string)($source['findings_path'] ?? 'audit.findings'), []),
            (array)($source['finding_fields'] ?? ['id', 'severity', 'scope', 'evidence'])
        );
        $history = agent_report_history(
            $reports,
            (string)$target['identity'],
            $source,
            (int)($source['history_limit'] ?? 7)
        );
        $prepared[] = [
            (string)($config['scope_output_field'] ?? 'scope') => (string)$target['scope'],
            (string)($config['identity_output_field'] ?? 'identity') => (string)$target['identity'],
            'source_report_uuid' => $report_uuid,
            'review_status' => $review_status,
            'generated_at' => (int)($report[$source['generated_at_field'] ?? 'generated_at'] ?? 0),
            'overall' => (string)($report[$source['overall_field'] ?? 'overall'] ?? 'unknown'),
            'findings' => agent_report_classify_findings($current_findings, $history),
            'recent_reports' => $history,
        ];
    }
    $payload = [
        (string)($config['input_key'] ?? 'expected_scopes') => $prepared,
        'authority' => agent_report_authority_map($dependencies),
    ];
    $history = $config['event_history'] ?? [];
    if (is_array($history) && !empty($history['input_key'])) {
        $payload[(string)$history['input_key']] = agent_report_event_history($history);
    }
    return [
        'source_report_uuids' => array_values(array_unique($source_uuids)),
        'input' => [[
            'role' => 'user',
            'content' => [[
                'type' => 'input_text',
                'text' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]],
        ]],
    ];
}

function agent_report_classify_findings(array $current, array $history): array
{
    $prior = [];
    foreach (array_slice($history, 1) as $report) {
        foreach ((array)($report['findings'] ?? []) as $finding) {
            if (!empty($finding['id'])) {
                $prior[(string)$finding['id']] = $finding;
            }
        }
    }
    $current_ids = [];
    foreach ($current as &$finding) {
        $id = (string)($finding['id'] ?? '');
        $current_ids[$id] = true;
        $finding['classification'] = isset($prior[$id]) ? 'recurring' : 'new';
        if (isset($prior[$id]) && agent_report_severity_rank((string)($finding['severity'] ?? ''))
            > agent_report_severity_rank((string)($prior[$id]['severity'] ?? ''))) {
            $finding['classification'] = 'worsening';
        }
    }
    unset($finding);
    foreach ($prior as $id => $finding) {
        if (!isset($current_ids[$id])) {
            $finding['classification'] = 'recovered';
            $current[] = $finding;
        }
    }
    return $current;
}

function agent_report_severity_rank(string $severity): int
{
    return ['notice' => 0, 'warning' => 1, 'critical' => 2][$severity] ?? 0;
}

function agent_report_find_scope(array $scopes, array $target, array $source): array
{
    $scope_field = (string)($source['scope_field'] ?? 'environment');
    $identity_field = (string)($source['identity_field'] ?? 'server');
    foreach ($scopes as $candidate) {
        if (is_array($candidate)
            && ($candidate[$scope_field] ?? '') === ($target['scope'] ?? '')
            && ($candidate[$identity_field] ?? '') === ($target['identity'] ?? '')) {
            return $candidate;
        }
    }
    return [];
}

function agent_report_history(array $reports, string $identity, array $source, int $limit): array
{
    $identity_field = (string)($source['identity_field'] ?? 'server');
    $history = [];
    foreach ($reports as $report) {
        if (!is_array($report) || ($report[$identity_field] ?? '') !== $identity) {
            continue;
        }
        $history[] = [
            'uuid' => (string)($report['uuid'] ?? ''),
            'generated_at' => (int)($report[$source['generated_at_field'] ?? 'generated_at'] ?? 0),
            'overall' => (string)($report[$source['overall_field'] ?? 'overall'] ?? 'unknown'),
            'findings' => agent_report_project_findings(
                array_slice((array)agent_report_value($report, (string)($source['findings_path'] ?? 'audit.findings'), []), 0, 50),
                (array)($source['history_finding_fields'] ?? ['id', 'severity', 'evidence'])
            ),
        ];
    }
    usort($history, fn($a, $b) => $b['generated_at'] <=> $a['generated_at']);
    return array_slice($history, 0, $limit);
}

function agent_report_project_findings(array $findings, array $fields): array
{
    $result = [];
    foreach ($findings as $finding) {
        if (!is_array($finding)) {
            continue;
        }
        $item = [];
        foreach ($fields as $field) {
            $value = $finding[$field] ?? '';
            $max_length = $field === 'evidence' ? 500 : 160;
            if (is_array($value)) {
                $encoded = json_encode($value, JSON_UNESCAPED_SLASHES);
                $item[(string)$field] = is_string($encoded) && strlen($encoded) <= $max_length
                    ? $value
                    : ['truncated' => true];
                continue;
            }
            $item[(string)$field] = substr((string)$value, 0, $max_length);
        }
        $result[] = $item;
    }
    return $result;
}

function agent_report_authority_map(array $dependencies): array
{
    $result = [];
    foreach (agent_report_targets($dependencies) as $target) {
        $result[(string)($target['authority'] ?? 'read_only')][] = (string)$target['identity'];
    }
    return $result;
}

function agent_report_event_history(array $config): array
{
    $history = [];
    foreach (data_read((string)($config['resource'] ?? '.agent_events')) ?: [] as $event) {
        $payload = (array)($event['payload'] ?? []);
        $type = (string)($event['type'] ?? '');
        if (!in_array($type, (array)($config['types'] ?? []), true)) {
            continue;
        }
        if ($type === 'tool_completed'
            && !in_array(($payload['tool'] ?? ''), (array)($config['tools'] ?? []), true)) {
            continue;
        }
        $history[] = [
            'occurred_at' => (int)($event['occurred_at'] ?? 0),
            'type' => $type,
            'tool' => (string)($payload['tool'] ?? ''),
            'payload' => $payload,
        ];
    }
    usort($history, fn($a, $b) => $b['occurred_at'] <=> $a['occurred_at']);
    return array_slice($history, 0, (int)($config['limit'] ?? 30));
}

function agent_report_value($value, string $path, $default = null)
{
    foreach (array_filter(explode('.', $path)) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function agent_report_tool_results(
    string $run_uuid,
    string $tool,
    array $allowed_identities,
    array $match = [],
    bool $latest = false
): array {
    $result = [];
    foreach (data_read('.agent_events') ?: [] as $event) {
        $payload = (array)($event['payload'] ?? []);
        $evidence = (array)($payload['result'] ?? []);
        $identity = (string)($evidence['server'] ?? $evidence['identity'] ?? '');
        if (($event['run_uuid'] ?? '') !== $run_uuid
            || ($event['type'] ?? '') !== 'tool_completed'
            || ($payload['tool'] ?? '') !== $tool
            || !in_array($identity, $allowed_identities, true)) {
            continue;
        }
        $matches = true;
        foreach ($match as $key => $value) {
            if (($evidence[$key] ?? null) !== $value) {
                $matches = false;
                break;
            }
        }
        if (!$matches) {
            continue;
        }
        if ($latest) {
            $result[$identity] = $evidence;
        } else {
            $result[$identity][] = $evidence;
        }
    }
    return $result;
}
