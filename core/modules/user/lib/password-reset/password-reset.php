<?php

function password_reset_public_message() {
	load_library('text');
	return t('Instructions to reset your password have been sent to your email address.');
}

function password_reset_service_config() {
	load_library('data');
	load_library('md5');

	$services = data_read_index('.services', 'tpl', md5_uuid('email-password-reset'));
	if (!empty($services)) {
		return reset($services);
	}

	foreach ([md5('email-password-reset'), 'email-password-reset'] as $service_id) {
		$service = data_read('.services', $service_id);
		if (!empty($service)) {
			return $service;
		}
	}

	return null;
}

function password_reset_request($email) {
	load_libraries(['url', 'email', 'log', 'uuid', 'set']);
	load_library('get-user', 'user');

	$email = trim((string)$email);
	$message = password_reset_public_message();

	if ($email === '') {
		return ['message' => $message, 'sent' => false];
	}

	// Keep timing less informative even when the email is unknown.
	usleep(rand(100000, 1000000));

	$user = find_user_by_email($email);
	if (empty($user) || empty($user['uuid']) || empty($user['email'])) {
		log_system('Error: password reset requested for unknown email ' . $email);
		return ['message' => $message, 'sent' => false];
	}

	$reset_token = $user['password_reset_token'] ?? uuid_sc();
	$user['password_reset_token'] = $reset_token;
	data_update('users', $user['uuid'], ['password_reset_token' => $reset_token]);

	set_variable('name', $user['name'] ?? $user['email']);
	set_variable('email', $user['email']);
	set_variable('reset-url', url_absolute('password-reset/' . $user['uuid'] . '/' . $reset_token));

	$cfg = password_reset_service_config();
	if (empty($cfg)) {
		log_system('Error: could not send password reset email. Email configuration for `email-password-reset` is empty');
		return ['message' => $message, 'sent' => false];
	}

	$cfg['recipient'] = $user['email'];
	if (!empty($user['name'])) {
		$cfg['recipient_name'] = $user['name'];
	}

	if (!email($cfg)) {
		log_system('Error: could not send password reset email. Email function failed.');
		return ['message' => $message, 'sent' => false];
	}

	log_system('Password reset email sent to ' . $user['email']);

	return ['message' => $message, 'sent' => true];
}
