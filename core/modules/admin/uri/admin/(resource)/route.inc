<?php

router_deny();

$parts = $GLOBALS['SYSTEM']['uri_parts'];
if (count($parts) !== 2 || $parts[0] !== 'admin') {
    return;
}
$resource = $parts[1];
load_library("data");
if (!data_exists($resource)) {
    return;
}

$items = data_read($resource);
if (empty ($items)) {
    $items = null;
}

$meta = data_meta($resource, $items);
if (!empty($items) && isset($meta['sort'])) {
    $items = data_sort_meta($items, $meta['sort']);
}

if (empty($items) && isset($meta['subkey'])) {
	$items = data_read_subkeys($resource, $meta['subkey']);
	set_variable('subkey', $meta['subkey']);
}

// store data and fields and render the page
load_library("set");
set_variable('_order', $meta['order'] ?? false);	
set_variable("data.resource", $resource);
set_variable("data.records", $items);
$meta['fields'] = data_exclude($meta['fields'], 'admin_col', false);
unset($meta['fields']['_created_by']);
unset($meta['fields']['_created']);
unset($meta['fields']['_modified']);
set_variable("data.fields", $meta['fields']);
load_library("access", "user");
load_library("get-user-resources", "admin");
if (access_by_feature('manage-content') && get_user_resources_access($resource)) {
	$_SESSION['features']['manage-' . $resource] = true;
}
load_module("admin");
router_accept();
