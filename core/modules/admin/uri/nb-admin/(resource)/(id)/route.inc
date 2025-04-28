<?php

router_deny();

$parts = $GLOBALS['SYSTEM']['uri_parts'];
if (count($parts) !== 3 || $parts[0] !== 'nb-admin') {
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

// decrypt 2-way encrypted fields
if (!empty($meta['encrypt2way'])) {
	load_library('encrypt');
	$fs = explode(',', $meta['encrypt2way']);
	foreach ($fs as $f) {
		$record[$f] = decrypt_2way($record[$f], $record['salt']);
	}
}

if (empty($meta['fields'])) {
	//build it dynamically from the record
	$meta['fields'] = [];
	foreach ($record as $key => $value) {
		if ($key === 'uuid' || $key[0] === '_') {
			continue;
		}
		if (!is_scalar($value)) {
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

if (isset($meta['languages']) && !isset($meta['translations'])) {
	$meta['translations'] = ['languages' => $meta['languages']];
	$record['lang'] = $record['lang'] ?? current($meta['languages']);
	foreach ($meta['languages'] as $lang) {
		foreach ($meta['fields'] as $fk => $field) {
			if (empty($field['i18n'])) {
				continue;
			}
			if (!is_array($record[$fk])) {
				$record[$fk] = [
					$lang => $record[$fk] ?? ''
				];
				continue;
			} 
			if (!isset($record[$fk][$lang])) {
				$record[$fk][$lang] = '';
			} else {
				$record['translations'][$lang] = true;
			}
		}		
	}
	set_variable('translation_mode', 'field');
} else {
	set_variable('translation_mode', 'record');
}

if (isset($meta['translations'])) { //deprecated way
	$languages = $meta['translations']['languages'];
	$lang = $record['lang'];
	$ix = array_search($lang, $languages);
	if ($ix !== false) {
		unset($languages[$ix]);
	}
	set_variable('languages', $languages);
	set_variable('has_translations', true);
} 

set_variable_dot('record', $record);
set_variable_dot('translations', $record['translations'] ?? '');
load_library("access", "user");
load_library("get-user-resources", "admin");
if (access_by_feature('manage-content') && get_user_resources_access($resource)) {
	$_SESSION['features']['manage-' . $resource] = true;
}

router_accept();
