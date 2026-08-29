<?php

const AGENT_TERMINAL_STATUSES = ['completed', 'failed'];

require_once __DIR__ . '/agent-definition.php';

function agent_enqueue(string $agent_id, ?int $now = null, array $dependencies = []): string
{
    $definition = agent_definition($agent_id);
    agent_ensure_resources();
    $now = $now ?? time();
    $timezone = new DateTimeZone($definition['timezone'] ?? 'UTC');
    $scheduled = (new DateTimeImmutable('@' . $now))->setTimezone($timezone);
    $occurrence = $scheduled->format('Y-m-d');
    $idempotency_suffix = trim((string)($dependencies['idempotency_suffix'] ?? ''));
    if ($idempotency_suffix !== '' && preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $idempotency_suffix) !== 1) {
        throw new InvalidArgumentException('Invalid agent idempotency suffix');
    }
    $idempotency_key = $agent_id . ':' . $occurrence
        . ($idempotency_suffix === '' ? '' : ':' . $idempotency_suffix);
    $run_uuid = substr(hash('sha256', $idempotency_key), 0, 16);
    $event_context = agent_event_context($dependencies['event_context'] ?? []);

    $lock = agent_lock('enqueue-' . $run_uuid);
    try {
        $existing = data_read('.agent_runs', $run_uuid);
        if (is_array($existing) && in_array(($existing['status'] ?? ''), ['completed', 'failed'], true)) {
            return $run_uuid;
        }
        if (!is_array($existing)) {
            $instructions = agent_instructions($definition);
            $run = [
                'agent_id' => $agent_id,
                'agent_version' => (string)$definition['version'],
                'instructions_sha256' => hash('sha256', $instructions),
                'trigger' => $dependencies['trigger'] ?? 'scheduled',
                'scheduled_at' => $now,
                'scheduled_occurrence' => $occurrence,
                'timezone' => $timezone->getName(),
                'status' => 'scheduled',
                'idempotency_key' => $idempotency_key,
                'source_report_uuids' => [],
                'usage' => agent_empty_usage(),
                'estimated_cost_usd' => 0.0,
                'email_delivery' => [],
                'failure_reason' => '',
                'lease_expires_at' => 0,
                'target' => (string)($dependencies['target'] ?? ''),
                'read_only' => !empty($dependencies['read_only']),
                'event_context' => $event_context,
            ];
            if (!data_create('.agent_runs', $run_uuid, $run)) {
                throw new RuntimeException('Could not create agent run');
            }
            agent_append_event($run_uuid, 'run_scheduled', ['occurrence' => $occurrence]);
        }
        load_library('job');
        job_enqueue('agent-run', ['run_uuid' => $run_uuid], [
            'uuid' => substr(hash('sha256', 'agent-job:' . $run_uuid), 0, 16),
            'max_attempts' => 3,
        ]);
    } finally {
        agent_unlock($lock);
    }
    return $run_uuid;
}

