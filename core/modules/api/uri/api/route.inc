<?php

router_deny();
$parts = explode('/', $GLOBALS["SYSTEM"]["request_uri"]);
if ($parts < 1 || $parts[0] !==  "api") {
    return;
}

load_library('api');
load_library('json');
if (!api_access()) {
    json_result(array('message' => 'ACCESS_DENIED'), 403);
}
json_result(array('message' => 'RESOURCE_NOT_FOUND'), 404);
