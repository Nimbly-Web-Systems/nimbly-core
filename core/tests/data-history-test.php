<?php

// Every change to a record of a resource with history is kept: the record as
// it was, who changed it, when and in which agent run. A kept version can be
// put back, a deleted record can be brought back, and old changes are pruned.

$fixture = sys_get_temp_dir() . '/nimbly-data-history-' . bin2hex(random_bytes(6));

$GLOBALS['SYSTEM'] = [
    'file_base' => $fixture . '/',
    'env_paths' => ['ext', 'core'],
    'variables' => [],
    'request_time' => time(),
];

function load_library($library)
{
    if (in_array($library, ['data', 'util', 'event', 'history'], true)) {
        require_once dirname(__DIR__) . '/lib/' . $library . '.php';
    }
}

function username_get()
{
    return $GLOBALS['history_test_user'] ?? 'tester';
}

require_once dirname(__DIR__) . '/lib/data.php';
require_once dirname(__DIR__) . '/lib/history.php';

function history_test_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function history_test_remove($path)
{
    if (is_dir($path) && !is_link($path)) {
        foreach (array_diff(scandir($path), ['.', '..']) as $entry) {
            history_test_remove($path . '/' . $entry);
        }
        rmdir($path);
        return;
    }
    unlink($path);
}

mkdir($fixture . '/ext/data', 0750, true);
register_shutdown_function('history_test_remove', $fixture);

data_create('articles', '.meta', ['fields' => ['title' => [], 'tag' => []], 'index' => ['tag']]);
data_create('.jobs', '.meta', ['fields' => []]);
data_create('quiet', '.meta', ['history' => false]);
data_create('.loud', '.meta', ['history' => true]);
data_create('users', '.meta', ['fields' => []]);

// Which resources keep history
history_test_assert(history_enabled('articles') && history_enabled('.navigation') && history_enabled('.loud'), 'content resources keep history');
history_test_assert(!history_enabled('.jobs') && !history_enabled('quiet') && !history_enabled('users'), 'machine state, opted-out resources and users keep none');

// Create, update, delete
data_create('articles', 'a1', ['title' => 'One', 'tag' => 'red']);
$changes = history_list('articles', 'a1');
history_test_assert(count($changes) === 1 && $changes[0]['action'] === 'create' && $changes[0]['record'] === null, 'a create is kept without a record');
history_test_assert($changes[0]['by'] === 'tester' && $changes[0]['run'] === '', 'a change names who made it');

data_update('articles', 'a1', ['title' => 'Two']);
$changes = history_list('articles', 'a1');
history_test_assert(count($changes) === 2 && $changes[0]['action'] === 'update', 'an update is kept');
history_test_assert($changes[0]['record']['title'] === 'One' && $changes[0]['changed'] === ['title'], 'with the record as it was and the fields that changed');

data_update('articles', 'a1', ['title' => 'Two']);
history_test_assert(count(history_list('articles', 'a1')) === 2, 'saving the same values keeps nothing');

data_update('articles', 'a1', ['phone' => '0612345678']);
data_update('articles', 'a1', ['phone' => '612345678']);
$changes = history_list('articles', 'a1');
history_test_assert(count($changes) === 4 && $changes[0]['changed'] === ['phone'], 'values that are equal only as numbers are a change');
history_restore('articles', 'a1', $changes[1]['id']);
array_map('unlink', array_slice(glob(history_path('articles', 'a1') . '/*'), 2));

// What the site writes by itself is not kept
$GLOBALS['history_test_user'] = 'anonymous';
data_update('articles', 'a1', ['title' => 'By a scheduled task']);
data_update('articles', 'a1', ['title' => 'Two']);
unset($GLOBALS['history_test_user']);
history_test_assert(count(history_list('articles', 'a1')) === 2, 'a write without a user or an agent keeps nothing');

// An agent's change carries the asker and the run
$GLOBALS['SYSTEM']['data_actor'] = ['username' => 'asker', 'run' => 'abcdef0123456789'];
$saved = data_update('articles', 'a1', ['title' => 'Three', 'tag' => 'blue', 'extra' => 'x']);
unset($GLOBALS['SYSTEM']['data_actor']);
$changes = history_list('articles', 'a1');
history_test_assert($changes[0]['by'] === 'asker' && $changes[0]['run'] === 'abcdef0123456789', 'an agent change names the asker and the run');
history_test_assert($saved['_modified_by'] === md5('asker'), 'the record names the asker too');
history_test_assert($changes[0]['changed'] === ['title', 'tag', 'extra'], 'all changed fields are named');
history_test_assert(array_keys(data_read_index('articles', 'tag', data_index_uuids('blue')[0])) === ['a1'], 'index follows the update');

