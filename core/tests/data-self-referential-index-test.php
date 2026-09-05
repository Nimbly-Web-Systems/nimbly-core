<?php

function data_self_index_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function data_self_index_remove_fixture($directory)
{
    if (!is_dir($directory)) {
        return;
    }

    foreach (array_diff(scandir($directory) ?: [], ['.', '..']) as $entry) {
        $path = $directory . '/' . $entry;
        if (is_link($path) || is_file($path)) {
            unlink($path);
        } else {
            data_self_index_remove_fixture($path);
        }
    }
    rmdir($directory);
}

$fixture = sys_get_temp_dir() . '/nimbly-data-self-index-' . bin2hex(random_bytes(4));
mkdir($fixture . '/ext/data/users', 0755, true);
symlink(dirname(__DIR__), $fixture . '/core');

$GLOBALS['SYSTEM'] = [
    'file_base' => $fixture . '/',
    'env_paths' => ['ext', 'core'],
    'variables' => [],
];

require_once dirname(__DIR__) . '/lib/find.php';
load_library('data');
load_library('util');

file_put_contents($fixture . '/ext/data/users/.meta', json_encode([
    'fields' => [
        'email' => ['type' => 'text', 'required' => true],
    ],
    'index' => ['email'],
]));

$email = 'legacy@example.com';
$uuid = md5_uuid($email);
data_self_index_assert(
    data_create('users', $uuid, ['email' => $email]) === true,
    'legacy user record was not created'
);

$index_file = $fixture . "/ext/data/users/.index/email/{$uuid}/{$uuid}";
data_self_index_assert(is_file($index_file), 'self-referential email index was not created');

$matches = data_read_index('users', 'email', $uuid);
data_self_index_assert(isset($matches[$uuid]), 'legacy user was not readable through its email index');

data_self_index_remove_fixture($fixture);
echo "Self-referential data index tests passed.\n";
