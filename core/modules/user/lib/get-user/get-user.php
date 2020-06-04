<?php

function get_user_sc($params) {
	$uuid = get_param_value($params, 'uuid', false);
	$user = get_user($uuid);
	load_library('set');
	$var = get_param_value($params, 'var', 'user');
	set_variable_dot($var, $user);
}

function get_user($uuid=false) {
	static $users = [];
	if (!empty($users[$uuid])) {
		return $users[$uuid];
	}
	load_library('session', 'user');
	if (empty($uuid) && session_resume() && !empty($_SESSION['userid'])) {
		$uuid = $_SESSION['userid'];
	}
	if (empty($uuid)) {
		return false;
	}
	load_library('data', 'data');
	$users[$uuid] = data_read("users", $uuid);
	return $users[$uuid];
}