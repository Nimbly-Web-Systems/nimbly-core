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

function example_prepare() {}
function example_validate() {}
function example_deliver() {}
function example_inspect() {}

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
agent_layer_assert(
    agent_config(['agent_definition' => $definition], 'tools.inspect.description') === 'Layered inspection',
    'layered agent configuration is not available to generic callbacks'
);

$pipeline_definition = [
    'tools' => [],
    'pipeline' => [
        'input' => [[
            'id' => 'source', 'type' => 'callback', 'handler' => 'example_prepare',
        ]],
        'agent' => [[
            'id' => 'reason', 'type' => 'model', 'instructions' => __FILE__,
            'validator' => 'example_validate',
        ]],
        'output' => [[
            'id' => 'delivery', 'type' => 'callback', 'input_from' => 'reason',
            'handler' => 'example_deliver',
        ]],
        'result_from' => 'reason',
        'delivery_from' => 'delivery',
    ],
];
agent_validate_definition($pipeline_definition);
agent_layer_assert(true, 'pipeline definitions accept agents without tools');

$invalid_pipeline = $pipeline_definition;
$invalid_pipeline['pipeline']['agent'][0]['input_from'] = 'future_phase';
$invalid_pipeline_denied = false;
try {
    agent_validate_definition($invalid_pipeline);
} catch (RuntimeException) {
    $invalid_pipeline_denied = true;
}
agent_layer_assert($invalid_pipeline_denied, 'pipeline phases cannot reference unavailable artifacts');

$invalid_output_schema = $pipeline_definition;
$invalid_output_schema['pipeline']['agent'][0]['output_schema'] = [
    'type' => 'object',
    'properties' => [],
    'required' => [],
    'additionalProperties' => true,
];
$invalid_output_schema_denied = false;
try {
    agent_validate_definition($invalid_output_schema);
} catch (RuntimeException) {
    $invalid_output_schema_denied = true;
}
agent_layer_assert($invalid_output_schema_denied, 'pipeline output schemas require a strict object contract');

$scoped_definition = $definition;
$scoped_definition['targets'] = [
    ['scope' => 'stage', 'identity' => 'stage.example', 'authority' => 'autonomous_remediation'],
    ['scope' => 'prod', 'identity' => 'prod.example', 'authority' => 'inspection_only'],
];
$scoped_definition['tools']['mutate'] = ['risk' => 'governed'];
$scoped_definition = agent_scope_definition($scoped_definition, [
    'target' => 'prod.example',
    'read_only' => true,
]);
agent_layer_assert(
    count($scoped_definition['targets']) === 1
        && $scoped_definition['targets'][0]['identity'] === 'prod.example',
    'manual target scope does not isolate the configured target'
);
agent_layer_assert(
    !isset($scoped_definition['tools']['mutate']) && isset($scoped_definition['tools']['inspect']),
    'read-only scope does not remove governed tools'
);

$invalid_definition = $definition;
$invalid_definition['targets'] = [
    ['scope' => 'prod', 'identity' => 'duplicate.example', 'authority' => 'inspection_only'],
    ['scope' => 'stage', 'identity' => 'duplicate.example', 'authority' => 'inspection_only'],
];
$invalid_denied = false;
try {
    agent_validate_definition($invalid_definition);
} catch (RuntimeException) {
    $invalid_denied = true;
}
agent_layer_assert($invalid_denied, 'invalid target definitions are rejected before execution');

agent_layer_remove($fixture);
echo "Agent definition layer tests passed.\n";