// Restore: the whole record, not a merge
$restored = history_restore('articles', 'a1', $changes[0]['id']);
$record = data_read('articles', 'a1');
history_test_assert(is_array($restored) && $record['title'] === 'Two' && $record['tag'] === 'red', 'restore puts the old values back');
history_test_assert(!isset($record['extra']), 'a field added later is gone after a restore');
history_test_assert(data_read_index('articles', 'tag', data_index_uuids('blue')[0]) === []
    && array_keys(data_read_index('articles', 'tag', data_index_uuids('red')[0])) === ['a1'], 'indexes follow the restore');
$changes = history_list('articles', 'a1');
history_test_assert(count($changes) === 4 && $changes[0]['record']['title'] === 'Three', 'a restore is itself a kept change');
history_test_assert(history_restore('articles', 'a1', '../x') === false && history_restore('articles', 'a1', $changes[3]['id']) === false,
    'an unknown change or a create cannot be restored');

// Delete and bring back
history_test_assert(history_deleted('articles') === [], 'nothing deleted yet');
data_delete('articles', 'a1');
$deleted = history_deleted('articles');
history_test_assert(array_keys($deleted) === ['a1'] && $deleted['a1']['record']['title'] === 'Two', 'a deleted record is listed with its last version');
history_test_assert(is_array(history_restore('articles', 'a1', $deleted['a1']['id'])) && data_read('articles', 'a1')['title'] === 'Two', 'and can be brought back');
history_test_assert(history_deleted('articles') === [], 'a record that is back is no longer listed as deleted');

// Emptying a resource keeps every record
data_create('articles', 'a2', ['title' => 'Second']);
data_empty('articles');
history_test_assert(array_keys(history_deleted('articles')) == ['a1', 'a2'] || array_keys(history_deleted('articles')) == ['a2', 'a1'], 'emptying a resource keeps its records');

// The whole resource, newest first
$all = history_resource_list('articles');
history_test_assert(count($all) >= 6 && $all[0]['at'] >= $all[count($all) - 1]['at'], 'the changes of a resource are listed newest first');
history_test_assert(array_values(array_unique(array_column(array_filter($all, fn($change) => $change['deleted']), 'uuid'))) == ['a1', 'a2']
    || array_values(array_unique(array_column(array_filter($all, fn($change) => $change['deleted']), 'uuid'))) == ['a2', 'a1'],
    'with a mark on the change that deleted a record that is still gone');
history_test_assert(count(history_resource_list('articles', 2)) === 2, 'and no more than asked for');

// Removing for good
history_test_assert(history_forget('articles', 'a2') > 0 && array_keys(history_deleted('articles')) === ['a1'] && !is_dir(history_path('articles', 'a2')),
    'a deleted record can be removed for good');
history_test_assert(history_forget('articles', '../a1') === 0 && history_forget('articles', '') === 0, 'only one record at a time, by its uuid');
data_create('articles', 'a3', ['title' => 'Third']);
data_update('articles', 'a3', ['title' => 'Third, changed']);
data_create('articles', 'a4', ['title' => 'Fourth']);
data_delete('articles', 'a4');
history_test_assert(history_forget_deleted('articles') === 2 && history_deleted('articles') === [], 'all deleted records of a resource can be removed for good');
history_test_assert(count(history_list('articles', 'a3')) === 2, 'the history of records that exist stays');
history_test_assert(history_forget('articles', 'a3') === 2 && history_list('articles', 'a3') === [] && data_read('articles', 'a3')['title'] === 'Third, changed',
    'clearing the history of a record leaves the record');
history_restore('articles', 'a1', 'x');
data_create('articles', 'a1', ['title' => 'Two']);

// No history where it is off
data_create('quiet', 'q1', ['title' => 'x']);
data_update('quiet', 'q1', ['title' => 'y']);
data_create('.jobs', 'j1', ['status' => 'new']);
data_delete('.jobs', 'j1');
history_test_assert(!is_dir(history_path('quiet')) && !is_dir(history_path('.jobs')), 'resources without history keep nothing');

// The history folder is no record of .state and is never a web path of its own
history_test_assert(strpos(history_path('articles'), '/ext/data/.state/.history/articles') !== false, 'history lives under .state');
history_test_assert(!in_array('.history', data_list('.state'), true), 'and is not listed as a record');

// Prune by age
$dir = history_path('articles', 'a1');
$old = $dir . '/' . sprintf('%017.6F', time() - 100 * 86400) . '-aaaaaa';
file_put_contents($old, json_encode(['action' => 'update', 'at' => time() - 100 * 86400, 'by' => 'tester', 'run' => '', 'record' => ['title' => 'Old']]));
$count = count(history_list('articles', 'a1'));
history_test_assert(history_prune(90, true) === 1 && is_file($old), 'a dry run counts and removes nothing');
history_test_assert(history_prune(90) === 1 && !is_file($old) && count(history_list('articles', 'a1')) === $count - 1, 'changes older than the limit are removed, newer ones stay');
history_test_assert(history_prune(-1) > 0 && !is_dir(history_path('articles')), 'emptied folders are removed');

echo "data history tests passed\n";
