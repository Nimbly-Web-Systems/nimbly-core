<?php

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
