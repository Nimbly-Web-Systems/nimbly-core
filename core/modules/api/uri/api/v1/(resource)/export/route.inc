<?php

router_deny();

$parts = router_match(__FILE__);
if ($parts === false || count($parts) !== 1) {
    return;
}

$resource = $parts[0];

if (!data_exists($resource)) {
    return;
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    return;
}

load_library('api');
$access_feature = _api_access_str('get', $resource, null) . sprintf(',export-%1$s,manage-%1$s,(any)_%1$s', $resource);
if (!api_access($access_feature, $resource)) {
    return json_result(array('message' => 'ACCESS_DENIED', 'needs' => $access_feature), 403);
}

load_library('api_export_resource');

return api_export_resource($resource);