function agent_run(string $run_uuid, array $dependencies = []): array
{
    agent_ensure_resources();
    $lock = agent_lock('run-' . $run_uuid);
    try {
        $run = data_read('.agent_runs', $run_uuid);
        if (!is_array($run)) {
            throw new RuntimeException('Agent run not found');
        }
        if (in_array($run['status'] ?? '', AGENT_TERMINAL_STATUSES, true)) {
            return $run;
        }
        $definition = agent_scope_definition(agent_definition((string)$run['agent_id']), $run);
        agent_update_run($run_uuid, [
            'status' => 'running',
            'started_at' => (int)($run['started_at'] ?? 0) ?: time(),
            'lease_expires_at' => time() + (int)($definition['lease_seconds'] ?? 1200),
        ]);
        agent_append_event($run_uuid, 'run_started', []);

        $agent_dependencies = array_merge($dependencies, [
            'agent_definition' => $definition,
            'run_uuid' => $run_uuid,
            'run' => $run,
        ]);
        if (empty($definition['pipeline'])) {
            $prepared = ($definition['prepare_input'])($run, $agent_dependencies);
            $prepared = agent_append_event_context_input($prepared, $run);
            agent_update_run($run_uuid, ['source_report_uuids' => $prepared['source_report_uuids'] ?? []]);
            $validated = ($definition['validate_result'])(
                agent_reason($run_uuid, $definition, $prepared['input'], $agent_dependencies)
            );
            $delivery = ($definition['deliver'])($validated, $run_uuid, $agent_dependencies);
        } else {
            $execution = agent_execute_pipeline($run_uuid, $definition, $run, $agent_dependencies);
            $validated = $execution['result'];
            $delivery = $execution['delivery'];
        }
        if (!is_array($delivery) || empty($delivery['success'])) {
            throw new RuntimeException($delivery['error'] ?? 'Agent report delivery failed');
        }
        agent_append_event($run_uuid, 'reports_accepted', agent_redact($delivery));
        agent_update_run($run_uuid, [
            'status' => 'completed',
            'completed_at' => time(),
            'lease_expires_at' => 0,
            'structured_result' => $validated,
            'email_delivery' => $delivery['environments'] ?? [],
            'failure_reason' => '',
        ]);
    } catch (Throwable $error) {
        $current = data_read('.agent_runs', $run_uuid);
        if (is_array($current) && !in_array($current['status'] ?? '', AGENT_TERMINAL_STATUSES, true)) {
            $terminal = $dependencies['terminal_on_failure'] ?? true;
            agent_append_event($run_uuid, $terminal ? 'run_failed' : 'run_retry_scheduled', ['error' => agent_safe_error($error->getMessage())]);
            agent_update_run($run_uuid, [
                'status' => $terminal ? 'failed' : 'scheduled',
                'completed_at' => $terminal ? time() : 0,
                'lease_expires_at' => 0,
                'failure_reason' => agent_safe_error($error->getMessage()),
            ]);
        }
        if (($dependencies['terminal_on_failure'] ?? true) === false) {
            throw $error;
        }
    } finally {
        agent_unlock($lock);
    }
    return data_read('.agent_runs', $run_uuid) ?: [];
}

function agent_append_event_context_input(array $prepared, array $run): array
{
    if (empty($run['event_context'])) {
        return $prepared;
    }
    $prepared['input'][] = [
        'role' => 'user',
        'content' => [[
            'type' => 'input_text',
            'text' => json_encode(
                ['event_context' => $run['event_context']],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ),
        ]],
    ];
    return $prepared;
}

function agent_execute_pipeline(
    string $run_uuid,
    array $definition,
    array $run,
    array $dependencies
): array {
    $pipeline = (array)$definition['pipeline'];
    $artifacts = [];
    $previous = $run;
    foreach (array_merge($pipeline['input'], $pipeline['agent'], $pipeline['output']) as $phase) {
        $source = agent_pipeline_source($phase, $artifacts, $previous);
        $artifact = agent_execute_durable_step($run_uuid, $definition, $run, $phase, $source, $artifacts, $dependencies);
        $artifacts[(string)$phase['id']] = $artifact;
        $previous = $artifact;
    }
    return [
        'result' => $artifacts[$pipeline['result_from']],
        'delivery' => $artifacts[$pipeline['delivery_from']],
        'artifacts' => $artifacts,
    ];
}

function agent_pipeline_source(array $phase, array $artifacts, array $previous): array
{
    $input_from = (string)($phase['from'] ?? $phase['input_from'] ?? '');
    if ($input_from === '') {
        return $previous;
    }
    if (!isset($artifacts[$input_from]) || !is_array($artifacts[$input_from])) {
        throw new RuntimeException('Agent pipeline artifact is unavailable: ' . $input_from);
    }
    return $artifacts[$input_from];
}

