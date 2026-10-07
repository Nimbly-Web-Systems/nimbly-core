<?php

$GLOBALS['SYSTEM'] = [
    'variables' => [],
];

function load_library($library)
{
}

function get_variable($key, $default = null)
{
    return $default;
}

function access_by_feature($feature)
{
    return permission_session_has($feature);
}

function honeypot_field_name()
{
    return 'website';
}

function generate_salt()
{
    return 'new-salt';
}

function encrypt($value, $salt)
{
    return 'hash-of-' . $value;
}

function json_input()
{
    return $GLOBALS['request_body'];
}

function sanitize_html_fields($meta, $data)
{
    return $data;
}

function data_meta($resource)
{
    return $GLOBALS['meta'][$resource] ?? [];
}

function data_modified($resource, $uuid = null)
{
    return 0;
}

function http_header_not_modified($modified)
{
}

function data_exists($resource, $uuid = null)
{
    return false;
}

function data_read($resource, $uuid = null)
{
    return $uuid === null ? $GLOBALS['stored'][$resource] : $GLOBALS['stored'][$resource][$uuid];
}

function data_create($resource, $uuid, $data)
{
    $GLOBALS['written'] = $data;
    return $data;
}

function data_update($resource, $uuid, $data)
{
    $GLOBALS['written'] = $data;
    if ((string)$uuid === '') {
        $result = [];
        foreach ($data as $id => $updates) {
            $result[$id] = $updates + $GLOBALS['stored'][$resource][$id];
        }
        return $result;
    }
    return $data + $GLOBALS['stored'][$resource][$uuid];
}

function data_path_valid($resource, $uuid = null)
{
    return true;
}

function data_error_get()
{
    return '';
}

function json_result($data, $code = 200, $modified = null)
{
    return [
        'data' => $data,
        'code' => $code,
    ];
}

if (!function_exists('getallheaders')) {
    function getallheaders()
    {
        return [];
    }
}

require_once dirname(__DIR__) . '/lib/request-input.php';
require_once dirname(__DIR__) . '/modules/user/lib/permissions.php';
require_once dirname(__DIR__) . '/modules/api/lib/api.php';

function api_hidden_fields_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** The records in an API answer, keyed by id. */
function api_hidden_fields_request(string $method, string $resource, ?string $uuid = null, array $body = [], array $features = ['(all)' => true])
{
    $_SESSION = ['features' => $features];
    $_SERVER['REQUEST_METHOD'] = $method;
    $GLOBALS['request_body'] = $body;
    $GLOBALS['written'] = null;
    $result = api_method_switch($uuid === null ? 'resource' : 'resource_id', $resource, $uuid);
    api_hidden_fields_assert(in_array($result['code'], [200, 201], true), "{$method} {$resource} answered {$result['code']}");
    return $result['data'][$resource];
}

function api_hidden_fields_names(array $record)
{
    $names = array_keys($record);
    sort($names);
    return $names;
}

$secret = ['password', 'salt', 'api', 'password_reset_token', 'change_email_token'];
$user = [
    'uuid' => 'u1',
    'email' => 'a@example.com',
    'name' => 'A',
    'roles' => 'editor',
    'password' => 'hash',
    'salt' => 'salt',
    'api' => ['token' => 't', 'access' => true, 'expires' => 9999999999],
    'password_reset_token' => 'r',
    'change_email_token' => 'c',
];
$GLOBALS['stored'] = [
    'users' => ['u1' => $user, 'u2' => ['uuid' => 'u2'] + $user],
    'members' => ['m1' => ['uuid' => 'm1', 'name' => 'M', 'stripe_id' => 'cus_1', 'pin' => 'hash', 'note' => 'n']],
    'pages' => ['p1' => ['uuid' => 'p1', 'title' => 'T', 'salt' => 'not a secret here', 'api' => 'text']],
];
$GLOBALS['meta'] = [
    'users' => ['encrypt' => 'password'],
    'members' => ['hidden' => 'stripe_id, note', 'encrypt' => 'pin'],
];
$visible_user = ['email', 'name', 'roles', 'uuid'];

