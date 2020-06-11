<?php

$parts = $GLOBALS['SYSTEM']['uri_parts'];
if (count($parts) !== 4 || $parts[0] !== 'api' || $parts[1] !== 'v1') {
    return;
}

$resource = $parts[2];
if (!data_exists($resource)) {
    return;
}

$uuid = $parts[3];
$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST' && !data_exists($resource, $uuid)) {
    return;
}

load_library('api', 'api');

if (data_is_subkey($resource, $uuid)) {
	return api_method_switch_with_subkey("resource", $resource, $uuid, null);
}

return api_method_switch("resource_id", $resource, $uuid);