function agent_execute_durable_step(
    string $run_uuid,
    array $definition,
    array $run,
    array $phase,
    array $source,
    array $artifacts,
    array $dependencies
): array {
    $step_id = (string)$phase['id'];
    $uuid = substr(hash('sha256', $run_uuid . ':' . $step_id), 0, 16);
    $stored = data_read('.agent_steps', $uuid);
    if (is_array($stored) && ($stored['status'] ?? '') === 'completed' && is_array($stored['artifact'] ?? null)) {
        return $stored['artifact'];
    }
    $attempts = (int)($stored['attempts'] ?? 0) + 1;
    $record = [
        'run_uuid' => $run_uuid,
        'step_id' => $step_id,
        'step_type' => (string)$phase['type'],
        'status' => 'running',
        'attempts' => $attempts,
        'input_digest' => hash('sha256', agent_canonical_json($source)),
        'output_digest' => '',
        'artifact' => [],
        'started_at' => time(),
        'completed_at' => 0,
        'retryable' => false,
        'error' => [],
    ];
    if (is_array($stored)) {
        data_update('.agent_steps', $uuid, $record);
    } else {
        data_create('.agent_steps', $uuid, $record);
    }
    $step_dependencies = array_merge($dependencies, [
        'pipeline_artifacts' => $artifacts,
        'pipeline_phase' => $phase,
    ]);
    try {
        $artifact = agent_execute_step_type($run_uuid, $definition, $run, $phase, $source, $step_dependencies);
        if (!is_array($artifact)) {
            throw new RuntimeException('Agent step returned an invalid artifact');
        }
        $bounded = agent_bounded_artifact($artifact);
        data_update('.agent_steps', $uuid, [
            'status' => 'completed',
            'artifact' => $bounded,
            'output_digest' => hash('sha256', agent_canonical_json($bounded)),
            'completed_at' => time(),
            'retryable' => false,
            'error' => [],
        ]);
        agent_append_event($run_uuid, 'step_completed', ['step' => $step_id, 'attempt' => $attempts]);
        return $bounded;
    } catch (Throwable $error) {
        $retryable = agent_error_retryable($error);
        data_update('.agent_steps', $uuid, [
            'status' => 'failed',
            'retryable' => $retryable,
            'error' => ['code' => get_class($error), 'message' => agent_safe_error($error->getMessage())],
            'completed_at' => time(),
        ]);
        agent_append_event($run_uuid, 'step_failed', [
            'step' => $step_id,
            'phase' => (string)$phase['type'],
            'retryable' => $retryable,
            'cause' => agent_safe_error($error->getMessage()),
        ]);
        throw $error;
    }
}

function agent_execute_step_type(
    string $run_uuid,
    array $definition,
    array $run,
    array $phase,
    array $source,
    array $dependencies
): array {
    $type = (string)$phase['type'];
    if ($type === 'callback') {
        $handler = $phase['handler'] ?? null;
        if (!is_callable($handler)) {
            throw new RuntimeException('Agent callback step is unavailable');
        }
        return in_array($phase['id'], ['result', 'delivery'], true)
            ? $handler($source, $run_uuid, $dependencies)
            : $handler($source, $dependencies);
    }
    if ($type === 'resource_snapshot') {
        $artifact = agent_report_prepare_input($run, $dependencies);
        $artifact = agent_append_event_context_input($artifact, $run);
        agent_update_run($run_uuid, ['source_report_uuids' => $artifact['source_report_uuids'] ?? []]);
        return $artifact;
    }
    if ($type === 'connector_collect') {
        $tool_name = (string)($phase['config']['tool'] ?? '');
        if ($tool_name === '' || !isset($definition['tools'][$tool_name])) {
            throw new RuntimeException('Agent collection connector is not configured');
        }
        $observations = [];
        foreach ((array)($definition['targets'] ?? []) as $target) {
            $identity = (string)($target['identity'] ?? '');
            try {
                $observations[] = agent_execute_tool($run_uuid, $definition['tools'], [
                    'call_id' => 'collect-' . (string)$phase['id'] . '-' . $identity,
                    'name' => $tool_name,
                    'arguments' => json_encode(['server' => $identity], JSON_THROW_ON_ERROR),
                ], $dependencies);
            } catch (Throwable $error) {
                $observations[] = [
                    'server' => $identity,
                    'status' => 'unreachable',
                    'complete' => false,
                    'reason' => agent_safe_error($error->getMessage()),
                    'observed_at' => time(),
                ];
            }
        }
        $payload = ['canonical' => $source, 'current_observations' => $observations];
        return [
            'source_report_uuids' => $source['source_report_uuids'] ?? [],
            'input' => [[
                'role' => 'user',
                'content' => [[
                    'type' => 'input_text',
                    'text' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ]],
            ]],
        ];
    }
    if ($type === 'model') {
        if (!empty($phase['projector'])) {
            $source = ($phase['projector'])($source, $dependencies);
        }
        $input = isset($source['input']) ? $source['input'] : [[
            'role' => 'user',
            'content' => [['type' => 'input_text', 'text' => json_encode($source, JSON_THROW_ON_ERROR)]],
        ]];
        $artifact = agent_reason($run_uuid, agent_pipeline_model_definition($definition, $phase), $input, $dependencies);
        if (!empty($phase['validator'])) {
            try {
                $artifact = ($phase['validator'])($artifact, $dependencies);
            } catch (Throwable $error) {
                agent_append_event($run_uuid, 'model_validation_failed', [
                    'phase' => (string)$phase['id'],
                    'error' => agent_safe_error($error->getMessage()),
                    'structured_output' => agent_redacted_json($artifact),
                ]);
                throw $error;
            }
        }
        return $artifact;
    }
    if ($type === 'evidence_guard') {
        return agent_evidence_guard($source, $dependencies);
    }
    if ($type === 'render_report') {
        return agent_render_single_report($source, $definition);
    }
    if ($type === 'deliver') {
        return agent_connector_deliver_email($source, $run_uuid, $dependencies);
    }
    throw new RuntimeException('Unsupported agent step type: ' . $type);
}

