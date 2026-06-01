<?php

load_library('data');
$password = filter_input(INPUT_POST, 'password');
$user_uuid = get_variable('useruuid');
$user = data_read('users', $user_uuid);
load_library('util');
load_library('util');
load_library('encrypt');
$user['salt'] = generate_salt();
$user['password'] = encrypt($password, $user['salt']);
$user['password_reset_token'] = generate_uuid();
data_update('users', $user_uuid, $user);
load_library('system-messages');
system_message('Your password has been reset. Please log in with ' . $user['email'] . ' and your new password.');
load_library('redirect');
redirect('login?email=' . urlencode($user['email']));
