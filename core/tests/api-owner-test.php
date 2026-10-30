<?php

// Personal records: who may do what on a resource with an owner_field.

function load_library($library) {}
function data_meta($resource) { return []; }
function data_exists() { return false; }

require_once dirname(__DIR__) . '/modules/api/lib/api.php';

function api_owner_assert($condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$mine = ['user_id' => 'me'];
$theirs = ['user_id' => 'them'];
$check = fn($method, $uuid, $record, $owner = 'me', $import = false) =>
    api_owner_refusal($method, $uuid, 'user_id', $owner, $record, $import);

api_owner_assert($check('get', '', null) === null, 'A list is allowed; it is filtered');
api_owner_assert($check('post', '', null) === null, 'A new record is allowed');
api_owner_assert($check('get', 'a', $mine) === null, 'Own record is readable');
api_owner_assert($check('put', 'a', $mine) === null, 'Own record is editable');
api_owner_assert($check('get', 'a', $theirs) === ['RESOURCE_NOT_FOUND', 404], 'Someone else\'s record does not exist');
api_owner_assert($check('put', 'a', $theirs)[1] === 404, 'Someone else\'s record cannot be changed');
api_owner_assert($check('delete', 'a', $theirs)[1] === 404, 'Someone else\'s record cannot be deleted');
api_owner_assert($check('get', 'a', ['date' => 'x'])[1] === 404, 'A record without an owner is nobody\'s to read');
api_owner_assert($check('put', '', null)[1] === 403, 'No bulk update');
api_owner_assert($check('delete', '', null)[1] === 403, 'No bulk delete');
api_owner_assert($check('post', '', null, 'me', true)[1] === 403, 'No import');
api_owner_assert($check('get', '', null, '')[1] === 403, 'Nobody signed in, nothing to see');
api_owner_assert(api_owner_filter('', 'user_id', 'me') === 'user_id:me', 'The filter stands alone');
api_owner_assert(api_owner_filter('date:x', 'user_id', 'me') === 'user_id:me,date:x', 'The filter narrows what was asked');

echo "ok\n";
