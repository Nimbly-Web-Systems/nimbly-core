<?php

function password_reset_public_message() {
	load_library('text');
	return t('Instructions to reset your password have been sent to your email address.');
}

function password_reset_request($email) {
	load_libraries(['url', 'log', 'uuid', 'job', 'data']);
	load_library('get-user');

	$email = trim((string)$email);
	$message = password_reset_public_message();

	if ($email === '') {
		return ['message' => $message, 'sent' => false];
	}

	$user = find_user_by_email($email);
	if (empty($user) || empty($user['uuid']) || empty($user['email'])) {
		log_system('Error: password reset requested for unknown email ' . $email);
		return ['message' => $message, 'sent' => false];
	}

	$reset_token = $user['password_reset_token'] ?? generate_uuid();
	data_update('users', $user['uuid'], ['password_reset_token' => $reset_token]);

	job_enqueue('password-reset', [
		'email'     => $user['email'],
		'name'      => $user['name'] ?? $user['email'],
		'reset_url' => url_absolute('password-reset/' . $user['uuid'] . '/' . $reset_token),
	]);

	log_system('Password reset email queued for ' . $user['email']);

	return ['message' => $message, 'sent' => true];
}

function password_reset_job($job)
{
	$payload = $job['payload'] ?? [];
	$email = $payload['email'] ?? '';
	if ($email === '') {
		return false;
	}

	load_libraries(['email', 'env', 'set', 'log', 'data', 'lookup']);

	$site_name = data_lookup('.config', 'site', 'name', 'our site');
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
		log_system('Error: password reset email failed for ' . $email);
		return false;
	}

	log_system('Password reset email sent to ' . $email);
	return true;
}
