<?php

load_library("json");
load_library("get-user");
load_libraries(["salt", "encrypt"]);
$user = get_user();
$new_pw = filter_input(INPUT_POST, 'newpw1');
$data['salt'] = salt_sc();
$data['password'] = encrypt($new_pw, $data['salt']);
data_update('users', $user['uuid'], $data);
return json_result(['message' => 'PASSWORD_UPDATED'] , 200);