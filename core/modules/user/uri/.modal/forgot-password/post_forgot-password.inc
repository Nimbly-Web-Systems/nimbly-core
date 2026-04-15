<?php

run_single_sc('init-email');

load_library("json", "api");
load_library('password-reset', 'user');

$email = filter_input(INPUT_POST, 'email');
$result = password_reset_request($email);

return json_result(['message' => $result['message']] , 200);
