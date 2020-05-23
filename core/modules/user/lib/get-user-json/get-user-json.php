<?php

function get_user_json_sc($params) {
    load_library('get-user');
    $user = get_user();
    if (empty($user)) {
        return '{}';
    }
    foreach (['salt', 'password', '_created_by', '_modified_by'] as $f) {
        if (isset($user[$f])) {
            unset($user[$f]);
        }
    }
    $user['token'] = $_SESSION['key'] ?? false; 
    $user['roles'] = explode(',', $user['roles']);
    $user['permissions'] = load_user_features($user['email']);
    return json_encode($user, true);
}