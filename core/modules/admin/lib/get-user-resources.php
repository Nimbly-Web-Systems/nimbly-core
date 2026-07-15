<?php

load_library("set");
load_library("data");
load_library("access");

function get_user_resources_sc($params) {
	$result = array();
    $rs = data_resources_list();
    foreach ($rs as $k => $v) {
		$meta = data_meta($k);
		$visible_by_default = !in_array($k, ['users', 'roles'], true);
		$visible = array_key_exists('nimblybar', $meta) ? $meta['nimblybar'] === true : $visible_by_default;
		if (!$visible) {
			continue;
		}
		if (get_user_resources_access($k)) {
			$name = $meta['name']['plural'] ?? ucwords(str_replace(['-', '_', '.'], [' ', ' ', ''], $k));
			$result[$k] = ['key' => $k, 'name' => $name, 'count' => count(data_list($k))];
		}
    }
    set_variable("data.user-resources", $result);
}

function get_user_resources_access($k) {
	if (access_by_feature('view-' . $k)) {
		return true;
	}
	return false;
}
