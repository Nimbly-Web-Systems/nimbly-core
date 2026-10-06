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

function json_input()
{
    return $GLOBALS['request_body'];
}

function data_meta($resource)
{
    return $GLOBALS['users_meta'];
}

function sanitize_html_fields($meta, $data)
{
    return $data;
}

function data_update($resource, $uuid, $data)
{
    $GLOBALS['updates'][] = [$resource, $uuid, $data];
    return $data;
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

function api_users_self_update_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** Fields that reach data_update for a PUT, or null when the request is refused. */
function api_users_self_update_request(array $features, string $resource, string $uuid, array $body, array $meta = [])
{
    $_SESSION = ['features' => $features];
    $_SERVER['REQUEST_METHOD'] = 'PUT';
    $GLOBALS['request_body'] = $body;
    $GLOBALS['users_meta'] = $meta;
    $GLOBALS['updates'] = [];
    $result = api_method_switch('resource_id', $resource, $uuid);
    if ($result['code'] !== 200) {
        return null;
    }
    $fields = array_keys($GLOBALS['updates'][0][2]);
    sort($fields);
    return $fields;
}

$own = ['api_put_users_u1' => true, 'view-pages' => true];
$body = [
    'name' => 'New name',
    'email' => 'other@example.com',
    'password' => 'secret',
    'roles' => 'admin',
    'features' => 'manage-users',
    'api' => ['token' => 't', 'access' => true, 'expires' => 9999999999],
    'membership_expires' => '2099-01-01',
];
$all = array_keys($body);
$all[] = 'uuid';
sort($all);

api_users_self_update_assert(
    api_users_self_update_request($own, 'users', 'u1', $body) === ['name', 'uuid'],
    'own-record save kept more than the name'
);
api_users_self_update_assert(
    api_users_self_update_request($own, 'users', 'u1', ['roles' => 'admin']) === ['uuid'],
    'own-record save with only other fields changed the record'
);
api_users_self_update_assert(
    api_users_self_update_request($own, 'users', 'u1', $body, ['self_edit' => 'name, phone']) === ['name', 'uuid'],
    'self_edit let through a field it does not list'
);
api_users_self_update_assert(
    api_users_self_update_request($own, 'users', 'u1', ['phone' => '1', 'roles' => 'admin'], ['self_edit' => 'name, phone']) === ['phone', 'uuid'],
    'self_edit did not allow a listed field'
);
api_users_self_update_assert(
    api_users_self_update_request($own, 'users', 'u2', $body) === null,
    'a user saved another user\'s record'
);

foreach (['(all)', 'edit-users', 'manage-users', 'api_put_users', 'api_(any)_users', 'api_put_(any)', 'api_(any)'] as $feature) {
    api_users_self_update_assert(
        api_users_self_update_request($own + [$feature => true], 'users', 'u1', $body) === $all,
        "{$feature} lost fields on a user record"
    );
    api_users_self_update_assert(
        api_users_self_update_request([$feature => true], 'users', 'u2', $body) === $all,
        "{$feature} lost fields on another user's record"
    );
}

api_users_self_update_assert(
    api_users_self_update_request(['edit-profiles' => true], 'profiles', 'p1', $body) === $all,
    'another resource lost fields'
);

echo "API users self-update tests passed.\n";