function agent_bounded_artifact(array $artifact): array
{
    $json = json_encode(agent_redact($artifact), JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    if (!is_string($json) || strlen($json) > 262144) {
        throw new RuntimeException('Agent step artifact exceeds the 256 KiB limit');
    }
    return $artifact;
}

function agent_error_retryable(Throwable $error): bool
{
    return $error instanceof AgentTransientException;
}

class AgentTransientException extends RuntimeException
{
}

function agent_evidence_guard(array $result, array $dependencies): array
{
    $targets = array_column((array)agent_config($dependencies, 'targets', []), 'identity');
    foreach ((array)($result['targets'] ?? []) as $target) {
        if (!is_array($target) || !in_array((string)($target['identity'] ?? ''), $targets, true)) {
            throw new RuntimeException('Agent result references an unconfigured target');
        }
        foreach ((array)($target['actions_completed'] ?? []) as $action) {
            if (empty($action['evidence_refs'])) {
                throw new RuntimeException('Completed action claim has no evidence reference');
            }
        }
    }
    return $result;
}

function agent_render_single_report(array $result, array $definition): array
{
    $subject = substr((string)($result['subject'] ?? ($definition['name'] . ' infrastructure briefing')), 0, 160);
    $sections = [];
    foreach (['overall_state', 'completed_work', 'blocked_work', 'production_recommendations', 'colleague_requests'] as $key) {
        $value = $result[$key] ?? [];
        $text = is_array($value) ? implode("\n", array_map('strval', $value)) : (string)$value;
        $sections[] = '<h2>' . htmlspecialchars(ucwords(str_replace('_', ' ', $key))) . '</h2><p>'
            . nl2br(htmlspecialchars($text)) . '</p>';
    }
    return ['briefings' => [[
        'id' => 'employee-briefing',
        'subject' => $subject,
        'html' => implode('', $sections),
        'result' => $result,
    ]]];
}

function agent_pipeline_model_definition(array $definition, array $phase): array
{
    $definition['instruction_files'] = [(string)$phase['instructions']];
    $definition['instructions'] = (string)$phase['instructions'];
    $definition['_phase_id'] = (string)$phase['id'];
    $configured_tools = $phase['tools'] ?? false;
    if (is_array($configured_tools)) {
        $definition['tools'] = array_intersect_key($definition['tools'], array_flip($configured_tools));
    } elseif ($configured_tools !== true) {
        $definition['tools'] = [];
    }
    if (isset($phase['model'])) {
        $definition['model'] = (string)$phase['model'];
    }
    if (isset($phase['reasoning_effort'])) {
        $definition['reasoning_effort'] = (string)$phase['reasoning_effort'];
    }
    if (isset($phase['max_output_tokens'])) {
        $definition['max_output_tokens'] = (int)$phase['max_output_tokens'];
    }
    if (isset($phase['output_schema'])) {
        $definition['output_schema'] = $phase['output_schema'];
    } else {
        unset($definition['output_schema']);
    }
    return $definition;
}

function agent_event_context($context): array
{
    if ($context === null || $context === []) {
        return [];
    }
    if (!is_array($context)) {
        throw new InvalidArgumentException('Agent event context must be an object');
    }
    $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (strlen($encoded) > 32768) {
        throw new InvalidArgumentException('Agent event context is too large');
    }
    return $context;
}

function agent_run_triggers(string $run_uuid): array
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

function agent_retry(string $failed_run_uuid): string
{
    agent_ensure_resources();
    $failed = data_read('.agent_runs', $failed_run_uuid);
    if (!is_array($failed) || ($failed['status'] ?? '') !== 'failed') {
        throw new RuntimeException('Only a failed agent run can be retried');
    }
    $retry_number = (int)($failed['retry_number'] ?? 0) + 1;
    data_update('.agent_runs', $failed_run_uuid, [
        'status' => 'scheduled',
        'retry_number' => $retry_number,
        'completed_at' => 0,
        'lease_expires_at' => 0,
        'failure_reason' => '',
    ]);
    agent_append_event($failed_run_uuid, 'run_retry_scheduled', ['retry_number' => $retry_number]);
    load_library('job');
    job_enqueue('agent-run', ['run_uuid' => $failed_run_uuid], [
        'uuid' => substr(hash('sha256', 'agent-job:' . $failed_run_uuid . ':retry:' . $retry_number), 0, 16),
        'max_attempts' => 3,
    ]);
    return $failed_run_uuid;
}

function agent_reason(string $run_uuid, array $definition, array $initial_input, array $dependencies): array
{
    $instructions = agent_instructions($definition);
    $input = $initial_input;
    $current_run = data_read('.agent_runs', $run_uuid);
    $available_tools = agent_tools_for_run(
        $definition['tools'],
        $definition,
        is_array($current_run) ? $current_run : []
    );
    $usage = is_array($current_run) && is_array($current_run['usage'] ?? null)
        ? $current_run['usage']
        : agent_empty_usage();
    $max_turns = (int)($definition['max_turns'] ?? 8);
    $max_tools = (int)($definition['max_tool_calls'] ?? 12);
    $tool_count = 0;
    $started = microtime(true);

    for ($turn = 1; $turn <= $max_turns; $turn++) {
        if ((microtime(true) - $started) > (int)($definition['max_wall_seconds'] ?? 900)) {
            throw new RuntimeException('Agent wall-clock limit exceeded');
        }
        $request_input = $input;
        if (isset($request_input[0]['content']) && is_array($request_input[0]['content'])) {
            $request_input[0]['content'][] = [
                'type' => 'input_text',
                'text' => 'Return the final response as a JSON object matching the supplied output contract.',
            ];
        } else {
            array_unshift($request_input, [
                'role' => 'user',
                'content' => [[
                    'type' => 'input_text',
                    'text' => 'Return the final response as a JSON object matching the supplied output contract.',
                ]],
            ]);
        }
        $output_schema = $definition['output_schema'] ?? null;
        $text_format = is_array($output_schema)
            ? [
                'type' => 'json_schema',
                'name' => (string)($definition['_phase_id'] ?? $definition['id'] ?? 'agent_response'),
                'strict' => true,
                'schema' => $output_schema,
            ]
            : ['type' => 'json_object'];
        $request = [
            'model' => $definition['model'] ?? 'gpt-5.6-terra',
            'reasoning' => ['effort' => $definition['reasoning_effort'] ?? 'medium'],
            'instructions' => $instructions,
            'input' => $request_input,
            'tools' => agent_openai_tools($available_tools),
            'text' => ['format' => $text_format],
            'store' => false,
            'max_output_tokens' => (int)($definition['max_output_tokens'] ?? 6000),
        ];
        $response = agent_openai_request($request, $dependencies);
        agent_append_event($run_uuid, 'model_response', [
            'turn' => $turn,
            'phase' => (string)($definition['_phase_id'] ?? 'agent'),
            'response_id' => $response['id'] ?? '',
            'request_id' => $response['_request_id'] ?? '',
            'status' => $response['status'] ?? '',
        ]);
        $usage = agent_add_usage($usage, $response['usage'] ?? []);
        agent_store_usage($run_uuid, $usage, $definition);
        $output = is_array($response['output'] ?? null) ? $response['output'] : [];
        $calls = array_values(array_filter($output, fn($item) => ($item['type'] ?? '') === 'function_call'));
        if (empty($calls)) {
            $text = agent_response_text($response);
            $decoded = json_decode($text, true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Agent returned invalid structured output');
            }
            return $decoded;
        }
        $input = array_merge($input, $output);
        foreach ($calls as $call) {
            $tool_count++;
            if ($tool_count > $max_tools) {
                throw new RuntimeException('Agent tool-call limit exceeded');
            }
            $result = agent_execute_tool($run_uuid, $available_tools, $call, $dependencies);
            $input[] = [
                'type' => 'function_call_output',
                'call_id' => $call['call_id'] ?? '',
                'output' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ];
        }
    }
    throw new RuntimeException('Agent turn limit exceeded');
}

require_once __DIR__ . '/agent-tool.php';

require_once __DIR__ . '/agent-state.php';
require_once __DIR__ . '/agent-support.php';
