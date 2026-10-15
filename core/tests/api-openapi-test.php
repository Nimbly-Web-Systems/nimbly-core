<?php

$GLOBALS['SYSTEM'] = ['variables' => [], 'file_base' => sys_get_temp_dir() . '/'];

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

function data_meta($resource)
{
    return $GLOBALS['meta'][$resource] ?? [];
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
require_once dirname(__DIR__) . '/modules/api/lib/api-openapi.php';

function api_openapi_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$GLOBALS['meta'] = [
    'projects' => ['hidden' => 'notes', 'fields' => [
        'title' => ['type' => 'text', 'required' => true],
        'notes' => ['type' => 'textarea'],
        'sector' => ['type' => 'select', 'options' => ['culture' => 'Culture']],
    ]],
    'users' => ['fields' => ['email' => ['type' => 'email'], 'password' => ['type' => 'password']]],
];

$_SESSION = ['features' => ['view-projects' => true]];
api_openapi_assert(api_openapi_operations('projects') === ['get'], 'a reader may only get');
api_openapi_assert(api_openapi_operations('users') === [], 'a resource without permission is described');

$projects = api_openapi_record_schema('projects')['properties'];
api_openapi_assert(!isset($projects['notes']), 'a hidden field is described');
api_openapi_assert(($projects['sector']['enum'] ?? null) === ['culture'], 'select options are not an enum');

$users = api_openapi_record_schema('users')['properties'];
api_openapi_assert(isset($users['email']) && !isset($users['password']) && !isset($users['api']), 'user secrets are described');

echo "API OpenAPI tests passed.\n";
