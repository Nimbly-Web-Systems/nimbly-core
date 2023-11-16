<?php

router_deny();

$parts = $GLOBALS['SYSTEM']['uri_parts'];
if (count($parts) !== 3 || $parts[0] !== 'admin') {
    return;
}
$resource = $parts[1];
$uuid = $parts[2];

load_library("data");
if (!data_exists($resource, $uuid)) {
    return;
}


load_library("set");
set_variable("data.uuid", $uuid);
set_variable("data.resource", $resource);
$meta = data_meta($resource);
if (!empty($meta['subkey'])) {
	$resource .= '/' . $uuid;
	$uuid = null;
	set_variable('subkey', $meta['subkey']);
	router_accept();
	return;
}


$record = data_read($resource, $uuid);

// store data and fields and render the page


if (empty($meta['fields'])) { 
	//build it dynamically from the record
	$meta['fields'] = [];
	foreach ($record as $key => $value) {
		if ($key === 'uuid' || $key[0] === '_') {
			continue;
		}
		$meta['fields'][$key] = [
			"name" => $key,
			"type" => "text"
		];
	}
} 
set_variable("data.fields", $meta['fields']);
set_variables("data.field.", $meta['fields']);
set_variable_dot('record', $record);
load_library("access", "user");
load_library("get-user-resources", "admin");
if (access_by_feature('manage-content') && get_user_resources_access($resource)) {
	$_SESSION['features']['manage-' . $resource] = true;
}
router_accept();