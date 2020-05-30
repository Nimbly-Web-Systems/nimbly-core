<?php

function files_sc($params) {
    load_library('api', 'api');
    load_library('files', 'api');
    load_library('get-user', 'user');
    $user = get_user();
    api_method_switch_with_subkey('files', 'avatars', $user['uuid']);
}

