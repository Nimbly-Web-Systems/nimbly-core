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
$access_feature = sprintf('create-%1$s,manage-%1$s,api_post_%1$s_import_document,api_post_%1$s,api_(any)_%1$s,api_post_(any),api_(any)', $resource);
if (!api_access($access_feature, $resource)) {
    return json_result(array('message' => 'ACCESS_DENIED', 'needs' => $access_feature), 403);
}

load_library('openai-import-document');

return openai_import_document($resource);
