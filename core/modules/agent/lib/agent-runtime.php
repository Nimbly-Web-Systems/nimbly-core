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
        ]);
        $prepared = ($definition['prepare_input'])($run, $agent_dependencies);
        if (!is_array($prepared) || !isset($prepared['input'])) {
            throw new RuntimeException('Agent input preparation failed');
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

function agent_execute_tool(string $run_uuid, array $tools, array $call, array $dependencies): array
{
    $name = (string)($call['name'] ?? '');
    if (!isset($tools[$name]) || !is_array($tools[$name])) {
        throw new RuntimeException('Unknown agent tool requested');
    }
    $arguments = json_decode((string)($call['arguments'] ?? ''), true);
    if (!is_array($arguments)) {
        throw new RuntimeException('Agent tool arguments are invalid');
    }
    $tool = $tools[$name];
    $risk = (string)($tool['risk'] ?? '');
    if (!in_array($risk, ['read_only', 'governed'], true)) {
        throw new RuntimeException('Agent tool risk class is invalid');
    }
    agent_validate_arguments($arguments, $tool['parameters'] ?? []);
    $tool_key = hash('sha256', $run_uuid . "\n" . $name . "\n" . agent_canonical_json($arguments));
    $stored = agent_tool_result($run_uuid, $tool_key);
    if ($stored !== null) {
        return $stored;
    }
    agent_append_event($run_uuid, 'tool_requested', [
        'tool_key' => $tool_key,
        'call_id' => $call['call_id'] ?? '',
        'tool' => $name,
        'risk' => $tool['risk'],
        'arguments' => agent_redact($arguments),
    ]);
    $authorization = null;
    if ($risk === 'governed') {
        if (empty($tool['authorize']) || !is_callable($tool['authorize'])) {
            throw new RuntimeException('Governed agent tool has no authorizer');
        }
        $authorization = ($tool['authorize'])($arguments, $run_uuid, $dependencies);
        $authorization = agent_validate_tool_authorization($run_uuid, $name, $arguments, $authorization);
        agent_append_event($run_uuid, 'risk_decision', agent_redact($authorization));
        if (($authorization['status'] ?? '') !== 'authorized') {
            $result = [
                'status' => (string)$authorization['status'],
                'reason' => (string)($authorization['reason'] ?? ''),
                'action_digest' => (string)$authorization['action_digest'],
            ];
            agent_append_event($run_uuid, 'tool_completed', [
                'tool_key' => $tool_key,
                'tool' => $name,
                'duration_ms' => 0,
                'result' => $result,
            ]);
            return $result;
        }
        agent_consume_tool_authorization($run_uuid, $authorization);
    }
    $started = microtime(true);
    $dependencies['tool_name'] = $name;
    $dependencies['tool_definition'] = $tool;
    if ($authorization !== null) {
        $dependencies['authorization'] = $authorization;
    }
    $result = ($tool['execute'])($arguments, $dependencies);
    if (!is_array($result)) {
        throw new RuntimeException('Agent tool returned an invalid result');
    }
    $result = agent_redact($result);
    agent_append_event($run_uuid, 'tool_completed', [
        'tool_key' => $tool_key,
        'tool' => $name,
        'duration_ms' => (int)round((microtime(true) - $started) * 1000),
        'result' => $result,
    ]);
    return $result;
}

function agent_tool_action_digest(string $run_uuid, string $tool_name, array $arguments): string
{
    return hash('sha256', $run_uuid . "\n" . $tool_name . "\n" . agent_canonical_json($arguments));
}

function agent_validate_tool_authorization(string $run_uuid, string $tool_name, array $arguments, $authorization): array
{
    if (!is_array($authorization)) {
        throw new RuntimeException('Governed tool authorization is invalid');
    }
    $status = (string)($authorization['status'] ?? '');
    if (!in_array($status, ['authorized', 'denied', 'human_approval_required'], true)) {
        throw new RuntimeException('Governed tool authorization status is invalid');
    }
    $expected_digest = agent_tool_action_digest($run_uuid, $tool_name, $arguments);
    if (!hash_equals($expected_digest, (string)($authorization['action_digest'] ?? ''))) {
        throw new RuntimeException('Governed tool authorization is not bound to the exact action');
    }
    $authorization['run_uuid'] = $run_uuid;
    $authorization['tool'] = $tool_name;
    $authorization['status'] = $status;
    $authorization['expires_at'] = (int)($authorization['expires_at'] ?? 0);
    if ($status === 'authorized' && $authorization['expires_at'] <= time()) {
        throw new RuntimeException('Governed tool authorization has expired');
    }
    return $authorization;
}

function agent_consume_tool_authorization(string $run_uuid, array $authorization): void
{
    load_library('data');
    $digest = (string)$authorization['action_digest'];
    $uuid = substr(hash('sha256', 'agent-authorization:' . $digest), 0, 16);
    if (data_exists('.agent_approvals', $uuid)) {
        throw new RuntimeException('Governed tool authorization has already been used');
    }
    $record = [
        'run_uuid' => $run_uuid,
        'status' => 'consumed',
        'action_digest' => $digest,
        'target' => (string)($authorization['target'] ?? ''),
        'tool' => (string)($authorization['tool'] ?? ''),
        'authorized_at' => (int)($authorization['authorized_at'] ?? time()),
        'expires_at' => (int)$authorization['expires_at'],
        'consumed_at' => time(),
        'decision' => $authorization,
    ];
    if (!data_create('.agent_approvals', $uuid, $record)) {
        throw new RuntimeException('Could not consume governed tool authorization');
    }
}

function agent_openai_tools(array $tools): array
{
    $result = [];
    foreach ($tools as $name => $tool) {
        $result[] = [
            'type' => 'function',
            'name' => $name,
            'description' => (string)($tool['description'] ?? ''),
            'parameters' => $tool['parameters'] ?? ['type' => 'object', 'properties' => []],
            'strict' => true,
        ];
    }
    return $result;
}

function agent_openai_request(array $request, array $dependencies): array
{
    if (!empty($dependencies['openai_request']) && is_callable($dependencies['openai_request'])) {
        return ($dependencies['openai_request'])($request);
    }
    load_library('env');
    $api_key = env('OPENAI_API_KEY');
    if ($api_key === '') {
        throw new RuntimeException('OpenAI is not configured');
    }
    $attempt = 0;
    do {
        $attempt++;
        $headers = [];
        $handle = curl_init('https://api.openai.com/v1/responses');
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($request, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $api_key, 'Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HEADERFUNCTION => function ($_handle, $line) use (&$headers) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);
        $body = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if ($error === '' && $status >= 200 && $status < 300) {
            $decoded = json_decode((string)$body, true);
            if (is_array($decoded)) {
                $decoded['_request_id'] = $headers['x-request-id'] ?? '';
                return $decoded;
            }
        }
        if (!in_array($status, [429, 500, 502, 503, 504], true) || $attempt >= 3) {
            throw new RuntimeException('OpenAI request failed');
        }
        usleep(250000 * $attempt);
    } while ($attempt < 3);
    throw new RuntimeException('OpenAI request failed');
}

function agent_response_text(array $response): string
{
    if (isset($response['output_text']) && is_string($response['output_text'])) {
        return $response['output_text'];
    }
    foreach ($response['output'] ?? [] as $item) {
        if (($item['type'] ?? '') !== 'message') {
            continue;
        }
        foreach ($item['content'] ?? [] as $content) {
            if (($content['type'] ?? '') === 'output_text') {
                return (string)($content['text'] ?? '');
            }
        }
    }
    return '';
}

function agent_validate_arguments(array $arguments, array $schema): void
{
    $properties = $schema['properties'] ?? [];
    foreach ($schema['required'] ?? [] as $required) {
        if (!array_key_exists($required, $arguments)) {
            throw new RuntimeException('Required tool argument is missing');
        }
    }
    foreach ($arguments as $key => $value) {
        if (!isset($properties[$key])) {
            throw new RuntimeException('Unknown tool argument');
        }
        $property = $properties[$key];
        if (($property['type'] ?? '') === 'string' && !is_string($value)) {
            throw new RuntimeException('Tool argument type is invalid');
        }
        if (!empty($property['enum']) && !in_array($value, $property['enum'], true)) {
            throw new RuntimeException('Tool argument value is not permitted');
        }
    }
}

function agent_append_event(string $run_uuid, string $type, array $payload): string
{
    $events = data_read('.agent_events') ?: [];
    $sequence = 1;
    foreach ($events as $event) {
        if (($event['run_uuid'] ?? '') === $run_uuid) {
            $sequence = max($sequence, (int)($event['sequence'] ?? 0) + 1);
        }
    }
    $uuid = substr(hash('sha256', $run_uuid . ':' . $sequence), 0, 16);
    data_create('.agent_events', $uuid, [
        'run_uuid' => $run_uuid,
        'sequence' => $sequence,
        'occurred_at' => time(),
        'type' => $type,
        'payload' => $payload,
    ]);
    return $uuid;
}

function agent_tool_result(string $run_uuid, string $tool_key): ?array
{
    foreach (data_read('.agent_events') ?: [] as $event) {
        if (($event['run_uuid'] ?? '') === $run_uuid
            && ($event['type'] ?? '') === 'tool_completed'
            && ($event['payload']['tool_key'] ?? '') === $tool_key) {
            return is_array($event['payload']['result'] ?? null) ? $event['payload']['result'] : [];
        }
    }
    return null;
}

function agent_update_run(string $run_uuid, array $changes): void
{
    $run = data_read('.agent_runs', $run_uuid);
    if (!is_array($run)) {
        throw new RuntimeException('Agent run not found');
    }
    if (in_array($run['status'] ?? '', AGENT_TERMINAL_STATUSES, true)) {
        throw new RuntimeException('Terminal agent runs are immutable');
    }
    if (!data_update('.agent_runs', $run_uuid, $changes)) {
        throw new RuntimeException('Could not update agent run');
    }
}

function agent_recover_expired_runs(?int $now = null): int
{
    agent_ensure_resources();
    $now = $now ?? time();
    $count = 0;
    load_library('job');
    foreach (data_read('.agent_runs') ?: [] as $uuid => $run) {
        if (($run['status'] ?? '') !== 'running' || (int)($run['lease_expires_at'] ?? 0) >= $now) {
            continue;
        }
        data_update('.agent_runs', $uuid, ['status' => 'scheduled', 'lease_expires_at' => 0]);
        agent_append_event($uuid, 'run_recovered', []);
        job_enqueue('agent-run', ['run_uuid' => $uuid], [
            'uuid' => substr(hash('sha256', 'agent-recovery:' . $uuid . ':' . $now), 0, 16),
            'max_attempts' => 3,
        ]);
        $count++;
    }
    return $count;
}

function agent_watchdog_status(string $agent_id, ?int $now = null): array
{
    $definition = agent_definition($agent_id);
    $now = $now ?? time();
    $timezone = new DateTimeZone($definition['timezone'] ?? 'UTC');
    $local = (new DateTimeImmutable('@' . $now))->setTimezone($timezone);
    $occurrence = $local->format('Y-m-d');
    $run = null;
    foreach (data_read('.agent_runs') ?: [] as $candidate) {
        if (($candidate['agent_id'] ?? '') === $agent_id
            && ($candidate['scheduled_occurrence'] ?? '') === $occurrence
            && in_array($candidate['trigger'] ?? '', ['scheduled', 'scheduled_retry'], true)
            && ($run === null || (int)($candidate['scheduled_at'] ?? 0) > (int)($run['scheduled_at'] ?? 0))) {
            $run = $candidate;
        }
    }
    $deadline = DateTimeImmutable::createFromFormat(
        'Y-m-d H:i',
        $occurrence . ' ' . ($definition['deadline_at'] ?? '23:59'),
        $timezone
    );
    $healthy = is_array($run) && ($run['status'] ?? '') === 'completed';
    if (!$healthy && $deadline instanceof DateTimeImmutable && $now < $deadline->getTimestamp()) {
        return ['healthy' => true, 'state' => 'pending'];
    }
    return ['healthy' => $healthy, 'state' => $healthy ? 'completed' : 'overdue'];
}

function agent_empty_usage(): array
{
    return ['input_tokens' => 0, 'cached_input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0];
}

function agent_add_usage(array $total, array $usage): array
{
    $input_details = $usage['input_tokens_details'] ?? [];
    $total['input_tokens'] += (int)($usage['input_tokens'] ?? 0);
    $total['cached_input_tokens'] += (int)($input_details['cached_tokens'] ?? 0);
    $total['output_tokens'] += (int)($usage['output_tokens'] ?? 0);
    $total['total_tokens'] += (int)($usage['total_tokens'] ?? 0);
    return $total;
}

function agent_store_usage(string $run_uuid, array $usage, array $definition): void
{
    $pricing = $definition['pricing'] ?? [];
    $uncached = max(0, $usage['input_tokens'] - $usage['cached_input_tokens']);
    $cost = ($uncached * (float)($pricing['input_per_million'] ?? 0)
        + $usage['cached_input_tokens'] * (float)($pricing['cached_input_per_million'] ?? 0)
        + $usage['output_tokens'] * (float)($pricing['output_per_million'] ?? 0)) / 1000000;
    agent_update_run($run_uuid, ['usage' => $usage, 'estimated_cost_usd' => round($cost, 6)]);
}

function agent_redact($value)
{
    if (is_array($value)) {
        $result = [];
        foreach ($value as $key => $item) {
            $usage_counter = preg_match('/^(?:input|output|total|cached)_tokens(?:_details)?$/', (string)$key) === 1;
            if (!$usage_counter && preg_match('/(secret|token|password|api[_-]?key|authorization|private[_-]?key)/i', (string)$key)) {
                $result[$key] = '[REDACTED]';
            } else {
                $result[$key] = agent_redact($item);
            }
        }
        return $result;
    }
    if (is_string($value)) {
        $value = preg_replace('/\b(sk|re)_[A-Za-z0-9_-]{12,}\b/', '[REDACTED]', $value);
        return strlen($value) > 12000 ? substr($value, 0, 12000) . '\n[TRUNCATED]' : $value;
    }
    return $value;
}

function agent_safe_error(string $error): string
{
    return substr((string)agent_redact($error), 0, 1000);
}

function agent_canonical_json(array $value): string
{
    ksort($value);
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function agent_lock(string $key)
{
    $path = sys_get_temp_dir() . '/nimbly-agent-' . hash('sha256', BASE_DIR . ':' . $key) . '.lock';
    $lock = fopen($path, 'c');
    if (!$lock || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('Could not acquire agent lock');
    }
    return $lock;
}

function agent_unlock($lock): void
{
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function agent_ensure_resources(): void
{
    load_library('data');
    $resources = [
        '.agent_runs' => 'agent-runs.json',
        '.agent_events' => 'agent-events.json',
        '.agent_approvals' => 'agent-approvals.json',
        '.agent_state' => 'agent-state.json',
    ];
    foreach ($resources as $resource => $definition) {
        if (!data_exists($resource, '.meta')) {
            $path = BASE_DIR . 'core/modules/agent/resources/' . $definition;
            $meta = json_decode((string)file_get_contents($path), true);
            if (!is_array($meta)) {
                throw new RuntimeException('Agent resource definition is invalid: ' . $resource);
            }
            data_create_resource($resource, $meta);
        }
    }
}
