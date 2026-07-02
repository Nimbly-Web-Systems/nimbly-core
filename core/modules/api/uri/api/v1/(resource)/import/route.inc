<?php

router_deny();

$parts = router_match(__FILE__);
if ($parts === false || count($parts) !== 1) {
    return;
}

$resource = $parts[0];

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    return;
}

load_library('api');
$access_feature = sprintf('import-%1$s,manage-%1$s,api_post_%1$s_import,api_post_%1$s,api_(any)_%1$s,api_post_(any),api_(any)', $resource);
if (!api_access($access_feature, $resource)) {
    return json_result(array('message' => 'ACCESS_DENIED', 'needs' => $access_feature), 403);
}

load_library('api_import_resource');

return api_import_resource($resource);
