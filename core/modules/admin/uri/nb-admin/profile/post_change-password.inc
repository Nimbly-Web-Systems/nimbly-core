<?php

load_libraries(['get-user', 'data', 'util', 'encrypt', 'log']);
$user = get_user();
$salt = generate_salt();
$stored = data_update('users', $user['uuid'], [
    'salt' => $salt,
    'password' => encrypt((string)filter_input(INPUT_POST, 'new_password'), $salt),
]);
if (!is_array($stored) || ($stored['salt'] ?? '') !== $salt) {
    $GLOBALS['SYSTEM']['validation_errors']['_global'][] = '[#text password_save_failed#]';
    return;
}
log_system_event('password.changed', ['user_uuid' => $user['uuid']]);
load_library('system-messages');
system_message('Your password has been changed.');
load_library('redirect');
redirect('nb-admin/profile');
