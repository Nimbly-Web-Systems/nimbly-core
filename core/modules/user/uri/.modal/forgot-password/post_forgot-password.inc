<?php

run_single_sc('init-email');

load_library("json", "api");
load_libraries(['url', 'email', 'log', 'uuid', 'data', 'set', 'text']);

$email = filter_input(INPUT_POST, 'email');

// always show the same message, even if the email does not exist:
// we do not want to give away info on who is member of our site
$msg = t('Instructions to reset your password have been sent to your email address.');

// sleep random amount of time to hide execution time information for success/failure flows
$sleep_time = rand(100000, 1000000);
usleep($sleep_time);

$user = data_read('users', md5($email));
if (empty($user)) {
	log_system('Error: user does not exist ' . $email);
	return json_result(['message' => $msg] , 200);
}

if ($user['email'] !== $email) {
	log_system('Error: entered email does not match user email');
	return json_result(['message' => $msg] , 200);
}

log_system('password reset for ' . $email);

$reset_token = $user['password_reset_token'] ?? uuid_sc();
$user['password_reset_token'] = $reset_token;
$user_uuid = md5($email);
data_update('users', $user_uuid, $user);
set_variable('name', $user['name']);
set_variable('email', $email);
set_variable('reset-url', url_absolute('password-reset/' . $user_uuid . '/' . $reset_token));
$cfg = data_read('.config', 'email-password-reset');

if (empty($cfg)) {
	log_system('Error: could not send email. setting `email-password-reset` is empty');
	return json_result(['message' => $msg] , 200);
}

$cfg['recipient'] = $email;

if (!email($cfg)) {
	log_system('Error: could not send email. Email function failed.');
}  

return json_result(['message' => $msg] , 200);