<?php

// A page of a list is answered from a cache per field. It has to give the
// records a full read gives, in the same order, for the filters and sorts
// sites use, and keep doing so after writes, a cache clear and a Git pull.

// With a fixture as argument: a second process, which looks at the Git commit afresh.
$probe = $argv[1] ?? null;
$fixture = $probe ?? sys_get_temp_dir() . '/nimbly-data-list-' . bin2hex(random_bytes(6));

$GLOBALS['SYSTEM'] = [
    'file_base' => $fixture . '/',
    'env_paths' => ['ext', 'core'],
    'variables' => [],
    'request_time' => time(),
];

function load_library($library)
{
    static $done = [];
    if (isset($done[$library]) || in_array($library, ['log', 'username', 'detect-language', 'get', 'run'], true)) {
        return;
    }
    $done[$library] = true;
    $file = dirname(__DIR__) . '/lib/' . $library . '.php';
    if (is_file($file)) {
        require_once $file;
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
    return 'nl';
}

function get_param_value($params, $name, $default_value = null)
{
    return $params[$name] ?? $default_value;
}

load_library('data');
load_library('util');
load_library('data-sort');
require_once dirname(__DIR__) . '/lib/data-list.php';

function data_list_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function data_list_remove($path)
{
    if (is_dir($path) && !is_link($path)) {
        foreach (array_diff(scandir($path), ['.', '..']) as $entry) {
            data_list_remove($path . '/' . $entry);
        }
        rmdir($path);
        return;
    }
    unlink($path);
}

if ($probe !== null) {
    echo implode(',', array_keys(data_query('flat', ['filter' => 'date:1990-05-05'])['records'])), "\n";
    exit(0);
}

register_shutdown_function(function () use ($fixture) {
    if (is_dir($fixture)) {
        data_list_remove($fixture);
    }
});

/** What `[#data#]` does without a page: read everything, sort, filter, keep fields. */
function data_list_full($resource, $options)
{
    $records = data_read($resource);
    if (!empty($options['sort'])) {
        $records = data_sort_param($records, $options['sort']);
    }
    if (isset($options['filter'])) {
        $records = data_filter($records, $options['filter']);
    }
    if (isset($options['fields'])) {
        $records = data_select_fields($records, $options['fields']);
    }
    return $records;
}

function data_list_same($resource, $options, $when)
{
    $label = $resource . ' ' . json_encode($options) . ' ' . $when;
    $full = data_list_full($resource, $options);
    $all = data_query($resource, $options);
    data_list_assert($all['total'] === count($full), 'same number of records: ' . $label);
    data_list_assert(
        array_map('strval', array_keys($all['records'])) === array_map('strval', array_keys($full)),
        'same records in the same order: ' . $label
    );
    data_list_assert($all['records'] == $full, 'same record contents: ' . $label);

    $page = data_query($resource, $options + ['limit' => 7, 'offset' => 5]);
    data_list_assert($page['total'] === count($full), 'a page knows the total: ' . $label);
    data_list_assert(
        array_map('strval', array_keys($page['records'])) === array_map('strval', array_keys(array_slice($full, 5, 7, true))),
        'a page is the same cut of the list: ' . $label
    );
}

function data_list_record($i)
{
    $record = [
        'title' => ['Zebra', 'apple', 'Mango', 'banana', 'cherry'][$i % 5] . ' ' . ($i % 13),
        'published' => [1, '1', 0, true, '0', 1][$i % 6],
        'lang' => ['en', 'nl'][$i % 2],
        'type' => ['gamechanger', 'cluster', 'support'][$i % 3],
        'date' => date('Y-m-d', 1700000000 + (($i * 7919) % 400) * 86400),
        'amount' => [5, '12', 3.5, '100', 0, '7'][$i % 6],
    ];
    if ($i % 4 !== 0) {
        $record['order'] = (string)(($i * 31) % 17);
    }
    if ($i % 7 === 0) {
        $record['featured'] = 1;
    }
    if ($i % 9 === 0) {
        $record['name'] = ['en' => 'Name ' . ($i % 11), 'nl' => 'Naam ' . ($i % 5)];
    } elseif ($i % 9 !== 1) {
        $record['name'] = 'name ' . (($i * 5) % 23);
    }
    if ($i % 10 === 0) {
        $record['note'] = null;
    }
    return $record;
}

// The tags fleet sites use, on the fields of the fixture.
$queries = [
    [],
    ['filter' => 'published:1'],
    ['sort' => 'date|text|desc'],
    ['sort' => 'date|string|asc', 'filter' => 'published:1'],
    ['sort' => 'order|text|asc'],
    ['sort' => 'title|string|asc'],
    ['sort' => 'amount|numeric|asc', 'filter' => 'published:1'],
    ['sort' => 'amount|num|desc'],
    ['sort' => 'order|numeric|desc', 'filter' => 'lang:nl'],
    ['sort' => 'order|int|asc,date|text|desc'],
    ['sort' => 'name|text|desc,order|num|asc', 'filter' => 'type:!support'],
    ['sort' => 'published|text|asc'],
    ['sort' => 'order|string|asc,name|string|asc', 'filter' => 'published:1,type:gamechanger'],
    ['sort' => 'order|string|asc,name|string|asc', 'filter' => 'published:1,type:gamechanger,featured:1', 'fields' => 'uuid,name,type'],
    ['sort' => 'order|string|asc,name|string|asc', 'filter' => 'published:1,type:support,uuid:!r0042'],
    ['filter' => 'published:1,lang:nl', 'sort' => 'date|string|desc'],
    ['filter' => 'type:cluster||support'],
    ['filter' => 'order:(exists)'],
    ['filter' => 'amount:(num),note:(exists)'],
    ['filter' => 'type:!cluster', 'sort' => 'name|text|desc'],
    ['sort' => 'date,text,desc'],
    ['sort' => 'date|desc'],
    ['sort' => 'date,desc'],
    ['sort' => 'date|asc,type|text|asc', 'filter' => 'published:1'],
    ['sort' => 'amount|desc'],
    ['sort' => 'amount|asc', 'filter' => 'lang:nl'],
    ['sort' => 'order|desc'],
    ['sort' => 'title|desc', 'filter' => 'type:cluster'],
    ['sort' => 'date|unknown|desc'],
    ['sort' => 'missing_field|text|asc'],
    ['sort' => '_created|num|desc', 'filter' => 'lang:en'],
    ['filter' => 'published:1', 'fields' => 'uuid,date,published,title'],
];

function data_list_all_same($queries, $when)
{
    foreach (['flat', 'split'] as $resource) {
        foreach ($queries as $options) {
            data_list_same($resource, $options, $when);
        }
    }
}

mkdir($fixture . '/ext/data/flat', 0750, true);
mkdir($fixture . '/ext/data/split', 0750, true);
file_put_contents($fixture . '/ext/data/flat/.meta', '{"history": false}');
file_put_contents($fixture . '/ext/data/split/.meta', '{"history": false}');
// A record from before the resource was split stays where it is
data_create('split', 'abcd-old', data_list_record(900));
data_create('split', 'ab-old', data_list_record(901));
file_put_contents($fixture . '/ext/data/split/.meta', '{"history": false, "splitdir": true, "index": ["type"]}');

for ($i = 0; $i < 120; $i++) {
    data_create('flat', sprintf('r%04d', $i), data_list_record($i));
    data_create('split', md5('record-' . $i), data_list_record($i));
}
// Ids that are numbers, a short one, and one that sorts between the split folders
foreach (['9', '10', '100', 'ab', 'abc', 'ab.x1', 'Zed1'] as $i => $uuid) {
    data_create('flat', $uuid, data_list_record(200 + $i));
    data_create('split', $uuid, data_list_record(200 + $i));
}

data_list_all_same($queries, 'on the first build');
data_list_all_same($queries, 'from the list files');

$list_dir = $fixture . '/ext/data/.tmp/cache/_data/list';
data_list_assert(is_file($list_dir . '/split/date') && is_file($list_dir . '/flat/published'), 'a field asked for has a list file');
data_list_assert(!is_file($list_dir . '/split/title_slug'), 'a field nobody asked for has none');

// Writes add to the list files instead of removing them.
$inode = fileinode($list_dir . '/split/date');
data_create('split', md5('new-1'), data_list_record(301));
data_create('split', 'ab-new', data_list_record(302));
data_create('flat', 'r0050a', data_list_record(303));
data_create('flat', '11', data_list_record(304));
data_update('split', md5('record-3'), ['date' => '2031-01-01', 'published' => 0, 'order' => '1']);
data_update('flat', 'r0003', ['date' => '1999-01-01', 'type' => 'support', 'name' => 'aaa']);
data_update('flat', 'r0004', ['order' => null]);
data_delete('split', md5('record-5'));
data_delete('flat', 'r0005');
data_delete('split', 'abcd-old');
clearstatcache();
data_list_assert(fileinode($list_dir . '/split/date') === $inode, 'a write keeps the list file');
data_list_all_same($queries, 'after creates, updates and deletes');

$page = data_query('flat', ['filter' => 'date:1999-01-01']);
data_list_assert(array_keys($page['records']) === ['r0003'], 'an updated value is found');
$page = data_query('flat', ['sort' => 'date|text|asc', 'limit' => 1]);
data_list_assert(array_keys($page['records']) === ['r0003'] && $page['total'] === 128, 'the first page starts with the oldest');

// A rule of field and order sorts without a type: numbers as numbers.
$page = data_query('flat', ['sort' => 'date|desc', 'limit' => 2]);
$dates = array_column(data_read('flat'), 'date');
rsort($dates);
data_list_assert(array_column($page['records'], 'date') === array_slice($dates, 0, 2), 'field|desc starts with the newest');
$page = data_query('flat', ['sort' => 'amount|desc', 'limit' => 1]);
data_list_assert((string)current($page['records'])['amount'] === '100', 'field|desc puts 100 before 12 and 7');
$sorted = data_sort_param(data_read('flat'), 'amount,asc');
data_list_assert((string)current($sorted)['amount'] === '0', 'field,asc starts with the smallest number');

// Many writes: the file is written compact again and still right.
for ($round = 0; $round < 3; $round++) {
    for ($i = 10; $i < 110; $i++) {
        data_update('flat', sprintf('r%04d', $i), ['amount' => (string)($i * ($round + 2) % 50)]);
    }
}
data_list_all_same($queries, 'after many updates');
data_query('flat', ['sort' => 'amount|num|asc', 'limit' => 1]);
data_list_assert(count(file($list_dir . '/flat/amount')) < 200, 'a list file that grew is written compact');

// Search looks in every field and still pages.
$found = data_search(data_read('flat'), 'mango');
$page = data_query('flat', ['search' => 'mango', 'limit' => 3, 'offset' => 2]);
data_list_assert($page['total'] === count($found) && array_keys($page['records']) === array_keys(array_slice($found, 2, 3, true)), 'a search is cut to its page');

// The cache can go at any time.
data_list_assert(data_cache_clear() > 0 && !is_file($list_dir . '/flat/amount'), 'clearing the cache removes the list files');
data_list_all_same($queries, 'after the cache was cleared');

// A file built on another commit is built again: Git changed the records.
data_list_assert(is_file($list_dir . '/flat/date'), 'the list file is back');
$record = json_decode(file_get_contents($fixture . '/ext/data/flat/r0010'), true);
file_put_contents($fixture . '/ext/data/flat/r0010', json_encode(['date' => '1990-05-05'] + $record));
mkdir($fixture . '/ext/.git/refs/heads', 0750, true);
file_put_contents($fixture . '/ext/.git/HEAD', "ref: refs/heads/master\n");
file_put_contents($fixture . '/ext/.git/refs/heads/master', str_repeat('a', 40) . "\n");
$output = [];
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($fixture) . ' 2>&1', $output, $status);
data_list_assert($status === 0 && trim(implode("\n", $output)) === 'r0010', 'a pull makes the list build again: ' . implode(' ', $output));

