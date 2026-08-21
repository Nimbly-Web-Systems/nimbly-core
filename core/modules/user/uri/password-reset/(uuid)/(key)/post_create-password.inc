<?php

$password = filter_input(INPUT_POST, 'password');
$user_uuid = get_variable('useruuid');
$reset_token = get_variable('password_reset_token');
load_library('password-reset');
$user = password_reset_complete($user_uuid, $reset_token, $password);
if ($user === false) {
	$GLOBALS['SYSTEM']['validation_errors']['_global'][] = '[#text password_reset_save_failed#]';
	return;
}
load_library('system-messages');
system_message('Your password has been reset. Please log in with ' . $user['email'] . ' and your new password.');
load_library('redirect');
redirect('login?email=' . urlencode($user['email']));
