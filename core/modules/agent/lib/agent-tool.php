<?php

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
            $decoded_error = json_decode((string)$body, true);
            $error_code = substr((string)($decoded_error['error']['code'] ?? $decoded_error['error']['type'] ?? ''), 0, 80);
            $detail = $status > 0 ? 'HTTP ' . $status : 'transport error';
            if ($error_code !== '') {
                $detail .= ', ' . $error_code;
            }
            throw new RuntimeException('OpenAI request failed (' . $detail . ')');
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
