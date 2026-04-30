<?php

load_library("json");
load_library("get-user");
load_libraries(["salt", "encrypt"]);
load_libraries(['url', 'log', 'uuid', 'job', 'data']);
$user = get_user();
$email = filter_input(INPUT_POST, 'newemail');
$token = $user['change_email_token'] ?? uuid_sc();
data_update('users', $user['uuid'], [
	'new_email'          => $email,
	'change_email_token' => $token,
]);

job_enqueue('change-email', [
	'email'           => $email,
	'name'            => $user['name'] ?? $user['email'],
	'change_email_url' => url_absolute('change-email/' . $user['uuid'] . '/' . md5($email) . '/' . $token),
]);

return json_result(['message' => 'Instructions on how to confirm your new email address have been sent to ' . $email], 200);