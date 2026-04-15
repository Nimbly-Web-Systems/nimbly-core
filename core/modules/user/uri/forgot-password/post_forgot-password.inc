<?php

load_library('password-reset', 'user');
load_library('set');

$result = password_reset_request(filter_input(INPUT_POST, 'email'));
set_variable('password_reset_requested', 'yes');
set_variable('password_reset_message', $result['message']);
