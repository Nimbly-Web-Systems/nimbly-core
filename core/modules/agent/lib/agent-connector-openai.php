<?php

// Default reasoning connector. Provider details stay outside the generic kernel.
function agent_connector_openai(array $source, array $config, array $context): array
{
    $definition = $context['definition'];
    $step = $context['step'];
    $run_uuid = (string)$context['run_uuid'];
    $input = $source['type'] === 'openai.input'
        ? $source['data']['messages']
        : [[
            'role' => 'user',
            'content' => [['type' => 'input_text', 'text' => json_encode($source, JSON_THROW_ON_ERROR)]],
        ]];
    if (!empty($context['run']['event_context'])) {
        $input[] = ['role' => 'user', 'content' => [[
            'type' => 'input_text',
            'text' => json_encode(['event_context' => $context['run']['event_context']], JSON_THROW_ON_ERROR),
        ]]];
    }
    $tools = agent_tools_for_run((array)($definition['tools'] ?? []), $definition, $context['run']);
    $usage = is_array($context['run']['usage'] ?? null) ? $context['run']['usage'] : [
        'input_tokens' => 0, 'cached_input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0,
    ];
    $tool_count = 0;
    $started = microtime(true);
    for ($turn = 1; $turn <= (int)($config['max_turns'] ?? $definition['max_turns'] ?? 8); $turn++) {
        if (microtime(true) - $started > (int)($config['max_wall_seconds'] ?? $definition['max_wall_seconds'] ?? 900)) {
            throw new RuntimeException('Agent wall-clock limit exceeded');
        }
        $schema = $step['output_schema'] ?? null;
        $request = [
            'model' => (string)($config['model'] ?? $definition['model'] ?? 'gpt-5.6-terra'),
            'reasoning' => ['effort' => (string)($config['reasoning_effort'] ?? $definition['reasoning_effort'] ?? 'medium')],
            'instructions' => agent_instructions($definition, $step),
            'input' => $input,
            'tools' => agent_openai_tools($tools),
            'text' => ['format' => is_array($schema) ? [
                'type' => 'json_schema', 'name' => (string)$step['id'], 'strict' => true, 'schema' => $schema,
            ] : ['type' => 'json_object']],
            'store' => false,
            'max_output_tokens' => (int)($config['max_output_tokens'] ?? $definition['max_output_tokens'] ?? 6000),
        ];
        $response = agent_openai_request($request, $context);
        agent_append_event($run_uuid, 'model_response', [
            'turn' => $turn, 'step' => $step['id'], 'response_id' => $response['id'] ?? '',
            'request_id' => $response['_request_id'] ?? '', 'status' => $response['status'] ?? '',
        ]);
        $usage = agent_add_usage($usage, (array)($response['usage'] ?? []));
        agent_store_usage($run_uuid, $usage, $definition);
        $output = is_array($response['output'] ?? null) ? $response['output'] : [];
        $calls = array_values(array_filter($output, fn($item) => ($item['type'] ?? '') === 'function_call'));
        if ($calls === []) {
            $decoded = json_decode(agent_response_text($response), true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Agent returned invalid structured output');
            }
            return agent_artifact((string)($step['produces'] ?? 'agent.result'), 1, $decoded, $source['evidence']);
        }
        $input = array_merge($input, $output);
        foreach ($calls as $call) {
            if (++$tool_count > (int)($config['max_tool_calls'] ?? $definition['max_tool_calls'] ?? 12)) {
                throw new RuntimeException('Agent tool-call limit exceeded');
            }
            $input[] = [
                'type' => 'function_call_output', 'call_id' => $call['call_id'] ?? '',
                'output' => json_encode(agent_execute_tool($run_uuid, $tools, $call, $context),
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ];
        }
    }
    throw new RuntimeException('Agent turn limit exceeded');
}

function agent_tools_for_run(array $tools, array $definition, array $run): array
{
    $target = (string)($run['target'] ?? '');
    $available = [];
    foreach ($tools as $name => $tool) {
        if (!empty($run['read_only']) && ($tool['risk'] ?? '') === 'governed') {
            continue;
        }
        if ($target !== '' && !empty($tool['targets'])) {
            $permitted = false;
            foreach ((array)agent_config(['definition' => $definition], (string)$tool['targets'], []) as $candidate) {
                if (($candidate['identity'] ?? '') === $target
                    && (empty($tool['authority']) || ($candidate['authority'] ?? '') === $tool['authority'])) {
                    $permitted = true;
                }
            }
            if (!$permitted) {
                continue;
            }
        }
        $available[$name] = $tool;
    }
    return $available;
}

function agent_openai_tools(array $tools): array
{
    $result = [];
    foreach ($tools as $name => $tool) {
        $result[] = ['type' => 'function', 'name' => $name,
            'description' => (string)($tool['description'] ?? ''),
            'parameters' => $tool['parameters'], 'strict' => true];
    }
    return $result;
}

function agent_openai_request(array $request, array $context): array
{
    if (!empty($context['openai_request']) && is_callable($context['openai_request'])) {
        return ($context['openai_request'])($request);
    }
    load_library('env');
    $api_key = env('OPENAI_API_KEY');
    if ($api_key === '') {
        throw new RuntimeException('OpenAI is not configured');
    }
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $headers = [];
        $handle = curl_init('https://api.openai.com/v1/responses');
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($request, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $api_key, 'Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 120,
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
        $transport_error = curl_error($handle);
        curl_close($handle);
        if ($transport_error === '' && $status >= 200 && $status < 300) {
            $response = json_decode((string)$body, true);
            if (is_array($response)) {
                $response['_request_id'] = $headers['x-request-id'] ?? '';
                return $response;
            }
        }
        if (!in_array($status, [429, 500, 502, 503, 504], true) || $attempt === 3) {
            throw new RuntimeException('OpenAI request failed (' . ($status > 0 ? 'HTTP ' . $status : 'transport error') . ')');
        }
        usleep(250000 * $attempt);
    }
    throw new RuntimeException('OpenAI request failed');
}

function agent_response_text(array $response): string
{
    if (is_string($response['output_text'] ?? null)) {
        return $response['output_text'];
    }
    foreach ((array)($response['output'] ?? []) as $item) {
        foreach (($item['type'] ?? '') === 'message' ? (array)($item['content'] ?? []) : [] as $content) {
            if (($content['type'] ?? '') === 'output_text') {
                return (string)($content['text'] ?? '');
            }
        }
    }
    return '';
}

function agent_add_usage(array $total, array $usage): array
{
    $total['input_tokens'] += (int)($usage['input_tokens'] ?? 0);
    $total['cached_input_tokens'] += (int)($usage['input_tokens_details']['cached_tokens'] ?? 0);
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
