<?php

router_deny();

$parts = router_match(__FILE__);
if ($parts === false || count($parts) !== 1) {
    return;
}

$resource = $parts[0];

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    return;
}

load_library('data');
if (!data_exists($resource)) {
    return;
}

load_library('api');
$access_feature = sprintf('edit-%1$s,manage-%1$s,api_put_%1$s_options,api_put_%1$s,api_(any)_%1$s,api_put_(any),api_(any)', $resource);
if (api_owner_field($resource) !== '') {
    return json_result(array('message' => 'ACCESS_DENIED'), 403);
}
if (!api_access($access_feature, $resource)) {
    return json_result(array('message' => 'ACCESS_DENIED', 'needs' => $access_feature), 403);
}

load_library('select-options');

return select_options_put($resource);
