<?php

load_library('session', 'user');
load_library('data', 'data');

function access_sc($params) {

    $role = get_param_value($params, "role", false);
    if ($role !== false && access_by_role($role) === true) {
        return;
    }

    $feature = get_param_value($params, "feature", false);
    if ($feature !== false && access_by_feature($feature) === true) {
         return;
    }

    $key = get_param_value($params, "key", false);
    if ($key !== false && access_by_key($key) === true) {
         return;
    }

    /* access denied */
    access_denied(get_param_value($params, 'redirect')); 
}

function access_denied($redirect_url = 'errors/403') {
    load_library('redirect');
    redirect($redirect_url);
}

function access_by_role($role) {
    $has_session = session_resume();
    if ($role === "anonymous" && $has_session === false) {
        return true;
    }
    $roles = explode(',', $role);
    foreach ($roles as $r) {
        if (!empty($_SESSION['roles'][$r])) {
            return true;
        }
    }
    return false;
}

function access_by_feature($feature) {
    $has_session = session_resume();
    if ($has_session === false || !isset($_SESSION['features'])) {
        
        return false;
    }
    if (isset($_SESSION['features']['(all)']) && $_SESSION['features']['(all)'] === true) {
        return true;
    }
    $features = explode(',', $feature);
    foreach ($features as $f) {
        if (!empty($_SESSION['features'][$f]) && $_SESSION['features'][$f] === true) {
            return true;
        }
    }
    return false;
}

function access_by_key($key) {
    if (empty($key)) {
        return false;
    }
    if (isset($_SERVER['PEPPER']) && $key === $_SERVER['PEPPER']) {
        return true;
    }
    if (isset($_SESSION['key']) && $key === $_SESSION['key']) {
        return true;
    }
    if (isset($_POST['api-key']) && $key === $_POST['api-key']) {
        return true;
    }
    if (isset($_SERVER['HTTP_X_API_KEY']) && $key === $_SERVER['HTTP_X_API_KEY']) {
        return true;
    }
    return false;
}


function load_user_roles($userid) {
    $roles = data_read('users', $userid, 'roles');
    if (!empty($roles)) {
        $result = array_map('trim', explode(',', $roles));
    } else {
        $result = array();
    }
    return $result;
}

function load_user_features($userid) {
    $features = data_read('users', $userid, 'features');
    if (!empty($features)) {
        $result = array_map('trim', explode(',', $features));
        return $result;
    }
    $roles = load_user_roles($userid);
    $result = array();
    foreach ($roles as $role) {
        $features = data_read('roles', $role, 'features');
        if (!empty($features)) {
            $fs = array_map('trim', explode(',', $features));
            $result = array_merge($result, $fs);
            if ($features === "(all)") {
                break;
            }
        }
    }
    $result = array_unique($result);
    return $result;
}

function persist_login_error() {
    $GLOBALS['SYSTEM']['validation_errors']['_global'][] = "[text validate_invalid_email_or_password]";
    $_SESSION['userid'] = -1;
    return false;
}

function persist_login($email, $password) {
    $users = data_read_index('users', 'email', md5($email));
    if (count($users) !== 1) {
        return persist_login_error();
    }
    $user_data = current($users);
    if (empty($user_data['salt']) || empty($user_data['password'] || empty($user_data['uuid']))) {
        return persist_login_error();
    }
    load_library('encrypt');
    $pw_typed = encrypt($password, $user_data['salt']);
    $pw_stored = $user_data['password'];
    if (hash_equals($pw_stored, $pw_typed) !== true) { 
        //hash_equals: time safe
        return persist_login_error();
    } else if (_persist_user_roles($user_data['uuid'])) {
        //login success
        run_library('session');
        _persist_user_features($user_data['uuid']);
        $_SESSION['userid'] = $user_data['uuid'];
        return true;
    }
    return persist_login_error();
}

function persist_oauth_login($email) {
    $users = data_read_index('users', 'email', md5($email));
    if (count($users) !== 1) {
        return persist_login_error();
    }
    $user_data = current($users);
    if (empty($user_data['uuid'])) {
        return persist_login_error();
    }
    if (_persist_user_roles($user['uuid'])) {
        // login success
        run_library('session');
        _persist_user_features($user_data['uuid']);
        $_SESSION['userid'] = $user_data['uuid'];
        return true;
    }
    return persist_login_error();
}

function _persist_user_roles($userid) {
    $roles = load_user_roles($userid);
    foreach ($_SESSION['roles'] as $role => $value) {
        $_SESSION['roles'][$role] = false;
    }
    foreach ($roles as $key => $role) {
        $_SESSION['roles'][$role] = true;
    }
    if (empty($roles)) {
        $_SESSION['roles']['anonymous'] = true;
        return false;
    } else {
        return true;
    }
}

function _persist_user_features($userid) {
    $features = load_user_features($userid);
    $_SESSION['features'] = array();
    if (empty($features)) {
        $_SESSION['features']['(none)'] = true;
    } else {
        foreach ($features as $k => $v) {
            $_SESSION['features'][$v] = true;
        }
    }
    if (user_has_role($userid, 'admin')) {
        $_SESSION['features']['(none)'] = false;
        $_SESSION['features']['(all)'] = true;
    } else {
        $_SESSION['features']['api_put_users_' . $userid] = true; 
    }
}

function user_has_role($userid, $role) {
    $roles = load_user_roles($userid);
    return is_array($roles) && in_array($role, $roles);
}
