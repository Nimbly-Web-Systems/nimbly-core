<?php

function agent_definition(string $agent_id): array
{
    if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $agent_id) !== 1) {
        throw new InvalidArgumentException('Invalid agent identity');
    }
    if (isset($GLOBALS['AGENT_TEST_DEFINITIONS'][$agent_id])
        && is_array($GLOBALS['AGENT_TEST_DEFINITIONS'][$agent_id])) {
        return $GLOBALS['AGENT_TEST_DEFINITIONS'][$agent_id];
    }
    $core_directory = BASE_DIR . 'core/agents/' . $agent_id . '/';
    $ext_directory = BASE_DIR . 'ext/agents/' . $agent_id . '/';
    $layers = [];
    foreach ([$core_directory, $ext_directory] as $directory) {
        $json_path = $directory . 'agent.json';
        if (!is_file($json_path)) {
            continue;
        }
        $layer = json_decode((string)file_get_contents($json_path), true);
        if (!is_array($layer)) {
            throw new RuntimeException('Agent configuration is invalid JSON: ' . $agent_id);
        }
        $layers[] = ['directory' => $directory, 'definition' => $layer];
    }
    if (!empty($layers)) {
        $definition = [];
        $instruction_files = [];
        foreach ($layers as $layer) {
            $directory = $layer['directory'];
            $layer_definition = $layer['definition'];
            foreach ((array)($layer_definition['bootstrap'] ?? []) as $bootstrap) {
                if (!is_string($bootstrap) || preg_match('#^[a-z0-9][a-z0-9._/-]*\.php$#i', $bootstrap) !== 1
                    || str_contains($bootstrap, '..')) {
                    throw new RuntimeException('Agent bootstrap path is invalid: ' . $agent_id);
                }
                $bootstrap_path = $directory . $bootstrap;
                if (!is_file($bootstrap_path)) {
                    throw new RuntimeException('Agent bootstrap is unavailable: ' . $agent_id);
                }
                require_once $bootstrap_path;
            }
            $instructions = (string)($layer_definition['instructions'] ?? '');
            if ($instructions !== '') {
                $instruction_path = str_starts_with($instructions, '/') ? $instructions : $directory . $instructions;
                if (!is_file($instruction_path)) {
                    throw new RuntimeException('Agent instructions are unavailable: ' . $agent_id);
                }
                if (($layer_definition['instructions_mode'] ?? 'append') === 'replace') {
                    $instruction_files = [];
                }
                $instruction_files[] = $instruction_path;
            }
            unset($layer_definition['bootstrap'], $layer_definition['instructions_mode']);
            $definition = agent_definition_merge($definition, $layer_definition);
        }
        $definition['instruction_files'] = array_values(array_unique($instruction_files));
        $definition['instructions'] = end($instruction_files) ?: '';
    } else {
        $php_path = $ext_directory . 'agent.php';
        if (!is_file($php_path)) {
            throw new RuntimeException('Agent definition not found: ' . $agent_id);
        }
        $definition = require $php_path;
        if (is_array($definition) && !empty($definition['instructions'])) {
            $definition['instruction_files'] = [(string)$definition['instructions']];
        }
    }
    if (!is_array($definition) || ($definition['id'] ?? '') !== $agent_id) {
        throw new RuntimeException('Agent definition is invalid: ' . $agent_id);
    }
    if (!empty($definition['abstract'])) {
        throw new RuntimeException('Agent definition requires an ext configuration: ' . $agent_id);
    }
    foreach (['version', 'instructions', 'tools', 'prepare_input', 'validate_result', 'deliver'] as $key) {
        if (empty($definition[$key])) {
            throw new RuntimeException('Agent definition is missing ' . $key);
        }
    }
    foreach ((array)($definition['instruction_files'] ?? [$definition['instructions']]) as $instruction_file) {
        if (!is_file($instruction_file)) {
            throw new RuntimeException('Agent instructions are unavailable');
        }
    }
    return $definition;
}

function agent_definition_merge(array $base, array $override): array
{
    foreach ($override as $key => $value) {
        if (is_array($value) && isset($base[$key]) && is_array($base[$key])
            && !array_is_list($value) && !array_is_list($base[$key])) {
            $base[$key] = agent_definition_merge($base[$key], $value);
        } else {
            $base[$key] = $value;
        }
    }
    return $base;
}

function agent_config(array $dependencies, string $path = '', $default = null)
{
    $value = $dependencies['agent_definition'] ?? null;
    if (!is_array($value)) {
        return $default;
    }
    if ($path === '') {
        return $value;
    }
    foreach (explode('.', $path) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function agent_instructions(array $definition): string
{
    $parts = [];
    foreach ((array)($definition['instruction_files'] ?? [$definition['instructions'] ?? '']) as $path) {
        if (is_string($path) && $path !== '') {
            $parts[] = trim((string)file_get_contents($path));
        }
    }
    if (!empty($definition['runtime_instruction'])) {
        $parts[] = trim((string)$definition['runtime_instruction']);
    }
    return implode("\n\n", array_filter($parts, fn($part) => $part !== ''));
}

function agent_scope_definition(array $definition, array $run): array
{
    $target = trim((string)($run['target'] ?? ''));
    if ($target !== '' && isset($definition['infrastructure']['targets'])) {
        $definition['infrastructure']['targets'] = array_values(array_filter(
            (array)$definition['infrastructure']['targets'],
            fn($item) => is_array($item) && ($item['server'] ?? '') === $target
        ));
        if (empty($definition['infrastructure']['targets'])) {
            throw new RuntimeException('Agent run target is not configured');
        }
        $definition['runtime_instruction'] = 'This is a scoped manual run. Review only ' . $target
            . '. Return exactly one environment object for that configured target; this overrides any normal target-count instruction.';
    }
    if (!empty($run['read_only'])) {
        $definition['tools'] = array_filter(
            (array)$definition['tools'],
            fn($tool) => is_array($tool) && ($tool['risk'] ?? '') === 'read_only'
        );
        $definition['runtime_instruction'] = trim((string)($definition['runtime_instruction'] ?? '')
            . ' This run is strictly read-only. Governed tools are unavailable and no mutation may be requested or claimed.');
    }
    return $definition;
}
