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

	load_library('session');
	if (empty($uuid) && session_resume() && !empty($_SESSION['username'])) {
		if (!empty($_SESSION['user_uuid'])) {
			$uuid = $_SESSION['user_uuid'];
		} else {
			$user = find_user_by_email($_SESSION['username']);
			if (!empty($user['uuid'])) {
				$_SESSION['user_uuid'] = $user['uuid'];
				return $user;
			}
			$uuid = md5($_SESSION['username']);
		}
	}
	if (empty($uuid)) {
		return false;
	}
	if (!empty($users[$uuid])) {
		return $users[$uuid];
	}
	load_library('data');
	$users[$uuid] = data_read("users", $uuid);
	return $users[$uuid];
}

function get_user_by_email($email) {
	return find_user_by_email($email);
}

function find_user_by_email($email) {
	static $users_by_email = [];

	$email = trim((string)$email);
	if ($email === '') {
		return false;
	}

	$cache_key = strtolower($email);
	if (array_key_exists($cache_key, $users_by_email)) {
		return $users_by_email[$cache_key];
	}

	load_library('data');
	load_library('util');

	foreach (array_unique([$email, strtolower($email)]) as $candidate) {
		$matches = data_read_index('users', 'email', md5_uuid($candidate));
		foreach ($matches as $user) {
			if (!empty($user['email']) && strcasecmp(trim((string)$user['email']), $email) === 0) {
				$users_by_email[$cache_key] = $user;
				return $user;
			}
		}
	}

	foreach (array_unique([$email, strtolower($email)]) as $candidate) {
		$uuid = md5($candidate);
		if (!data_exists('users', $uuid)) {
			continue;
		}

		$user = data_read('users', $uuid);
		if (!empty($user['email']) && strcasecmp(trim((string)$user['email']), $email) === 0) {
			$users_by_email[$cache_key] = $user;
			return $user;
		}
	}

	foreach (data_read('users') as $user) {
		if (empty($user['email'])) {
			continue;
		}
		if (strcasecmp(trim((string)$user['email']), $email) === 0) {
			$users_by_email[$cache_key] = $user;
			return $user;
		}
	}

	$users_by_email[$cache_key] = false;
	return false;
}