foreach ([['(all)' => true], ['view-users' => true, 'edit-users' => true, 'create-users' => true]] as $features) {
    $list = api_hidden_fields_request('GET', 'users', null, [], $features);
    api_hidden_fields_assert(count($list) === 2, 'user list lost records');
    foreach ($list as $record) {
        api_hidden_fields_assert(api_hidden_fields_names($record) === $visible_user, 'user list returned a hidden field');
    }
    api_hidden_fields_assert(
        api_hidden_fields_names(api_hidden_fields_request('GET', 'users', 'u1', [], $features)['u1']) === $visible_user,
        'single user returned a hidden field'
    );

    $answer = api_hidden_fields_request('PUT', 'users', 'u1', ['name' => 'B', 'password' => 'typed-password'], $features)['u1'];
    api_hidden_fields_assert(api_hidden_fields_names($answer) === $visible_user && $answer['name'] === 'B', 'user update answer returned a hidden field');
    api_hidden_fields_assert(
        $GLOBALS['written']['password'] === 'hash-of-typed' && $GLOBALS['written']['salt'] === 'new-salt',
        'hidden fields were no longer saved'
    );

    $answer = api_hidden_fields_request('PUT', 'users', null, ['u1' => ['name' => 'C'], 'u2' => ['name' => 'D']], $features);
    api_hidden_fields_assert(
        api_hidden_fields_names($answer['u1']) === $visible_user && api_hidden_fields_names($answer['u2']) === $visible_user,
        'bulk user update answer returned a hidden field'
    );

    $new = ['uuid' => 'u3', 'email' => 'n@example.com', 'name' => 'N', 'roles' => 'editor', 'salt' => 's', 'api' => ['token' => 'x']];
    api_hidden_fields_assert(
        api_hidden_fields_names(api_hidden_fields_request('POST', 'users', null, $new, $features)['u3']) === $visible_user,
        'user create answer returned a hidden field'
    );
    api_hidden_fields_assert($GLOBALS['written']['salt'] === 's', 'create no longer stored a submitted field');
    api_hidden_fields_assert(
        api_hidden_fields_names(api_hidden_fields_request('POST', 'users', 'u4', $new, $features)['u4']) === $visible_user,
        'user create-with-id answer returned a hidden field'
    );
}

// The built-in users list does not depend on what a site's users .meta says.
$GLOBALS['meta']['users'] = [];
api_hidden_fields_assert(
    api_hidden_fields_names(api_hidden_fields_request('GET', 'users', 'u1')['u1']) === $visible_user,
    'users without .meta options returned a hidden field'
);

$GLOBALS['meta']['users'] = ['encrypt' => 'password', 'hidden' => 'roles'];
api_hidden_fields_assert(
    api_hidden_fields_names(api_hidden_fields_request('GET', 'users', 'u1')['u1']) === ['email', 'name', 'uuid'],
    'users .meta hidden list was not added to the built-in list'
);

api_hidden_fields_assert(
    api_hidden_fields_names(api_hidden_fields_request('GET', 'members')['m1']) === ['name', 'uuid'],
    '.meta hidden and encrypt fields were returned in a list'
);
api_hidden_fields_assert(
    api_hidden_fields_names(api_hidden_fields_request('GET', 'members', 'm1')['m1']) === ['name', 'uuid'],
    '.meta hidden and encrypt fields were returned for one record'
);
api_hidden_fields_assert(
    api_hidden_fields_names(api_hide_fields_all('members', $GLOBALS['stored']['members'])['m1']) === ['name', 'uuid'],
    'export records kept a hidden field'
);

api_hidden_fields_assert(
    api_hidden_fields_request('GET', 'pages') === $GLOBALS['stored']['pages'],
    'a resource without hidden fields changed in a list'
);
api_hidden_fields_assert(
    api_hidden_fields_request('GET', 'pages', 'p1')['p1'] === $GLOBALS['stored']['pages']['p1'],
    'a resource without hidden fields changed for one record'
);
api_hidden_fields_assert(api_hide_fields('pages', null) === null, 'a missing record no longer reads as null');

echo "API hidden fields tests passed.\n";
