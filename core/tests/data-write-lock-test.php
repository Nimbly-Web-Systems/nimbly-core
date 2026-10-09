<?php

// Writes to one resource happen one at a time: processes that each change
// another field of the same record lose nothing, a process may write again
// while it holds the lock, and lifecycle handlers run after the lock is given
// back.

$worker = ($argv[1] ?? '') === 'worker';
$fixture = $worker ? $argv[2] : sys_get_temp_dir() . '/nimbly-data-lock-' . bin2hex(random_bytes(6));

$GLOBALS['SYSTEM'] = [
    // data.php derives the data directory from file_base
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

if ($worker) {
    for ($round = 1; $round <= (int)$argv[4]; $round++) {
        if (!is_array(data_update('notes', 'n1', ['w' . $argv[3] => $round]))) {
            exit(1);
        }
    }
    exit(0);
}

function data_lock_assert($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function data_lock_remove($path)
{
    if (is_dir($path) && !is_link($path)) {
        foreach (array_diff(scandir($path), ['.', '..']) as $entry) {
            data_lock_remove($path . '/' . $entry);
        }
        rmdir($path);
        return;
    }
    unlink($path);
}

$workers = 6;
$rounds = 150;

mkdir($fixture . '/ext/data', 0750, true);
mkdir($fixture . '/ext/modules/notes/lib', 0750, true);
file_put_contents($fixture . '/ext/modules/notes/lib/note-changed.php', <<<'PHP'
<?php
function note_changed($payload)
{
    $GLOBALS['note_events'][] = [$payload['uuid'], !empty($GLOBALS['SYSTEM']['data_locks'])];
    if ($payload['uuid'] === 'n2') {
        data_update('tasks', 'n3', ['seen' => 'n2']);
    }
}
PHP);

try {
    data_create('notes', '.meta', ['fields' => []]);
    data_create('notes', 'n1', ['title' => 'first']);

    $processes = [];
    for ($i = 0; $i < $workers; $i++) {
        $processes[] = proc_open(
            [PHP_BINARY, __FILE__, 'worker', $fixture, (string)$i, (string)$rounds],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );
    }
    foreach ($processes as $process) {
        data_lock_assert(proc_close($process) === 0, 'A worker could not save its update');
    }

    $record = data_read('notes', 'n1');
    data_lock_assert(($record['title'] ?? '') === 'first', 'The record lost a field it had before the updates');
    for ($i = 0; $i < $workers; $i++) {
        data_lock_assert(
            ($record['w' . $i] ?? null) === $rounds,
            'An update of one process was overwritten by another'
        );
    }

    data_lock_assert(is_file($fixture . '/ext/data/.state/.locks/notes.lock'), 'The lock file is not under .state');
    data_lock_assert(
        array_values(array_diff(scandir($fixture . '/ext/data/notes'), ['.', '..', '.meta', 'n1'])) === [],
        'The resource folder holds more than its records'
    );

    // A resource that still names the old option saves as before
    data_create('pages', '.meta', ['fields' => [], 'write_lock' => true]);
    data_lock_assert(data_create('pages', 'p1', ['title' => 'page']) === true, 'A resource with write_lock no longer saves');
    data_lock_assert(is_array(data_update('pages', 'p1', ['title' => 'changed'])), 'A resource with write_lock no longer updates');

    // Handlers run after the lock is given back, and may write the resource themselves
    $on = ['note-changed'];
    data_create('tasks', '.meta', ['fields' => [], 'events' => ['create' => $on, 'update' => $on, 'delete' => $on]]);
    data_create('tasks', 'n3', ['title' => 'third']);
    data_create('tasks', 'n2', ['title' => 'second']);
    data_update('tasks', 'n1x', ['title' => 'missing']);
    data_update('tasks', 'n3', ['title' => 'again']);
    data_lock_assert(data_delete('tasks', 'n2') === 1, 'A record could not be deleted');

    $events = $GLOBALS['note_events'] ?? [];
    data_lock_assert(array_column($events, 0) === ['n3', 'n2', 'n3', 'n3', 'n2', 'n3'], 'Lifecycle events did not all run, or not in order');
    data_lock_assert(!in_array(true, array_column($events, 1), true), 'A lifecycle handler ran while the lock was held');
    data_lock_assert(data_read('tasks', 'n3', 'seen') === 'n2', 'A handler could not write its own resource');
    data_lock_assert(empty($GLOBALS['SYSTEM']['data_locks']), 'A lock is still held after the writes');

    // A lock file that cannot be opened is reported once and does not stop the write
    if (function_exists('posix_geteuid') && posix_geteuid() !== 0) {
        chmod($fixture . '/ext/data/.state/.locks', 0500);
        $log = $fixture . '/error.log';
        ini_set('error_log', $log);
        data_lock_assert(data_create('letters', 'l1', ['title' => 'one']) === true, 'A write stopped because its lock file could not be opened');
        data_lock_assert(is_array(data_update('letters', 'l1', ['title' => 'two'])), 'An update stopped because its lock file could not be opened');
        chmod($fixture . '/ext/data/.state/.locks', 0750);
        data_lock_assert(
            substr_count((string)@file_get_contents($log), 'no write lock for letters') === 1,
            'A missing lock was not reported exactly once'
        );
    }

    echo "data write lock tests passed\n";
} finally {
    data_lock_remove($fixture);
}
