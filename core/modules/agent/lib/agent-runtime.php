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
        $prepared = ($definition['prepare_input'])($run, $agent_dependencies);
        if (!is_array($prepared) || !isset($prepared['input'])) {
            throw new RuntimeException('Agent input preparation failed');
        }
        if (!empty($run['event_context'])) {
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
        }
        agent_update_run($run_uuid, ['source_report_uuids' => $prepared['source_report_uuids'] ?? []]);
        $result = agent_reason($run_uuid, $definition, $prepared['input'], $agent_dependencies);
        $validated = ($definition['validate_result'])($result, $run_uuid, $agent_dependencies);
        if (!is_array($validated)) {
            throw new RuntimeException('Agent result validation failed');
        }
        $delivery = ($definition['deliver'])($validated, $run_uuid, $agent_dependencies);
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
    $agent_id = (string)$failed['agent_id'];
    $definition = agent_definition($agent_id);
    $retry_number = 1;
    foreach (data_read('.agent_runs') ?: [] as $run) {
        if (($run['retry_of'] ?? '') === $failed_run_uuid) {
            $retry_number = max($retry_number, (int)($run['retry_number'] ?? 0) + 1);
        }
    }
    $retry_uuid = substr(hash('sha256', $failed_run_uuid . ':retry:' . $retry_number), 0, 16);
    if (!data_exists('.agent_runs', $retry_uuid)) {
        $instructions = agent_instructions($definition);
        $run = [
            'agent_id' => $agent_id,
            'agent_version' => (string)$definition['version'],
            'instructions_sha256' => hash('sha256', $instructions),
            'trigger' => 'scheduled_retry',
            'scheduled_at' => time(),
            'scheduled_occurrence' => (string)$failed['scheduled_occurrence'],
            'timezone' => (string)$failed['timezone'],
            'status' => 'scheduled',
            'idempotency_key' => (string)$failed['idempotency_key'] . ':retry:' . $retry_number,
            'retry_of' => $failed_run_uuid,
            'retry_number' => $retry_number,
            'source_report_uuids' => [],
            'usage' => agent_empty_usage(),
            'estimated_cost_usd' => 0.0,
            'email_delivery' => [],
            'failure_reason' => '',
            'lease_expires_at' => 0,
            'target' => (string)($failed['target'] ?? ''),
            'read_only' => !empty($failed['read_only']),
            'event_context' => agent_event_context($failed['event_context'] ?? []),
        ];
        if (!data_create('.agent_runs', $retry_uuid, $run)) {
            throw new RuntimeException('Could not create agent retry');
        }
        agent_append_event($retry_uuid, 'run_scheduled', ['occurrence' => $run['scheduled_occurrence'], 'retry_of' => $failed_run_uuid]);
    }
    load_library('job');
    job_enqueue('agent-run', ['run_uuid' => $retry_uuid], [
        'uuid' => substr(hash('sha256', 'agent-job:' . $retry_uuid), 0, 16),
        'max_attempts' => 3,
    ]);
    return $retry_uuid;
}

function agent_reason(string $run_uuid, array $definition, array $initial_input, array $dependencies): array
{
    $instructions = agent_instructions($definition);
    $input = $initial_input;
    $usage = agent_empty_usage();
    $max_turns = (int)($definition['max_turns'] ?? 8);
    $max_tools = (int)($definition['max_tool_calls'] ?? 12);
    $tool_count = 0;
    $started = microtime(true);

    for ($turn = 1; $turn <= $max_turns; $turn++) {
        if ((microtime(true) - $started) > (int)($definition['max_wall_seconds'] ?? 900)) {
            throw new RuntimeException('Agent wall-clock limit exceeded');
        }
        $request = [
            'model' => $definition['model'] ?? 'gpt-5.6-terra',
            'reasoning' => ['effort' => $definition['reasoning_effort'] ?? 'medium'],
            'instructions' => $instructions,
            'input' => $input,
            'tools' => agent_openai_tools($definition['tools']),
            'store' => false,
            'max_output_tokens' => (int)($definition['max_output_tokens'] ?? 6000),
        ];
        $response = agent_openai_request($request, $dependencies);
        agent_append_event($run_uuid, 'model_response', [
            'turn' => $turn,
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
            $result = agent_execute_tool($run_uuid, $definition['tools'], $call, $dependencies);
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
