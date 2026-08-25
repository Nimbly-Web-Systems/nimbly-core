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
            $layer_definition = agent_definition_resolve_pipeline_paths(
                $layer_definition,
                $directory,
                $agent_id
            );
            unset($layer_definition['bootstrap'], $layer_definition['instructions_mode']);
            $definition = agent_definition_merge($definition, $layer_definition);
        }
        $primary_instruction = end($instruction_files) ?: '';
        foreach ((array)($definition['pipeline']['agent'] ?? []) as $phase) {
            if (!empty($phase['instructions'])) {
                $instruction_files[] = (string)$phase['instructions'];
            }
        }
        $definition['instruction_files'] = array_values(array_unique($instruction_files));
        $definition['instructions'] = $primary_instruction;
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
    $required = ['version'];
    if (empty($definition['pipeline'])) {
        $required = array_merge($required, ['instructions', 'prepare_input', 'validate_result', 'deliver']);
    }
    foreach ($required as $key) {
        if (empty($definition[$key])) {
            throw new RuntimeException('Agent definition is missing ' . $key);
        }
    }
    if (!isset($definition['tools']) || !is_array($definition['tools'])) {
        throw new RuntimeException('Agent definition is missing tools');
    }
    foreach ((array)($definition['instruction_files'] ?? [$definition['instructions']]) as $instruction_file) {
        if (!is_file($instruction_file)) {
            throw new RuntimeException('Agent instructions are unavailable');
        }
    }
    agent_validate_definition($definition);
    return $definition;
}

function agent_definition_resolve_pipeline_paths(array $definition, string $directory, string $agent_id): array
{
    if (!isset($definition['pipeline'])) {
        return $definition;
    }
    if (!is_array($definition['pipeline'])) {
        throw new RuntimeException('Agent pipeline is invalid: ' . $agent_id);
    }
    foreach ((array)($definition['pipeline']['agent'] ?? []) as $index => $phase) {
        if (!is_array($phase) || empty($phase['instructions'])) {
            continue;
        }
        $path = (string)$phase['instructions'];
        if (!str_starts_with($path, '/')) {
            if (preg_match('#^[a-z0-9][a-z0-9._/-]*\.md$#i', $path) !== 1 || str_contains($path, '..')) {
                throw new RuntimeException('Agent pipeline instruction path is invalid: ' . $agent_id);
            }
            $path = $directory . $path;
        }
        if (!is_file($path)) {
            throw new RuntimeException('Agent pipeline instructions are unavailable: ' . $agent_id);
        }
        $definition['pipeline']['agent'][$index]['instructions'] = $path;
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
    if ($target !== '' && isset($definition['targets'])) {
        $definition['targets'] = array_values(array_filter(
            (array)$definition['targets'],
            fn($item) => is_array($item) && ($item['identity'] ?? '') === $target
        ));
        if (empty($definition['targets'])) {
            throw new RuntimeException('Agent run target is not configured');
        }
        $definition['runtime_instruction'] = 'This is a scoped run. Review only ' . $target
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

function agent_validate_definition(array $definition): void
{
    if (empty($definition['pipeline'])) {
        foreach (['prepare_input', 'validate_result', 'deliver'] as $callback) {
            if (!is_callable($definition[$callback] ?? null)) {
                throw new RuntimeException('Agent definition callback is invalid: ' . $callback);
            }
        }
    } else {
        agent_validate_pipeline_definition((array)$definition['pipeline']);
    }
    $identities = [];
    foreach ((array)($definition['targets'] ?? []) as $target) {
        if (!is_array($target)
            || trim((string)($target['scope'] ?? '')) === ''
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', (string)($target['identity'] ?? '')) !== 1
            || !in_array(($target['authority'] ?? ''), ['inspection_only', 'autonomous_remediation'], true)) {
            throw new RuntimeException('Agent target configuration is invalid');
        }
        if (isset($identities[$target['identity']])) {
            throw new RuntimeException('Agent target identity is duplicated');
        }
        $identities[$target['identity']] = true;
    }
    foreach ((array)$definition['tools'] as $name => $tool) {
        if (!is_array($tool)
            || preg_match('/^[a-z][a-z0-9_-]*$/', (string)$name) !== 1
            || !in_array(($tool['risk'] ?? ''), ['read_only', 'governed'], true)
            || !is_callable($tool['execute'] ?? null)
            || !is_array($tool['parameters'] ?? null)) {
            throw new RuntimeException('Agent tool definition is invalid: ' . $name);
        }
        if (($tool['risk'] ?? '') === 'governed' && !is_callable($tool['authorize'] ?? null)) {
            throw new RuntimeException('Governed agent tool authorizer is invalid: ' . $name);
        }
        $connector = $tool['connector'] ?? null;
        if (is_array($connector) && !empty($connector['targets'])) {
            $targets = agent_config(['agent_definition' => $definition], (string)$connector['targets'], null);
            if (!is_array($targets)) {
                throw new RuntimeException('Agent tool target reference is invalid: ' . $name);
            }
        }
    }
}

function agent_validate_pipeline_definition(array $pipeline): void
{
    foreach (['input', 'agent', 'output'] as $group) {
        if (!isset($pipeline[$group]) || !is_array($pipeline[$group]) || $pipeline[$group] === []) {
            throw new RuntimeException('Agent pipeline group is invalid: ' . $group);
        }
    }
    $known = [];
    foreach (['input', 'agent', 'output'] as $group) {
        foreach ($pipeline[$group] as $phase) {
            if (!is_array($phase)
                || preg_match('/^[a-z][a-z0-9_-]*$/', (string)($phase['id'] ?? '')) !== 1
                || isset($known[$phase['id']])) {
                throw new RuntimeException('Agent pipeline phase identity is invalid');
            }
            $type = (string)($phase['type'] ?? 'callback');
            if ($group === 'agent' && $type === 'model') {
                if (!is_file((string)($phase['instructions'] ?? ''))
                    || (!empty($phase['projector']) && !is_callable($phase['projector']))
                    || (!empty($phase['validator']) && !is_callable($phase['validator']))) {
                    throw new RuntimeException('Agent model phase is invalid: ' . $phase['id']);
                }
            } elseif ($type !== 'callback' || !is_callable($phase['handler'] ?? null)) {
                throw new RuntimeException('Agent callback phase is invalid: ' . $phase['id']);
            }
            $input_from = (string)($phase['input_from'] ?? '');
            if ($input_from !== '' && !isset($known[$input_from])) {
                throw new RuntimeException('Agent pipeline input reference is invalid: ' . $phase['id']);
            }
            $known[$phase['id']] = true;
        }
    }
    foreach (['result_from', 'delivery_from'] as $reference) {
        if (empty($pipeline[$reference]) || !isset($known[$pipeline[$reference]])) {
            throw new RuntimeException('Agent pipeline result reference is invalid: ' . $reference);
        }
    }
}
