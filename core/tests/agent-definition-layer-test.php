<?php

$fixture = sys_get_temp_dir() . '/nimbly-agent-layer-test-' . bin2hex(random_bytes(4));
mkdir($fixture . '/core/agents/example', 0755, true);
mkdir($fixture . '/ext/agents/example', 0755, true);
file_put_contents($fixture . '/core/agents/example/instructions.md', "Core evidence rules.\n");
file_put_contents($fixture . '/ext/agents/example/instructions.md', "Ext personality rules.\n");
file_put_contents($fixture . '/ext/agents/example/bootstrap.php', "<?php\n\$GLOBALS['LAYER_BOOTSTRAPPED'] = true;\n");
file_put_contents($fixture . '/core/agents/example/agent.json', json_encode([
    'id' => 'example',
    'name' => 'Core Example',
    'version' => '1.0.0',
    'instructions' => 'instructions.md',
    'model' => 'base-model',
    'tools' => [
        'inspect' => [
            'risk' => 'read_only',
            'parameters' => ['type' => 'object', 'additionalProperties' => false],
            'execute' => 'example_inspect',
        ],
    ],
    'prepare_input' => 'example_prepare',
    'validate_result' => 'example_validate',
    'deliver' => 'example_deliver',
], JSON_PRETTY_PRINT));
file_put_contents($fixture . '/ext/agents/example/agent.json', json_encode([
    'name' => 'Ext Example',
    'version' => '2.0.0',
    'bootstrap' => ['bootstrap.php'],
    'instructions' => 'instructions.md',
    'tools' => [
        'inspect' => ['description' => 'Layered inspection'],
    ],
], JSON_PRETTY_PRINT));

define('BASE_DIR', $fixture . '/');
require_once dirname(__DIR__) . '/modules/agent/lib/agent-runtime.php';

function agent_layer_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function agent_layer_remove(string $path): void
{
    if (is_dir($path)) {
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $item) {
            agent_layer_remove($path . '/' . $item);
        }
        rmdir($path);
    } elseif (file_exists($path)) {
        unlink($path);
    }
}

$definition = agent_definition('example');
agent_layer_assert($definition['name'] === 'Ext Example', 'ext scalar did not override core');
agent_layer_assert($definition['model'] === 'base-model', 'core default was not inherited');
agent_layer_assert(
    ($definition['tools']['inspect']['risk'] ?? '') === 'read_only'
        && ($definition['tools']['inspect']['description'] ?? '') === 'Layered inspection',
    'nested tool configuration was not layered'
);
agent_layer_assert(!empty($GLOBALS['LAYER_BOOTSTRAPPED']), 'ext bootstrap was not loaded');
agent_layer_assert(
    agent_instructions($definition) === "Core evidence rules.\n\nExt personality rules.",
    'core and ext instructions were not composed in order'
);

agent_layer_remove($fixture);
echo "Agent definition layer tests passed.\n";
