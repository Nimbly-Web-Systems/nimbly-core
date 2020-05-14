<?php

load_library('data', 'data');
$password = filter_input(INPUT_POST, 'password');
$user_uuid = get_variable('useruuid');
$user = data_read('users', $user_uuid);
load_library('salt', 'data');
load_library('uuid', 'data');
load_library('encrypt', 'data');
$user['salt'] = salt_sc();
$user['password'] = encrypt($password, $user['salt']);
$user['password_reset_token'] = uuid_sc();
data_update('users', $user_uuid, $user);
load_library('system-messages', 'user');
system_message('New password created successfully!');
load_library('redirect');
redirect('login');