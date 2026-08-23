<?php

function password_reset_public_message() {
	load_library('text');
	return t('Instructions to reset your password have been sent to your email address.');
}

function password_reset_request($email) {
	load_libraries(['url', 'log', 'uuid', 'job', 'data', 'encrypt']);
	load_library('get-user');

	$email = trim((string)$email);
	$message = password_reset_public_message();

	if ($email === '') {
		return ['message' => $message, 'sent' => false];
	}

	$user = find_user_by_email($email);
	if (empty($user) || empty($user['uuid']) || empty($user['email'])) {
		log_system_event('password_reset.request_unknown');
		return ['message' => $message, 'sent' => false];
	}

	$reset_token = empty($user['password_reset_token']) ? generate_uuid() : $user['password_reset_token'];
	$updates = ['password_reset_token' => $reset_token];
	if (empty($user['password']) || empty($user['salt'])) {
		$updates['salt'] = generate_salt();
		$updates['password'] = encrypt(generate_uuid(), $updates['salt']);
	}

	$stored_user = data_update('users', $user['uuid'], $updates);
	if (!is_array($stored_user) || !password_reset_token_matches($stored_user, $reset_token)) {
		log_system_event('password_reset.persistence_failed', [
			'stage' => 'request',
			'user_uuid' => $user['uuid'],
		]);
		return ['message' => $message, 'sent' => false];
	}

	$job_uuid = job_enqueue('password-reset', [
		'user_uuid' => $user['uuid'],
		'email'     => $user['email'],
		'name'      => $user['name'] ?? $user['email'],
		'reset_url' => url_absolute('password-reset/' . $user['uuid'] . '/' . $reset_token),
	]);
	if ($job_uuid === false) {
		log_system_event('password_reset.queue_failed', ['user_uuid' => $user['uuid']]);
		return ['message' => $message, 'sent' => false];
	}

	log_system_event('password_reset.queued', ['user_uuid' => $user['uuid']]);

	return ['message' => $message, 'sent' => true];
}

function password_reset_token_matches($user, $reset_token)
{
	return !empty($user['password_reset_token'])
		&& hash_equals((string)$user['password_reset_token'], (string)$reset_token);
}

function password_reset_complete($user_uuid, $reset_token, $password)
{
	load_libraries(['data', 'util', 'encrypt', 'validate', 'log']);

	if (validate('password', $password) !== true) {
		log_system_event('password_reset.completion_failed', ['reason' => 'invalid_password']);
		return false;
	}

	$user = data_read('users', $user_uuid);
	if (!is_array($user) || !password_reset_token_matches($user, $reset_token)) {
		$context = ['reason' => 'invalid_request'];
		if (is_array($user) && !empty($user['uuid'])) {
			$context['user_uuid'] = $user['uuid'];
		}
		log_system_event('password_reset.completion_failed', $context);
		return false;
	}

	$salt = generate_salt();
	$rotated_token = generate_uuid();
	$stored_user = data_update('users', $user_uuid, [
		'salt' => $salt,
		'password' => encrypt($password, $salt),
		'password_reset_token' => $rotated_token,
	]);
	if (!is_array($stored_user)
		|| ($stored_user['salt'] ?? '') !== $salt
		|| !hash_equals((string)($stored_user['password_reset_token'] ?? ''), $rotated_token)
	) {
		log_system_event('password_reset.persistence_failed', [
			'stage' => 'completion',
			'user_uuid' => $user_uuid,
		]);
		return false;
	}
	log_system_event('password_reset.completed', ['user_uuid' => $user_uuid]);

	return $stored_user;
}

function password_reset_job($job)
{
	$payload = $job['payload'] ?? [];
	$email = $payload['email'] ?? '';
	$user_uuid = (string)($payload['user_uuid'] ?? '');
	if ($email === '') {
		load_library('log');
		log_system_event('password_reset.send_failed', ['reason' => 'missing_recipient']);
		return false;
	}

	load_libraries(['email', 'env', 'set', 'log', 'data', 'lookup', 'get']);

	$site_name = data_lookup('.config', 'site', 'name', 'our site');
	if (is_array($site_name)) {
		$site_name = get_i18n_resolve($site_name, 'auto');
	}
	$subject = data_lookup('.config', 'site', 'pw_reset_subject', 'Reset your ' . $site_name . ' password');

	set_variable('name', $payload['name'] ?? $email);
	set_variable('email', $email);
	set_variable('reset-url', $payload['reset_url'] ?? '');

	$cfg = [
		'service'   => env('MAIL_SERVICE', 'resend'),
		'from'      => env('MAIL_FROM'),
		'from_name' => env('MAIL_FROM_NAME'),
		'recipient' => $email,
		'subject'   => $subject,
		'tpl'       => 'email-password-reset',
	];

	if (!email($cfg)) {
		log_system_event('password_reset.send_failed', ['user_uuid' => $user_uuid]);
		return false;
	}

	log_system_event('password_reset.sent', ['user_uuid' => $user_uuid]);
	return true;
}