// Emptying a resource empties its lists.
data_empty('flat');
$page = data_query('flat', ['sort' => 'date|text|asc', 'limit' => 5]);
data_list_assert($page['total'] === 0 && $page['records'] === [], 'an emptied resource lists nothing');
data_create('flat', 'again', data_list_record(1));
$page = data_query('flat', ['sort' => 'date|text|asc', 'limit' => 5]);
data_list_assert(array_keys($page['records']) === ['again'], 'and lists what is created after');

// The tag: a page and its total.
data_sc(['split', 'filter' => 'published:1', 'sort' => 'date|text|desc', 'limit' => '4', 'offset' => '2']);
$expected = array_slice(data_list_full('split', ['filter' => 'published:1', 'sort' => 'date|text|desc']), 2, 4, true);
data_list_assert(array_keys($GLOBALS['SYSTEM']['variables']['data.split']) === array_keys($expected), 'the tag gives the page');
data_list_assert($GLOBALS['SYSTEM']['variables']['data.split-total'] === count(data_list_full('split', ['filter' => 'published:1'])), 'the tag gives the total');

// A unique field is an index field, listed under index or not.
mkdir($fixture . '/ext/data/codes', 0750, true);
file_put_contents($fixture . '/ext/data/codes/.meta', '{"history": false, "splitdir": true, "unique": ["code"]}');
data_list_assert(data_create('codes', 'one', ['code' => 'A-1']) !== false, 'the first record of a unique resource is created');
for ($i = 2; $i < 30; $i++) {
    data_create('codes', md5('code-' . $i), ['code' => 'A-' . $i, 'label' => 'x']);
}
data_list_assert(is_file($fixture . '/ext/data/codes/.index/code/.unique'), 'a unique field gets an index');
data_list_assert(array_keys(data_read_index('codes', 'code', md5('A-5'))) === [md5('code-5')], 'with an entry for every record written');
data_list_assert(count(data_read('codes')) === 29, 'different values are all created');
data_list_assert(data_create('codes', 'copy', ['code' => 'A-7']) === false, 'a value another record has is refused on create');
data_list_assert(data_create('codes', 'spaced', ['code' => ' A-7 ']) === false, 'also with spaces around it');
data_list_assert(data_update('codes', 'one', ['code' => 'A-1', 'label' => 'kept']) !== false, 'a record keeps its own value');
data_list_assert(data_update('codes', 'one', ['code' => 'A-9']) === false, 'a value another record has is refused on update');
data_list_assert(data_update('codes', 'one', ['code' => 'B-1']) !== false, 'a new value is accepted');
data_list_assert(data_create('codes', 'reuse', ['code' => 'A-1']) !== false, 'the value it left is free');
data_delete('codes', md5('code-7'));
data_list_assert(data_create('codes', 'copy', ['code' => 'A-7']) !== false, 'the value of a deleted record is free');
// Records from before the field was unique are indexed the first time it is checked.
mkdir($fixture . '/ext/data/older/ab/cd', 0750, true);
file_put_contents($fixture . '/ext/data/older/.meta', '{"history": false, "splitdir": true, "unique": ["sku"]}');
file_put_contents($fixture . '/ext/data/older/ab/cd/abcd0001', '{"sku": "S-1"}');
file_put_contents($fixture . '/ext/data/older/ab/cd/abcd0002', '{"sku": "S-2"}');
file_put_contents($fixture . '/ext/data/older/plain', '{"sku": "S-3"}');
data_list_assert(data_create('older', 'new1', ['sku' => 'S-2']) === false, 'a value of an older record is refused');
data_list_assert(data_create('older', 'new2', ['sku' => 'S-3']) === false, 'also of one outside the split folders');
data_list_assert(data_create('older', 'new3', ['sku' => 'S-4']) !== false, 'a new value is accepted next to older records');
data_list_assert(data_update('older', 'abcd0001', ['sku' => 'S-1', 'note' => 'x']) !== false, 'an older record keeps its value');
data_list_assert(data_update('older', 'abcd0001', ['sku' => 'S-9']) !== false && data_create('older', 'new4', ['sku' => 'S-1']) !== false, 'and frees it when it changes');
data_cache_clear();
data_list_assert(data_create('older', 'new5', ['sku' => 'S-9']) === false && data_create('older', 'new6', ['sku' => 'S-2']) === false, 'the index does not depend on the cache');

// A field name that is no folder name is checked by reading the records.
mkdir($fixture . '/ext/data/odd', 0750, true);
file_put_contents($fixture . '/ext/data/odd/.meta', '{"history": false, "unique": ["../../../../../odd/escaped", "code"]}');
data_list_assert(data_create('odd', 'one', ['code' => 'C-1', '../../../../../odd/escaped' => 'v']) !== false, 'a unique field with a path for a name is accepted once');
data_list_assert(data_create('odd', 'two', ['code' => 'C-2', '../../../../../odd/escaped' => 'v']) === false, 'and refused the second time');
data_list_assert(
    !file_exists($fixture . '/ext/data/odd/escaped') && array_diff(@scandir($fixture . '/ext/data/odd/.index') ?: [], ['.', '..', 'code']) === [],
    'without a file or folder outside its place'
);
data_list_assert(data_query('odd', ['filter' => '../../../../../odd/escaped:v', 'limit' => 5])['total'] === 1 && !file_exists($fixture . '/ext/data/odd/escaped'), 'and a list on such a field reads the records too');
data_cache_clear();
data_list_assert(data_create('codes', 'late', ['code' => 'B-1']) === false, 'and after a cache clear the check still holds');

echo "PASS: data list\n";
