<?php

function data_meta_embedded_schema_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function data_meta_embedded_schema_remove_fixture(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $item) {
        $item_path = $path . '/' . $item;
        if (is_dir($item_path)) {
            data_meta_embedded_schema_remove_fixture($item_path);
        } else {
            unlink($item_path);
        }
    }
    rmdir($path);
}

$fixture = sys_get_temp_dir() . '/nimbly-data-meta-test-' . bin2hex(random_bytes(4));
mkdir($fixture, 0755, true);

$GLOBALS['SYSTEM'] = [
    'file_base' => dirname(__DIR__, 2) . '/',
    'env_paths' => ['ext', 'core'],
    'modules'   => ['root' => '/'],
    'variables' => [],
];

require_once __DIR__ . '/../lib/find.php';
load_library('data');
// Redirect data_*() calls to an isolated fixture dir instead of real ext/data.
$GLOBALS['SYSTEM']['data_base'] = $fixture;

// A resource with no external .meta and no uuid given falls back to the
// auto-created default (fields: false) — unchanged, pre-existing behavior.
$meta = data_meta('settings_a');
data_meta_embedded_schema_assert(
    $meta['fields'] === false,
    'resource with no .meta and no uuid should default to fields:false'
);
data_meta_embedded_schema_assert(
    file_exists("$fixture/settings_a/.meta"),
    'data_meta() should still auto-create a default .meta file'
);

// The same resource, now with a uuid pointing at a record that embeds its
// own _fields/_languages, should resolve schema from the record instead.
data_create('settings_a', 'main', [
    'uuid' => 'main',
    '_fields' => [
        'greeting' => ['type' => 'html', 'name' => 'Greeting', 'i18n' => true],
    ],
    '_languages' => ['en', 'nl'],
    'greeting' => ['en' => 'Hello', 'nl' => 'Hallo'],
]);

$meta_with_uuid = data_meta('settings_a', 'main');
data_meta_embedded_schema_assert(
    is_array($meta_with_uuid['fields']) && isset($meta_with_uuid['fields']['greeting']),
    'data_meta() with uuid should resolve the record\'s embedded _fields'
);
data_meta_embedded_schema_assert(
    $meta_with_uuid['fields']['greeting']['type'] === 'html',
    'embedded field definition should come through intact'
);
data_meta_embedded_schema_assert(
    $meta_with_uuid['languages'] === ['en', 'nl'],
    'data_meta() with uuid should resolve the record\'s embedded _languages'
);

// A cached no-uuid lookup must not be reused for a with-uuid lookup on the
// same resource (and vice versa) — the cache key has to include the uuid.
$meta_no_uuid_again = data_meta('settings_a');
data_meta_embedded_schema_assert(
    $meta_no_uuid_again['fields'] === false,
    'no-uuid lookup must stay cached separately from the with-uuid lookup on the same resource'
);

// A resource with a real external .meta (real fields defined) must ignore
// any embedded schema on individual records — the external .meta wins.
mkdir("$fixture/settings_b", 0755, true);
file_put_contents("$fixture/settings_b/.meta", json_encode([
    'fields' => ['title' => ['type' => 'text']],
]));
data_create('settings_b', 'rec', [
    'uuid' => 'rec',
    '_fields' => ['ignored_field' => ['type' => 'text']],
    'title' => 'A real resource',
]);
$meta_real = data_meta('settings_b', 'rec');
data_meta_embedded_schema_assert(
    isset($meta_real['fields']['title']) && !isset($meta_real['fields']['ignored_field']),
    'a resource with a real external .meta must not fall back to embedded schema'
);

data_meta_embedded_schema_remove_fixture($fixture);
echo "Data meta embedded schema tests passed.\n";
