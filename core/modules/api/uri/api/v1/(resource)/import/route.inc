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
if ($method !== 'POST') {
    return;
}

load_library('api_import_resource', 'api');

return api_import_resource($resource);

