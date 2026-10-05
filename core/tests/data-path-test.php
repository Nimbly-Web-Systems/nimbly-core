<?php

// A resource name or record id never leads outside its own folder, a record
// id that arrives with an API request cannot name a dot file, and ids and
// resources that sites use today keep working.

$fixture = sys_get_temp_dir() . '/nimbly-data-path-' . bin2hex(random_bytes(6));
mkdir($fixture . '/ext/data', 0750, true);

$GLOBALS['SYSTEM'] = [
    // data.php derives the data directory from file_base
    'file_base' => $fixture . '/',
    'variables' => [],
    'request_time' => time(),
];
$GLOBALS['fixture_input'] = [];

function load_library($library)
{
    if (in_array($library, ['data', 'util', 'event', 'request-input', 'html-sanitize', 'encrypt'], true)) {
        require_once dirname(__DIR__) . '/lib/' . $library . '.php';
    }
}

function username_get()
{
    return 'tester';
}

function json_input($create_uuid = true)
{
    return $GLOBALS['fixture_input'];
}

function json_result($result, $code = 200, $modified = 0)
{
    return $code;
}

function log_system($message)
{
}

function honeypot_field_name()
{
    return 'website';
}

if (!function_exists('getallheaders')) {
    function getallheaders()
    {
        return [];
    }
}

require_once dirname(__DIR__) . '/lib/data.php';
require_once dirname(__DIR__) . '/modules/api/lib/api.php';

function data_path_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function data_path_remove_fixture($directory)
{
    foreach (array_diff(scandir($directory) ?: [], ['.', '..']) as $entry) {
        $path = $directory . '/' . $entry;
        is_dir($path) && !is_link($path) ? data_path_remove_fixture($path) : unlink($path);
    }
    rmdir($directory);
}

register_shutdown_function('data_path_remove_fixture', $fixture);
// a refused write reports the path it could not use; keep the output to the verdict
error_reporting(E_ALL & ~E_WARNING);

$data = $fixture . '/ext/data';
data_path_assert($GLOBALS['SYSTEM']['data_base'] === $data, 'the test is not running against its own data directory');
$meta = ['fields' => false];

// what sites do today
data_path_assert(data_create('notes', '.meta', $meta) !== false, 'a .meta file could not be created');
data_path_assert(data_exists('notes', '.meta'), 'a .meta file is not found');
data_path_assert(data_create('.jobs', '.meta', $meta) !== false && data_exists('.jobs', '.meta'), 'a dot resource stopped working');
foreach (['a1', 'my.custom-id_1', 'name@example.com', 'two words', '0'] as $id) {
    data_path_assert(data_create('notes', $id, ['title' => $id]) !== false, "id '{$id}' could not be created");
    data_path_assert((data_read('notes', $id)['title'] ?? null) === $id, "id '{$id}' could not be read");
}
data_path_assert(data_create('.files_meta/author', 'img1', ['title' => 'x']) !== false, 'a resource in a subfolder could not be created');
data_path_assert((data_read('.files_meta/author', 'img1')['title'] ?? null) === 'x', 'a resource in a subfolder could not be read');
data_path_assert(data_path('notes') === $data . '/notes', 'the resource path changed');
data_path_assert(data_path('notes', 'a1') === $data . '/notes/a1', 'the record path changed');

// a neighbour resource and a file outside the data directory
data_create('other', '.meta', $meta);
data_create('other', 'b1', ['title' => 'neighbour']);
file_put_contents($fixture . '/ext/outside', 'outside');

$climbing = [
    ['notes', '../other/b1'],
    ['notes', '..'],
    ['notes', '.'],
    ['notes', 'sub/b1'],
    ['notes', '..\\other\\b1'],
    ['notes', "a1\0x"],
    ['notes/../other', 'b1'],
    ['..', 'outside'],
    ['../data/other', 'b1'],
    ['/etc', 'hostname'],
];
foreach ($climbing as [$resource, $id]) {
    $label = json_encode([$resource, $id]);
    data_path_assert(strpos(data_path($resource, $id), $fixture) !== 0, "{$label} still resolves into the project");
    data_path_assert(data_exists($resource, $id) === false, "{$label} is found");
    data_path_assert(data_read($resource, $id) === null, "{$label} is read");
    data_path_assert(!data_create($resource, $id, ['title' => 'planted']), "{$label} is written");
    data_path_assert(!data_delete($resource, $id), "{$label} is deleted");
}
data_path_assert(!data_delete('..'), 'a climbing resource is emptied');
data_path_assert(!data_delete('notes/../other'), 'a neighbour resource is emptied through a climbing name');
data_path_assert(!data_create('../planted', null, null) && !file_exists($fixture . '/ext/planted'), 'a resource folder is created outside the data directory');
data_path_assert((data_read('other', 'b1')['title'] ?? null) === 'neighbour', 'the neighbour record changed');
data_path_assert(file_get_contents($fixture . '/ext/outside') === 'outside', 'the file outside the data directory changed');
data_path_assert(!file_exists($data . '/notes/sub'), 'a subfolder was created from a record id');

// record ids that arrive with an API request
foreach (['a1', 'my.custom-id_1', 'name@example.com', 5] as $id) {
    data_path_assert(api_uuid_valid($id), 'a normal id is refused: ' . json_encode($id));
}
foreach (['', '.meta', '.index', '..', '../other/b1', 'sub/b1', 'a\\b', ['a1'], null] as $id) {
    data_path_assert(!api_uuid_valid($id), 'an unusable id is accepted: ' . json_encode($id));
}

$meta_before = file_get_contents($data . '/notes/.meta');

$GLOBALS['fixture_input'] = ['uuid' => 'c1', 'title' => 'created'];
data_path_assert(resource_post('notes') === 201, 'a normal record is not created through the API');
data_path_assert((data_read('notes', 'c1')['title'] ?? null) === 'created', 'the created record is missing');

foreach (['../other/c2', '.meta', 'sub/c2'] as $id) {
    $GLOBALS['fixture_input'] = ['uuid' => $id, 'title' => 'planted', 'fields' => ['x' => ['type' => 'text']]];
    data_path_assert(resource_post('notes') === 400, "create with id '{$id}' is not refused");
}
data_path_assert(!file_exists($data . '/other/c2'), 'a record was created in the neighbour resource');

$GLOBALS['fixture_input'] = ['c1' => ['title' => 'updated']];
data_path_assert(resource_put('notes') === 200, 'a normal bulk update fails');
data_path_assert((data_read('notes', 'c1')['title'] ?? null) === 'updated', 'the bulk update was not saved');

foreach (['.meta' => ['write_lock' => true], '../other/b1' => ['title' => 'planted']] as $id => $updates) {
    $GLOBALS['fixture_input'] = [$id => $updates];
    data_path_assert(resource_put('notes') === 400, "bulk update of '{$id}' is not refused");
}
$GLOBALS['fixture_input'] = ['' => ['uuid' => '.meta', 'write_lock' => true]];
data_path_assert(resource_put('notes') === 400, 'bulk update with .meta as the record uuid is not refused');
data_path_assert(file_get_contents($data . '/notes/.meta') === $meta_before, 'the .meta file changed through the API');
data_path_assert((data_read('other', 'b1')['title'] ?? null) === 'neighbour', 'the neighbour record changed through the API');

echo "data-path-test: ok\n";
