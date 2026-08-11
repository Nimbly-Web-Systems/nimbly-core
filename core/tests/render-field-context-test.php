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

function get_param_value($params, $key, $default = null)
{
    return $params[$key] ?? $default;
}

require_once __DIR__ . '/../lib/find.php';
load_libraries(['get', 'set']);

function run_single_sc(string $name): void
{
    $GLOBALS['rendered_field_contexts'][$name] = $GLOBALS['SYSTEM']['variables'];
}

require_once __DIR__ . '/../modules/forms/lib/render-field.php';

render_field(['type' => 'slug', 'name' => 'Slug', 'source' => 'title'], 'title_slug');
render_field(['type' => 'image', 'name' => 'Main image'], 'image');

$fields = [
    'title' => ['type' => 'text', 'name' => 'Title', 'i18n' => true],
    'title_slug' => ['type' => 'slug', 'name' => 'Slug', 'source' => 'title'],
];
require_once __DIR__ . '/../modules/forms/lib/build-form/build-form.php';
_bf_render_field('title_slug', $fields['title_slug'], null, null, null, '', $fields);

$image_context = $GLOBALS['rendered_field_contexts']['field-image'];
render_field_context_assert(
    !array_key_exists('_f.source', $image_context),
    'image field inherited the preceding slug field source'
);
render_field_context_assert(
    $image_context['_f.key'] === 'image',
    'image field did not receive its own field context'
);
render_field_context_assert(
    $GLOBALS['rendered_field_contexts']['field-slug']['_f.i18n'] === true,
    'slug field did not inherit i18n from its source field'
);

echo "Render field context tests passed.\n";
