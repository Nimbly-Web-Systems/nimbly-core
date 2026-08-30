<?php

/**
 * Durable connector-driven agent kernel.
 *
 * Ext owns agent definitions and domain behavior. Core owns run, step, artifact,
 * action, retry, and connector-loading semantics. Connectors accept one artifact
 * and must return one bounded, versioned artifact.
 */

const AGENT_PIPELINE_VERSION = 3;
const AGENT_TERMINAL_STATUSES = ['completed', 'failed'];

class AgentTransientException extends RuntimeException
{
}

// Definitions are Ext-owned. Core validates only the generic pipeline contract.
function agent_definition(string $agent_id): array
{
    if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $agent_id) !== 1) {
        throw new InvalidArgumentException('Invalid agent identity');
    }
    if (isset($GLOBALS['AGENT_TEST_DEFINITIONS'][$agent_id])
        && is_array($GLOBALS['AGENT_TEST_DEFINITIONS'][$agent_id])) {
        $definition = $GLOBALS['AGENT_TEST_DEFINITIONS'][$agent_id];
    } else {
        $directory = BASE_DIR . 'ext/agents/' . $agent_id . '/';
        $path = $directory . 'agent.json';
        if (!is_file($path)) {
            throw new RuntimeException('Agent definition not found: ' . $agent_id);
        }
        $definition = json_decode((string)file_get_contents($path), true);
        if (!is_array($definition)) {
            throw new RuntimeException('Agent configuration is invalid JSON: ' . $agent_id);
        }
        $definition = agent_resolve_definition_paths($definition, $directory);
    }
    agent_validate_definition($definition, $agent_id);
    return $definition;
}

function agent_resolve_definition_paths(array $definition, string $directory): array
{
    $instructions = (string)($definition['instructions'] ?? '');
    if ($instructions !== '') {
        $definition['instructions'] = agent_definition_file($directory, $instructions, 'md');
    }
    foreach (['input', 'agent', 'output'] as $group) {
        foreach ((array)($definition['pipeline'][$group] ?? []) as $index => $step) {
            if (!is_array($step)) {
                continue;
            }
            foreach (['instructions' => 'md', 'schema' => 'json', 'template' => 'tpl'] as $key => $extension) {
                if (!empty($step[$key])) {
                    $step[$key] = agent_definition_file($directory, (string)$step[$key], $extension);
                }
            }
            $definition['pipeline'][$group][$index] = $step;
        }
    }
    return $definition;
}

function agent_definition_file(string $directory, string $relative, string $extension): string
{
    if (str_contains($relative, '..')
        || preg_match('#^[a-z0-9][a-z0-9._/-]*\.' . preg_quote($extension, '#') . '$#i', $relative) !== 1) {
        throw new RuntimeException('Agent file reference is invalid');
    }
    $path = $directory . $relative;
    if (!is_file($path)) {
        throw new RuntimeException('Agent file is unavailable: ' . $relative);
    }
    return $path;
}

