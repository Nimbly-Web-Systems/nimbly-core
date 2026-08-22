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

function data_delete($resource)
{
    $GLOBALS['deleted_resources'][] = $resource;
    return 2;
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

require_once dirname(__DIR__) . '/modules/user/lib/permissions.php';
require_once dirname(__DIR__) . '/modules/api/lib/api.php';

function api_resource_delete_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function api_resource_delete_request(array $features)
{
    $_SESSION = ['features' => $features];
    $_SERVER['REQUEST_METHOD'] = 'DELETE';
    $GLOBALS['deleted_resources'] = [];
    return api_method_switch('resource', 'profiles');
}

api_resource_delete_assert(
    _api_access_str('delete', 'profiles', null) === 'api_delete_resource_profiles',
    'collection deletion did not advertise the explicit destructive permission'
);
api_resource_delete_assert(
    str_contains(_api_access_str('delete', 'profiles', 'profile-1'), 'api_delete_profiles'),
    'record deletion lost its existing API permission'
);

$record_delete_result = api_resource_delete_request(['api_delete_profiles' => true]);
api_resource_delete_assert($record_delete_result['code'] === 403, 'record-delete permission removed a resource');
api_resource_delete_assert(
    $record_delete_result['data']['needs'] === 'api_delete_resource_profiles',
    'denied response did not name the destructive permission'
);
api_resource_delete_assert($GLOBALS['deleted_resources'] === [], 'denied request reached data deletion');

foreach (['api_(any)_profiles', 'api_(any)', 'manage-profiles', 'delete-profiles'] as $feature) {
    $result = api_resource_delete_request([$feature => true]);
    api_resource_delete_assert($result['code'] === 403, "{$feature} implied resource-destruction authority");
    api_resource_delete_assert($GLOBALS['deleted_resources'] === [], "{$feature} reached data deletion");
}

$explicit_result = api_resource_delete_request(['api_delete_resource_profiles' => true]);
api_resource_delete_assert($explicit_result['code'] === 200, 'explicit resource-delete permission was denied');
api_resource_delete_assert($GLOBALS['deleted_resources'] === ['profiles'], 'explicit permission did not delete the resource');

$admin_result = api_resource_delete_request(['(all)' => true]);
api_resource_delete_assert($admin_result['code'] === 200, '(all) administrator was denied');
api_resource_delete_assert($GLOBALS['deleted_resources'] === ['profiles'], '(all) did not delete the resource');

echo "API resource deletion permission tests passed.\n";
