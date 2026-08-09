<?php

function render_field_context_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$GLOBALS['SYSTEM'] = [
    'file_base' => dirname(__DIR__, 2) . '/',
    'env_paths' => ['ext', 'core'],
    'modules' => ['root' => '/'],
    'variables' => [],
];

require_once __DIR__ . '/../lib/find.php';
load_libraries(['get', 'set']);

function run_single_sc(string $name): void
{
    $GLOBALS['rendered_field_contexts'][$name] = $GLOBALS['SYSTEM']['variables'];
}

require_once __DIR__ . '/../modules/forms/lib/render-field.php';

render_field(['type' => 'slug', 'name' => 'Slug', 'source' => 'title'], 'title_slug');
render_field(['type' => 'image', 'name' => 'Main image'], 'image');

$image_context = $GLOBALS['rendered_field_contexts']['field-image'];
render_field_context_assert(
    !array_key_exists('_f.source', $image_context),
    'image field inherited the preceding slug field source'
);
render_field_context_assert(
    $image_context['_f.key'] === 'image',
    'image field did not receive its own field context'
);

echo "Render field context tests passed.\n";