function agent_validate_definition(array $definition, string $agent_id = ''): void
{
    if (($definition['id'] ?? '') !== $agent_id && $agent_id !== '') {
        throw new RuntimeException('Agent definition identity does not match its directory');
    }
    foreach (['id', 'version', 'instructions', 'pipeline'] as $required) {
        if (empty($definition[$required])) {
            throw new RuntimeException('Agent definition is missing ' . $required);
        }
    }
    if (!is_file((string)$definition['instructions'])) {
        throw new RuntimeException('Agent instructions are unavailable');
    }
    $pipeline = $definition['pipeline'];
    if (!is_array($pipeline) || (int)($pipeline['version'] ?? 0) !== AGENT_PIPELINE_VERSION) {
        throw new RuntimeException('Agent pipeline version must be ' . AGENT_PIPELINE_VERSION);
    }
    $known = [];
    foreach (['input', 'agent', 'output'] as $group) {
        if (!isset($pipeline[$group]) || !is_array($pipeline[$group]) || $pipeline[$group] === []) {
            throw new RuntimeException('Agent pipeline group is invalid: ' . $group);
        }
        foreach ($pipeline[$group] as $step) {
            if (!is_array($step)) {
                throw new RuntimeException('Agent pipeline step is invalid');
            }
            $id = (string)($step['id'] ?? '');
            $connector = (string)($step['connector'] ?? '');
            if (preg_match('/^[a-z][a-z0-9_-]*$/', $id) !== 1
                || isset($known[$id]) || !agent_valid_capability($connector)) {
                throw new RuntimeException('Agent pipeline step is invalid: ' . $id);
            }
            $sources = isset($step['from']) ? (array)$step['from'] : [];
            foreach ($sources as $source) {
                if (!isset($known[$source])) {
                    throw new RuntimeException('Agent pipeline input reference is invalid: ' . $id);
                }
            }
            if (isset($step['output_schema'])) {
                agent_validate_output_schema($step['output_schema'], $id);
            }
            $known[$id] = true;
        }
    }
    foreach (['result_from', 'delivery_from'] as $reference) {
        if (!isset($known[$pipeline[$reference] ?? ''])) {
            throw new RuntimeException('Agent pipeline reference is invalid: ' . $reference);
        }
    }
    foreach ((array)($definition['tools'] ?? []) as $name => $tool) {
        if (preg_match('/^[a-z][a-z0-9_-]*$/', (string)$name) !== 1 || !is_array($tool)
            || !agent_valid_capability((string)($tool['connector'] ?? ''))
            || !in_array(($tool['risk'] ?? ''), ['read_only', 'governed'], true)
            || !is_array($tool['parameters'] ?? null)) {
            throw new RuntimeException('Agent tool definition is invalid: ' . $name);
        }
        if (($tool['risk'] ?? '') === 'governed'
            && !agent_valid_capability((string)($tool['authorizer'] ?? ''))) {
            throw new RuntimeException('Governed agent tool authorizer is invalid: ' . $name);
        }
    }
}

function agent_validate_output_schema($schema, string $identity): void
{
    if (!is_array($schema) || ($schema['type'] ?? '') !== 'object'
        || !is_array($schema['properties'] ?? null) || !is_array($schema['required'] ?? null)
        || ($schema['additionalProperties'] ?? null) !== false) {
        throw new RuntimeException('Agent output schema is invalid: ' . $identity);
    }
    json_encode($schema, JSON_THROW_ON_ERROR);
}

function agent_valid_capability(string $id): bool
{
    return preg_match('/^[a-z][a-z0-9-]*$/', $id) === 1;
}

function agent_connector_callable(string $id): callable
{
    if (!agent_valid_capability($id)) {
        throw new RuntimeException('Agent connector identity is invalid');
    }
    $function = 'agent_connector_' . str_replace('-', '_', $id);
    if (!function_exists($function)) {
        load_library('agent-connector-' . $id);
    }
    if (!is_callable($function)) {
        throw new RuntimeException('Agent connector is unavailable: ' . $id);
    }
    return $function;
}

