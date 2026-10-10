<?php

// A search looks inside a field that holds a list of plain values or one value
// in several languages.

$fixture = sys_get_temp_dir() . '/nimbly-data-search-' . bin2hex(random_bytes(6));

$GLOBALS['SYSTEM'] = [
    'file_base' => $fixture . '/',
    'env_paths' => ['ext', 'core'],
    'variables' => [],
    'request_time' => time(),
];

function load_library($library)
{
    if (in_array($library, ['data', 'util', 'event', 'data-sort'], true)) {
        require_once dirname(__DIR__) . '/lib/' . $library . '.php';
    }
}

function detect_language_sc()
{
    return 'en';
}

require_once dirname(__DIR__) . '/lib/data.php';

function data_search_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

set_error_handler(function ($severity, $message) {
    fwrite(STDERR, "FAIL: a search raises no warning: {$message}\n");
    exit(1);
});

$records = [
    'plain' => ['title' => 'Kangaroo court', 'body' => 'nothing here'],
    'tagged' => ['title' => 'x', 'tags' => ['alpha', 'quokka']],
    'translated' => ['title' => ['en' => 'Wombat', 'nl' => 'Buidelbeer']],
    'other' => ['title' => 'y', 'tags' => ['beta'], 'count' => 3],
];

data_search_assert(array_keys(data_search($records, 'kangaroo')) === ['plain'], 'a plain value is found');
data_search_assert(array_keys(data_search($records, 'quokka')) === ['tagged'], 'a value in a list is found');
data_search_assert(array_keys(data_search($records, 'wombat')) === ['translated'], 'a value in one language is found');
data_search_assert(data_search($records, 'platypus') === [], 'nothing is found where nothing matches');

echo "PASS: data search\n";
