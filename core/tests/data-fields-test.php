<?php

function data_fields_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function data_fields_remove_fixture($directory)
{
    foreach (array_diff(scandir($directory) ?: [], ['.', '..']) as $entry) {
        $path = $directory . '/' . $entry;
        if (is_link($path) || is_file($path)) {
            unlink($path);
        } else {
            data_fields_remove_fixture($path);
        }
    }
    rmdir($directory);
}

$fixture = sys_get_temp_dir() . '/nimbly-data-fields-' . bin2hex(random_bytes(4));
mkdir($fixture . '/ext/data/stories', 0755, true);
symlink(dirname(__DIR__), $fixture . '/core');

$GLOBALS['SYSTEM'] = [
    'file_base' => $fixture . '/',
    'env_paths' => ['ext', 'core'],
    'variables' => [],
];

require_once dirname(__DIR__) . '/lib/find.php';
require_once dirname(__DIR__) . '/lib/run.php';
load_library('data');
load_library('util');
load_library('get');

foreach ([
    'one' => ['title' => 'One', 'main_text' => 'long body', 'published' => true],
    'two' => ['title' => 'Two', 'main_text' => 'long body', 'published' => false],
] as $uuid => $record) {
    file_put_contents($fixture . '/ext/data/stories/' . $uuid, json_encode($record));
}

data_sc(['stories', 'var' => 'slim', 'filter' => 'published:1', 'fields' => 'title, missing']);
data_fields_assert(
    get_variable('slim') === ['one' => ['title' => 'One']],
    'fields keeps listed fields only and still filters on an unlisted field'
);

data_sc(['stories.one', 'var' => 'single', 'fields' => 'title,published']);
data_fields_assert(
    get_variable('single') === ['title' => 'One', 'published' => true],
    'fields applies to a single record'
);

data_sc(['stories', 'var' => 'full']);
data_fields_assert(
    (get_variable('full')['one']['main_text'] ?? '') === 'long body',
    'records stay complete without fields'
);

data_fields_remove_fixture($fixture);
echo "Data fields tests passed\n";
