<?php

// A cached collection is thrown away when a record in it is replaced, added or
// removed, by the data API or by another program such as Git, and when `.meta`
// is edited. The check looks at the folders, not at every record.

$fixture = sys_get_temp_dir() . '/nimbly-data-cache-' . bin2hex(random_bytes(6));

$GLOBALS['SYSTEM'] = [
    'file_base' => $fixture . '/',
    'env_paths' => ['ext', 'core'],
    'variables' => [],
    'request_time' => time(),
];

function load_library($library)
{
    if (in_array($library, ['data', 'util', 'event'], true)) {
        require_once dirname(__DIR__) . '/lib/' . $library . '.php';
    }
}

function username_get()
{
    return 'tester';
}

require_once dirname(__DIR__) . '/lib/data.php';

function data_cache_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function data_cache_remove($path)
{
    if (is_dir($path) && !is_link($path)) {
        foreach (array_diff(scandir($path), ['.', '..']) as $entry) {
            data_cache_remove($path . '/' . $entry);
        }
        rmdir($path);
        return;
    }
    unlink($path);
}

/** Everything written so far happened a while ago, the collection cache a little later. */
function data_cache_settle($fixture)
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fixture . '/ext/data', FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $path) {
        touch((string)$path, time() - (strpos((string)$path, '/.tmp/cache/') !== false ? 60 : 120));
    }
    clearstatcache();
}

/** The way Git and the data API change a file: a new one takes its place. */
function data_cache_replace($file, $contents)
{
    file_put_contents($file . '.new', $contents);
    rename($file . '.new', $file);
    clearstatcache();
}

function data_cache_titles($resource)
{
    $titles = array_column(data_read($resource, null, ['title']), 'title');
    sort($titles);
    return implode(',', $titles);
}

mkdir($fixture . '/ext/data/notes', 0750, true);
file_put_contents($fixture . '/ext/data/notes/.meta', '{}');
data_create('notes', 'n1', ['title' => 'one']);
data_create('notes', 'n2', ['title' => 'two']);

data_cache_assert(data_cache_titles('notes') === 'one,two', 'the collection is read');
data_cache_settle($fixture);
data_cache_assert(data_cache_titles('notes') === 'one,two', 'the collection is read again');

// Another program replaces a record.
$record = json_decode(file_get_contents($fixture . '/ext/data/notes/n1'), true);
data_cache_replace($fixture . '/ext/data/notes/n1', json_encode(['title' => 'uno'] + $record));
data_cache_assert(data_cache_titles('notes') === 'two,uno', 'a replaced record is seen');

// Another program adds and removes one.
data_cache_settle($fixture);
data_cache_titles('notes');
data_cache_replace($fixture . '/ext/data/notes/n3', json_encode(['title' => 'three', 'uuid' => 'n3']));
data_cache_assert(data_cache_titles('notes') === 'three,two,uno', 'an added record is seen');
data_cache_settle($fixture);
data_cache_titles('notes');
unlink($fixture . '/ext/data/notes/n3');
clearstatcache();
data_cache_assert(data_cache_titles('notes') === 'two,uno', 'a removed record is seen');

// The data API, within the second in which the cache was written.
data_cache_titles('notes');
data_update('notes', 'n2', ['title' => 'dos']);
data_cache_assert(data_cache_titles('notes') === 'dos,uno', 'an update in the same second is seen');
data_cache_titles('notes');
data_delete('notes', 'n2');
data_cache_assert(data_cache_titles('notes') === 'uno', 'a delete in the same second is seen');

// `.meta` is edited in place.
data_cache_settle($fixture);
data_cache_titles('notes');
$cache_files = glob($fixture . '/ext/data/.tmp/cache/_data/*');
file_put_contents($fixture . '/ext/data/notes/.meta', '{ }');
clearstatcache();
data_read('notes', null, ['title']);
clearstatcache();
data_cache_assert(filemtime($cache_files[0]) > time() - 30, 'an edited .meta renews the cache');

// Records in split folders.
mkdir($fixture . '/ext/data/logs', 0750, true);
file_put_contents($fixture . '/ext/data/logs/.meta', json_encode(['splitdir' => true]));
data_create('logs', 'abcd1', ['title' => 'first']);
data_create('logs', 'cdef2', ['title' => 'second']);
data_cache_assert(is_file($fixture . '/ext/data/logs/ab/cd/abcd1'), 'the record is in a split folder');
data_cache_assert(data_cache_titles('logs') === 'first,second', 'the split collection is read');
data_cache_settle($fixture);
data_cache_titles('logs');
$record = json_decode(file_get_contents($fixture . '/ext/data/logs/ab/cd/abcd1'), true);
data_cache_replace($fixture . '/ext/data/logs/ab/cd/abcd1', json_encode(['title' => 'eerste'] + $record));
data_cache_assert(data_cache_titles('logs') === 'eerste,second', 'a replaced record in a split folder is seen');

// The check does not open or look at the records themselves.
data_cache_settle($fixture);
touch($fixture . '/ext/data/logs/cd/ef/cdef2', time() + 3600);
clearstatcache();
data_cache_assert(data_modified('logs') < time(), 'the time of a collection comes from its folders');

data_cache_remove($fixture);
echo "data collection cache test passed\n";
