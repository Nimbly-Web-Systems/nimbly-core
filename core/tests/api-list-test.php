<?php

// The API list: unchanged without parameters, and with sort, filter, limit and
// offset a page of the same list plus the total. A field the API never returns
// cannot be sorted or filtered on.

$fixture = sys_get_temp_dir() . '/nimbly-api-list-' . bin2hex(random_bytes(6));

$GLOBALS['SYSTEM'] = [
    'file_base' => $fixture . '/',
    'env_paths' => ['ext', 'core'],
    'variables' => [],
    'request_time' => time(),
];

function load_library($library)
{
    static $done = [];
    if (isset($done[$library]) || in_array($library, ['log', 'username', 'detect-language', 'get', 'run', 'json', 'access'], true)) {
        return;
    }
    $done[$library] = true;
    foreach (['/lib/', '/modules/admin/lib/'] as $dir) {
        $file = dirname(__DIR__) . $dir . $library . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
}

function log_system($message)
{
}

function username_get()
{
    return 'tester';
}

function detect_language_sc()
{
    return 'en';
}

function get_param_value($params, $name, $default_value = null)
{
    return $params[$name] ?? $default_value;
}

load_library('data');
load_library('util');
require_once dirname(__DIR__) . '/modules/api/lib/api.php';

function api_list_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function api_list_cleanup($dir)
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . '/' . $entry;
        is_dir($path) && !is_link($path) ? api_list_cleanup($path) : unlink($path);
    }
    rmdir($dir);
}

register_shutdown_function('api_list_cleanup', $fixture);

mkdir($fixture . '/ext/data/members', 0750, true);
file_put_contents($fixture . '/ext/data/members/.meta', json_encode([
    'history' => false,
    'hidden' => 'secret',
    'fields' => ['name' => ['type' => 'text'], 'city' => ['type' => 'text'], 'secret' => ['type' => 'text']],
]));
for ($i = 0; $i < 60; $i++) {
    data_create('members', sprintf('m%03d', $i), [
        'name' => ['Okapi', 'tapir', 'Quoll', 'numbat'][$i % 4] . ' ' . ($i % 7),
        'city' => ['Delft', 'Gouda', 'Assen'][$i % 3],
        'age' => (string)(20 + ($i * 13) % 41),
        'secret' => 's' . ($i % 2),
    ]);
}

// Without parameters: what the API answered before
$all = api_hide_fields_all('members', data_read('members'));
api_list_assert(
    json_encode(api_list('members', [])) === json_encode(['members' => $all, 'count' => 60]),
    'a list without parameters is every record and its count'
);
api_list_assert(
    json_encode(api_list('members', ['search' => null, 'sort' => null, 'filter' => null, 'limit' => false, 'offset' => null]))
        === json_encode(['members' => $all, 'count' => 60]),
    'absent parameters change nothing'
);
api_list_assert(!is_dir($fixture . '/ext/data/.tmp/cache/_data/list/members'), 'a whole list builds no list file');

// limit alone: the same first records, and now the total
$answer = api_list('members', ['limit' => 10]);
api_list_assert(
    json_encode($answer['members']) === json_encode(array_slice($all, 0, 10, true)) && $answer['count'] === 10 && $answer['total'] === 60,
    'limit gives the first records and the total'
);

// A page
load_library('data-sort');
$expected = data_sort_param(data_filter($all, 'city:Gouda'), 'age|num|desc');
$answer = api_list('members', ['filter' => 'city:Gouda', 'sort' => 'age|num|desc', 'limit' => 5, 'offset' => 5]);
api_list_assert($answer['total'] === 20 && $answer['count'] === 5, 'a page knows its count and the total');
api_list_assert(
    json_encode($answer['members']) === json_encode(array_slice($expected, 5, 5, true)),
    'a page is the same cut of the sorted and filtered list'
);
api_list_assert(!array_key_exists('secret', current($answer['members'])), 'a page leaves hidden fields out');
$answer = api_list('members', ['offset' => 58]);
api_list_assert(array_keys($answer['members']) === ['m058', 'm059'] && $answer['total'] === 60, 'offset alone gives the rest');
$answer = api_list('members', ['sort' => 'name|desc', 'limit' => 1]);
api_list_assert(strpos(current($answer['members'])['name'], 'tapir') === 0, 'field|desc sorts');

// Search keeps looking in the title field, and pages too
$found = array_filter($all, fn($record) => stripos($record['name'], 'quoll') !== false);
$answer = api_list('members', ['search' => 'quoll', 'limit' => 10]);
api_list_assert(
    json_encode($answer['members']) === json_encode(array_slice($found, 0, 10, true)) && $answer['count'] === 10 && $answer['total'] === 15,
    'search with limit gives the first matches'
);
api_list_assert(api_list('members', ['search' => 'Delft'])['count'] === 0, 'search does not look outside the title field');
$answer = api_list('members', ['search' => 'quoll', 'filter' => 'city:Assen', 'sort' => 'age|num|asc', 'limit' => 2, 'offset' => 1]);
$expected = data_sort_param(data_filter($found, 'city:Assen'), 'age|num|asc');
api_list_assert(
    json_encode($answer['members']) === json_encode(array_slice($expected, 1, 2, true)) && $answer['total'] === count($expected),
    'search, filter, sort and page together'
);

// Hidden fields
foreach ([['filter' => 'secret:s1'], ['sort' => 'secret|text|asc'], ['sort' => 'secret|desc'], ['filter' => 'city:Gouda, secret:(exists)'],
    ['search' => 'quoll', 'filter' => 'secret:s1'], ['sort' => 'name|text|asc,secret|text|asc']] as $options) {
    api_list_assert(api_list('members', $options) === false, 'a hidden field is refused: ' . json_encode($options));
}
api_list_assert(!file_exists($fixture . '/ext/data/.tmp/cache/_data/list/members/secret'), 'no list file of a hidden field is built');

// The value for If-Modified-Since: from a list file, moving with every write
$list_file = $fixture . '/ext/data/.tmp/cache/_data/list/members/city';
api_list_assert(is_file($list_file), 'the page built a list file');
$then = time() - 500;
touch($fixture . '/ext/data/members/.meta', $then - 100);
foreach (scandir(dirname($list_file)) as $entry) {
    if ($entry[0] !== '.') {
        touch(dirname($list_file) . '/' . $entry, $then);
    }
}
clearstatcache();
api_list_assert(data_list_modified('members') === $then, 'the list files tell when the list changed');
api_list_assert(data_modified('members') > $then, 'which is not what the folders say');
data_update('members', 'm003', ['city' => 'Gouda']);
clearstatcache();
api_list_assert(data_list_modified('members') >= time() - 2, 'a write moves that time');
api_list_assert(api_list('members', ['filter' => 'city:Gouda', 'limit' => 1])['total'] === 21, 'and the page has the write');
data_cache_clear();
clearstatcache();
api_list_assert(data_list_modified('members') === data_modified('members'), 'without list files the resource itself tells');

echo "PASS: api list\n";