function agent_config(array $context, string $path = '', $default = null)
{
    $value = $context['definition'] ?? null;
    if (!is_array($value)) {
        return $default;
    }
    foreach ($path === '' ? [] : explode('.', $path) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function agent_instructions(array $definition, ?array $step = null): string
{
    $path = (string)($step['instructions'] ?? $definition['instructions'] ?? '');
    if (!is_file($path)) {
        throw new RuntimeException('Agent instructions are unavailable');
    }
    $instructions = trim((string)file_get_contents($path));
    if (!empty($definition['_runtime_instruction'])) {
        $instructions .= "\n\n" . trim((string)$definition['_runtime_instruction']);
    }
    return $instructions;
}

function agent_scope_definition(array $definition, array $run): array
{
    $target = trim((string)($run['target'] ?? ''));
    if ($target !== '' && isset($definition['targets'])) {
        $definition['targets'] = array_values(array_filter(
            (array)$definition['targets'],
            fn($item) => is_array($item) && ($item['identity'] ?? '') === $target
        ));
        if ($definition['targets'] === []) {
            throw new RuntimeException('Agent run target is not configured');
        }
        $definition['_runtime_instruction'] = 'This run is scoped to ' . $target . '. Review only that target.';
    }
    if (!empty($run['read_only'])) {
        $definition['tools'] = array_filter(
            (array)($definition['tools'] ?? []),
            fn($tool) => is_array($tool) && ($tool['risk'] ?? '') === 'read_only'
        );
        $definition['_runtime_instruction'] = trim((string)($definition['_runtime_instruction'] ?? '')
            . ' This run is strictly read-only. Governed tools are unavailable.');
    }
    return $definition;
}

// Persistence is deliberately centralized so every connector gets identical durability.
function agent_redact($value)
{
    if (is_array($value)) {
        $result = [];
        foreach ($value as $key => $item) {
            $usage = preg_match('/^(?:input|output|total|cached)_tokens(?:_details)?$/', (string)$key) === 1;
            $result[$key] = !$usage && preg_match('/(secret|token|password|api[_-]?key|authorization|private[_-]?key)/i', (string)$key)
                ? '[REDACTED]' : agent_redact($item);
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
    $value = agent_canonical_value($value);
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function agent_canonical_value(array $value): array
{
    if (!array_is_list($value)) {
        ksort($value);
    }
    foreach ($value as $key => $item) {
        if (is_array($item)) {
            $value[$key] = agent_canonical_value($item);
        }
    }
    return $value;
}

function agent_lock(string $key)
{
    $lock = fopen(sys_get_temp_dir() . '/nimbly-agent-' . hash('sha256', BASE_DIR . ':' . $key) . '.lock', 'c');
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
    foreach ([
        '.agent_runs' => 'agent-runs.json', '.agent_events' => 'agent-events.json',
        '.agent_steps' => 'agent-steps.json', '.agent_actions' => 'agent-actions.json',
    ] as $resource => $definition) {
        if (!data_exists($resource, '.meta')) {
            $meta = json_decode((string)file_get_contents(BASE_DIR . 'core/modules/agent/resources/' . $definition), true);
            if (!is_array($meta)) {
                throw new RuntimeException('Agent resource definition is invalid: ' . $resource);
            }
            data_create_resource($resource, $meta);
        }
    }
}

function agent_append_event(string $run_uuid, string $type, array $payload): string
{
    $uuid = substr(hash('sha256', $run_uuid . ':' . microtime(true) . ':' . random_bytes(8)), 0, 16);
    data_create('.agent_events', $uuid, [
        'run_uuid' => $run_uuid,
        'sequence' => count(data_read('.agent_events', ['run_uuid' => $run_uuid]) ?: []) + 1,
        'occurred_at' => time(), 'type' => $type, 'payload' => agent_redact($payload),
    ]);
    return $uuid;
}

function agent_update_run(string $run_uuid, array $changes): void
{
    if (!data_update('.agent_runs', $run_uuid, $changes)) {
        throw new RuntimeException('Could not update agent run');
    }
}

// Runs are idempotent by agent, scheduled occurrence, and optional caller suffix.
function agent_enqueue(string $agent_id, ?int $now = null, array $options = []): string
{
    $definition = agent_definition($agent_id);
    agent_ensure_resources();
    $now ??= time();
    $timezone = new DateTimeZone((string)($definition['timezone'] ?? 'UTC'));
    $occurrence = (new DateTimeImmutable('@' . $now))->setTimezone($timezone)->format('Y-m-d');
    $suffix = trim((string)($options['idempotency_suffix'] ?? ''));
    if ($suffix !== '' && preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $suffix) !== 1) {
        throw new InvalidArgumentException('Invalid agent idempotency suffix');
    }
    $idempotency_key = $agent_id . ':' . $occurrence . ($suffix === '' ? '' : ':' . $suffix);
    $run_uuid = substr(hash('sha256', $idempotency_key), 0, 16);
    $lock = agent_lock('enqueue-' . $run_uuid);
    try {
        $run = data_read('.agent_runs', $run_uuid);
        if (!is_array($run)) {
            $run = [
                'agent_id' => $agent_id, 'agent_version' => (string)$definition['version'],
                'instructions_sha256' => hash('sha256', agent_instructions($definition)),
                'trigger' => (string)($options['trigger'] ?? 'scheduled'),
                'scheduled_at' => $now, 'scheduled_occurrence' => $occurrence,
                'timezone' => $timezone->getName(), 'status' => 'scheduled',
                'idempotency_key' => $idempotency_key, 'source_report_uuids' => [],
                'delivery' => [], 'failure_reason' => '', 'lease_expires_at' => 0,
                'target' => (string)($options['target'] ?? ''), 'read_only' => !empty($options['read_only']),
                'event_context' => agent_event_context($options['event_context'] ?? []),
            ];
            if (!data_create('.agent_runs', $run_uuid, $run)) {
                throw new RuntimeException('Could not create agent run');
            }
            agent_append_event($run_uuid, 'run_scheduled', ['occurrence' => $occurrence]);
        }
        if (!in_array(($run['status'] ?? ''), AGENT_TERMINAL_STATUSES, true)) {
            agent_queue($run_uuid);
        }
    } finally {
        agent_unlock($lock);
    }
    return $run_uuid;
}

function agent_queue(string $run_uuid, string $suffix = ''): void
{
    load_library('job');
    job_enqueue('agent', ['run_uuid' => $run_uuid], [
        'uuid' => substr(hash('sha256', 'agent-job:' . $run_uuid . ':' . $suffix), 0, 16),
        'max_attempts' => 3,
    ]);
}

function agent_run(string $run_uuid, array $options = []): array
{
    agent_ensure_resources();
    $lock = agent_lock('run-' . $run_uuid);
    try {
        $run = data_read('.agent_runs', $run_uuid);
        if (!is_array($run)) {
            throw new RuntimeException('Agent run not found');
        }
        if (in_array(($run['status'] ?? ''), AGENT_TERMINAL_STATUSES, true)) {
            return $run;
        }
        $definition = agent_scope_definition(agent_definition((string)$run['agent_id']), $run);
        agent_update_run($run_uuid, [
            'status' => 'running', 'started_at' => (int)($run['started_at'] ?? 0) ?: time(),
            'lease_expires_at' => time() + (int)($definition['lease_seconds'] ?? 1200),
        ]);
        agent_append_event($run_uuid, 'run_started', []);
        $context = array_merge($options, [
            'definition' => $definition, 'run' => $run, 'run_uuid' => $run_uuid,
        ]);
        $execution = agent_execute_pipeline($run_uuid, $definition, $run, $context);
        $delivery = $execution['delivery'];
        if (!is_array($delivery) || empty($delivery['success'])) {
            throw new RuntimeException((string)($delivery['error'] ?? 'Agent delivery failed'));
        }
        agent_append_event($run_uuid, 'reports_accepted', $delivery);
        agent_update_run($run_uuid, [
            'status' => 'completed', 'completed_at' => time(), 'lease_expires_at' => 0,
            'structured_result' => $execution['result'],
            'delivery' => $delivery['deliveries'] ?? [],
            'failure_reason' => '',
        ]);
    } catch (Throwable $error) {
        $run = data_read('.agent_runs', $run_uuid);
        if (is_array($run) && !in_array(($run['status'] ?? ''), AGENT_TERMINAL_STATUSES, true)) {
            $terminal = $options['terminal_on_failure'] ?? true;
            agent_append_event($run_uuid, $terminal ? 'run_failed' : 'run_retry_scheduled', [
                'error' => agent_safe_error($error->getMessage()),
            ]);
            agent_update_run($run_uuid, [
                'status' => $terminal ? 'failed' : 'scheduled',
                'completed_at' => $terminal ? time() : 0, 'lease_expires_at' => 0,
                'failure_reason' => agent_safe_error($error->getMessage()),
            ]);
        }
        if (($options['terminal_on_failure'] ?? true) === false) {
            throw $error;
        }
    } finally {
        agent_unlock($lock);
    }
    return data_read('.agent_runs', $run_uuid) ?: [];
}

// The kernel knows ordering and durability; connectors own all domain behavior.
function agent_execute_pipeline(string $run_uuid, array $definition, array $run, array $context): array
{
    $artifacts = [];
    $previous = agent_artifact('agent.run', 1, $run);
    foreach (['input', 'agent', 'output'] as $group) {
        foreach ($definition['pipeline'][$group] as $step) {
            $source = agent_pipeline_source($step, $artifacts, $previous);
            $artifact = agent_execute_durable_step($run_uuid, $step, $source, $artifacts, $context);
            $artifacts[$step['id']] = $artifact;
            $previous = $artifact;
        }
    }
    return [
        'result' => agent_artifact_data($artifacts[$definition['pipeline']['result_from']]),
        'delivery' => agent_artifact_data($artifacts[$definition['pipeline']['delivery_from']]),
        'artifacts' => $artifacts,
    ];
}

function agent_pipeline_source(array $step, array $artifacts, array $previous): array
{
    $references = $step['from'] ?? [];
    if ($references === '' || $references === []) {
        return $previous;
    }
    $references = is_array($references) ? $references : [$references];
    if (count($references) === 1) {
        return $artifacts[$references[0]];
    }
    $data = [];
    foreach ($references as $reference) {
        if (!isset($artifacts[$reference])) {
            throw new RuntimeException('Agent pipeline artifact is unavailable: ' . $reference);
        }
        $data[$reference] = $artifacts[$reference];
    }
    return agent_artifact('agent.artifact-set', 1, $data);
}

function agent_execute_durable_step(
    string $run_uuid,
    array $step,
    array $source,
    array $artifacts,
    array $context
): array {
    $step_id = (string)$step['id'];
    $uuid = substr(hash('sha256', $run_uuid . ':' . $step_id), 0, 16);
    $stored = data_read('.agent_steps', $uuid);
    if (is_array($stored) && ($stored['status'] ?? '') === 'completed'
        && agent_valid_artifact($stored['artifact'] ?? null)) {
        return $stored['artifact'];
    }
    $attempts = (int)($stored['attempts'] ?? 0) + 1;
    $record = [
        'run_uuid' => $run_uuid, 'step_id' => $step_id,
        'step_type' => (string)$step['connector'], 'status' => 'running', 'attempts' => $attempts,
        'input_digest' => hash('sha256', agent_canonical_json($source)), 'output_digest' => '',
        'artifact' => [], 'started_at' => time(), 'completed_at' => 0,
        'retryable' => false, 'error' => [],
    ];
    is_array($stored) ? data_update('.agent_steps', $uuid, $record) : data_create('.agent_steps', $uuid, $record);
    try {
        $handler = agent_connector_callable((string)$step['connector']);
        $artifact = $handler($source, (array)($step['config'] ?? []), array_merge($context, [
            'step' => $step, 'artifacts' => $artifacts,
        ]));
        if (!agent_valid_artifact($artifact)) {
            throw new RuntimeException('Agent connector returned an invalid artifact');
        }
        agent_bound_artifact($artifact);
        data_update('.agent_steps', $uuid, [
            'status' => 'completed', 'artifact' => $artifact,
            'output_digest' => hash('sha256', agent_canonical_json($artifact)),
            'completed_at' => time(), 'retryable' => false, 'error' => [],
        ]);
        agent_append_event($run_uuid, 'step_completed', ['step' => $step_id, 'attempt' => $attempts]);
        return $artifact;
    } catch (Throwable $error) {
        $retryable = $error instanceof AgentTransientException;
        data_update('.agent_steps', $uuid, [
            'status' => 'failed', 'retryable' => $retryable,
            'error' => ['code' => get_class($error), 'message' => agent_safe_error($error->getMessage())],
            'completed_at' => time(),
        ]);
        agent_append_event($run_uuid, 'step_failed', [
            'step' => $step_id, 'connector' => $step['connector'], 'retryable' => $retryable,
            'cause' => agent_safe_error($error->getMessage()),
        ]);
        throw $error;
    }
}

function agent_artifact(string $type, int $version, array $data, array $evidence = [], array $meta = []): array
{
    if (!agent_valid_capability(str_replace('.', '-', $type)) || $version < 1) {
        throw new RuntimeException('Agent artifact identity is invalid');
    }
    return ['type' => $type, 'version' => $version, 'data' => $data, 'evidence' => $evidence, 'meta' => $meta];
}

function agent_valid_artifact($artifact): bool
{
    return is_array($artifact) && is_string($artifact['type'] ?? null)
        && (int)($artifact['version'] ?? 0) > 0 && is_array($artifact['data'] ?? null)
        && is_array($artifact['evidence'] ?? null) && is_array($artifact['meta'] ?? null);
}

function agent_artifact_data(array $artifact): array
{
    if (!agent_valid_artifact($artifact)) {
        throw new RuntimeException('Agent artifact is invalid');
    }
    return $artifact['data'];
}

function agent_bound_artifact(array $artifact): void
{
    $json = json_encode(agent_redact($artifact), JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    if (!is_string($json) || strlen($json) > 262144) {
        throw new RuntimeException('Agent artifact exceeds the 256 KiB limit');
    }
}

function agent_event_context($context): array
{
    if ($context === null || $context === []) {
        return [];
    }
    if (!is_array($context)) {
        throw new InvalidArgumentException('Agent event context must be an object');
    }
    $json = json_encode($context, JSON_THROW_ON_ERROR);
    if (strlen($json) > 32768) {
        throw new InvalidArgumentException('Agent event context is too large');
    }
    return $context;
}

function agent_retry(string $run_uuid): string
{
    agent_ensure_resources();
    $run = data_read('.agent_runs', $run_uuid);
    if (!is_array($run) || ($run['status'] ?? '') !== 'failed') {
        throw new RuntimeException('Only a failed agent run can be retried');
    }
    $retry = (int)($run['retry_number'] ?? 0) + 1;
    agent_update_run($run_uuid, [
        'status' => 'scheduled', 'retry_number' => $retry, 'completed_at' => 0,
        'lease_expires_at' => 0, 'failure_reason' => '',
    ]);
    agent_append_event($run_uuid, 'run_retry_scheduled', ['retry_number' => $retry]);
    agent_queue($run_uuid, 'retry:' . $retry);
    return $run_uuid;
}

function agent_recover_expired_runs(?int $now = null): int
{
    agent_ensure_resources();
    $now ??= time();
    $count = 0;
    foreach (data_read('.agent_runs') ?: [] as $run) {
        if (!is_array($run) || ($run['status'] ?? '') !== 'running'
            || (int)($run['lease_expires_at'] ?? 0) >= $now) {
            continue;
        }
        agent_update_run((string)$run['uuid'], ['status' => 'scheduled', 'lease_expires_at' => 0]);
        agent_queue((string)$run['uuid'], 'recover:' . $now);
        $count++;
    }
    return $count;
}

function agent_watchdog_status(string $agent_id, ?int $now = null): array
{
    $definition = agent_definition($agent_id);
    $now ??= time();
    $latest = null;
    foreach (data_read('.agent_runs') ?: [] as $run) {
        if (is_array($run) && ($run['agent_id'] ?? '') === $agent_id
            && (!is_array($latest) || (int)($run['scheduled_at'] ?? 0) > (int)($latest['scheduled_at'] ?? 0))) {
            $latest = $run;
        }
    }
    $timezone = new DateTimeZone((string)($definition['timezone'] ?? 'UTC'));
    $deadline = DateTimeImmutable::createFromFormat(
        'Y-m-d H:i',
        (new DateTimeImmutable('@' . $now))->setTimezone($timezone)->format('Y-m-d')
            . ' ' . (string)($definition['deadline_at'] ?? '23:59'),
        $timezone
    );
    $healthy = is_array($latest) && ($latest['status'] ?? '') === 'completed';
    if (!$healthy && $deadline instanceof DateTimeImmutable && $now < $deadline->getTimestamp()) {
        return ['healthy' => true, 'state' => 'pending'];
    }
    return ['healthy' => $healthy, 'state' => $healthy ? 'completed' : 'overdue'];
}

function agent_job(array $job)
{
    $run_uuid = trim((string)($job['payload']['run_uuid'] ?? ''));
    if ($run_uuid === '') {
        throw new RuntimeException('Agent run UUID is missing');
    }
    $result = agent_run($run_uuid, [
        'terminal_on_failure' => (int)($job['attempts'] ?? 1) >= (int)($job['max_attempts'] ?? 3),
    ]);
    if (($result['status'] ?? '') !== 'completed') {
        throw new RuntimeException(agent_safe_error((string)($result['failure_reason'] ?? 'Agent run failed')));
    }
    return true;
}

function agent_sc($params)
{
    load_library('data');
    $agent_id = get_param_value($params, 'agent', current($params));
    $status = agent_watchdog_status((string)$agent_id);
    http_response_code($status['healthy'] ? 200 : 503);
    header('Content-Type: text/plain; charset=utf-8');
    return $status['healthy'] ? 'ok' : 'unavailable';
}

// Governed tools share one durable action lifecycle regardless of connector.
function agent_execute_tool(string $run_uuid, array $tools, array $call, array $context): array
{
    $name = (string)($call['name'] ?? '');
    $tool = $tools[$name] ?? null;
    $arguments = json_decode((string)($call['arguments'] ?? ''), true);
    if (!is_array($tool) || !is_array($arguments)) {
        throw new RuntimeException('Agent tool call is invalid');
    }
    agent_validate_arguments($arguments, (array)$tool['parameters']);
    $identity = agent_action_identity($run_uuid, $name, $arguments);
    $stored_result = agent_tool_result($run_uuid, $identity);
    if ($stored_result !== null) {
        return $stored_result;
    }
    agent_append_event($run_uuid, 'tool_requested', [
        'tool_key' => $identity, 'call_id' => $call['call_id'] ?? '', 'tool' => $name,
        'risk' => $tool['risk'], 'arguments' => $arguments,
    ]);
    if ($tool['risk'] === 'governed') {
        $authorizer = agent_connector_callable((string)$tool['authorizer']);
        $decision = $authorizer(agent_artifact('agent.action-request', 1, [
            'tool' => $name, 'arguments' => $arguments, 'action_digest' => $identity,
        ]), (array)($tool['authorization'] ?? []), $context);
        $decision = agent_artifact_data($decision);
        if (!in_array(($decision['status'] ?? ''), ['authorized', 'denied', 'human_approval_required'], true)
            || !hash_equals($identity, (string)($decision['action_digest'] ?? ''))) {
            throw new RuntimeException('Governed action authorization is invalid');
        }
        agent_append_event($run_uuid, 'risk_decision', $decision);
        if ($decision['status'] !== 'authorized') {
            $result = ['status' => $decision['status'], 'reason' => (string)($decision['reason'] ?? ''),
                'action_digest' => $identity];
            agent_store_action($identity, $run_uuid, $name, $arguments, 'blocked', $result, $result['reason']);
            agent_append_event($run_uuid, 'tool_completed', ['tool_key' => $identity, 'tool' => $name, 'result' => $result]);
            return $result;
        }
        $context['authorization'] = $decision;
        $existing = data_read('.agent_actions', substr($identity, 0, 16));
        if (is_array($existing) && in_array(($existing['status'] ?? ''), ['succeeded', 'blocked'], true)) {
            return (array)($existing['result'] ?? []);
        }
        if (is_array($existing) && ($existing['status'] ?? '') === 'uncertain') {
            return ['status' => 'uncertain', 'reason' => (string)($existing['reason'] ?? '')];
        }
        agent_store_action($identity, $run_uuid, $name, $arguments, 'executing', [], '');
    }
    $connector = agent_connector_callable((string)$tool['connector']);
    $started = microtime(true);
    try {
        $artifact = $connector(agent_artifact('agent.tool-request', 1, [
            'tool' => $name, 'arguments' => $arguments, 'action_digest' => $identity,
        ]), (array)($tool['config'] ?? []), array_merge($context, ['tool' => $tool]));
        $result = agent_artifact_data($artifact);
    } catch (Throwable $error) {
        if ($tool['risk'] === 'governed') {
            agent_store_action($identity, $run_uuid, $name, $arguments, 'uncertain', [], agent_safe_error($error->getMessage()));
        }
        throw $error;
    }
    if ($tool['risk'] === 'governed') {
        agent_store_action($identity, $run_uuid, $name, $arguments,
            ($result['status'] ?? '') === 'blocked' ? 'blocked' : 'succeeded', $result, (string)($result['reason'] ?? ''));
    }
    agent_append_event($run_uuid, 'tool_completed', [
        'tool_key' => $identity, 'tool' => $name,
        'duration_ms' => (int)round((microtime(true) - $started) * 1000), 'result' => $result,
    ]);
    return $result;
}

function agent_action_identity(string $run_uuid, string $tool, array $arguments): string
{
    $run = data_read('.agent_runs', $run_uuid);
    $lineage = is_array($run) ? (string)($run['idempotency_key'] ?? $run_uuid) : $run_uuid;
    $target = (string)($arguments['target'] ?? $arguments['server'] ?? '');
    return hash('sha256', $lineage . "\n" . $tool . "\n" . $target . "\n" . agent_canonical_json($arguments));
}

function agent_store_action(string $identity, string $run_uuid, string $tool, array $arguments,
    string $status, array $result, string $reason): void
{
    $uuid = substr($identity, 0, 16);
    $existing = data_read('.agent_actions', $uuid);
    $record = [
        'lineage_key' => $identity, 'tool' => $tool,
        'target' => (string)($arguments['target'] ?? $arguments['server'] ?? ''),
        'arguments_digest' => hash('sha256', agent_canonical_json($arguments)), 'status' => $status,
        'attempts' => (int)($existing['attempts'] ?? 0) + ($status === 'executing' ? 1 : 0),
        'result' => $result, 'reason' => substr($reason, 0, 1000), 'updated_at' => time(), 'run_uuid' => $run_uuid,
    ];
    is_array($existing) ? data_update('.agent_actions', $uuid, $record) : data_create('.agent_actions', $uuid, $record);
}

function agent_tool_result(string $run_uuid, string $identity): ?array
{
    foreach (data_read('.agent_events') ?: [] as $event) {
        if (is_array($event) && ($event['run_uuid'] ?? '') === $run_uuid
            && ($event['type'] ?? '') === 'tool_completed'
            && ($event['payload']['tool_key'] ?? '') === $identity) {
            return is_array($event['payload']['result'] ?? null) ? $event['payload']['result'] : [];
        }
    }
    return null;
}

function agent_validate_arguments(array $arguments, array $schema): void
{
    foreach ((array)($schema['required'] ?? []) as $required) {
        if (!array_key_exists($required, $arguments)) {
            throw new RuntimeException('Required tool argument is missing');
        }
    }
    foreach ($arguments as $key => $value) {
        $property = $schema['properties'][$key] ?? null;
        if (!is_array($property) || (($property['type'] ?? '') === 'string' && !is_string($value))
            || (!empty($property['enum']) && !in_array($value, $property['enum'], true))) {
            throw new RuntimeException('Agent tool argument is invalid: ' . $key);
        }
    }
}
