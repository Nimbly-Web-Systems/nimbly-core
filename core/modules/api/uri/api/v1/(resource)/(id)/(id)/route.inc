<?php

router_deny();


$parts = $GLOBALS['SYSTEM']['uri_parts'];
if (count($parts) !== 5 || $parts[0] !== 'api' || $parts[1] !== 'v1') {
    return;
}

$resource = $parts[2];

if (!data_exists($resource)) {
    return;
}

$subkey = $parts[3];

if (!data_is_subkey($resource, $subkey)) {
	return;
}

$uuid = $parts[4];
load_library('api', 'api');
return api_method_switch_with_subkey("resource_id", $resource, $subkey, $uuid);