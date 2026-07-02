<?php

load_library("session");
load_library('data');
load_library("redirect");
load_library('get-user');
load_library('permissions');

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
    access_denied(get_param_value($params, "redirect")); 
}

function access_denied($redirect_url = 'errors/403') {
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
    $features = explode(',', $feature);
    foreach ($features as $f) {
        if (permission_session_has($f)) {
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
    return false;
}


function load_user_roles($name) {
    $user = find_user_by_email($name);
    if (empty($user)) {
        return array();
    }
    $roles = $user['roles'] ?? '';
    if (!empty($roles)) {
        if (is_array($roles)) {
            return $roles;
        }
        $result = array_map('trim', explode(',', $roles));
    } else {
        $result = array();
    }
    return $result;
}

function load_user_features($name) {
    $user = find_user_by_email($name);
    if (empty($user)) {
        return array();
    }

    $features = $user['features'] ?? '';
    if (!empty($features)) {
        $result = array_map('trim', explode(',', $features));
        return $result;
    }
    $roles = load_user_roles($name);
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
    $GLOBALS['SYSTEM']['validation_errors']['_global'][] = "[#text validate_invalid_email_or_password#]";
    $_SESSION['username'] = 'anonymous';
    unset($_SESSION['user_uuid']);
    return false;
}

function persist_login($email, $password) {
    run_library('session');
    $user_data = find_user_by_email($email);
    if (empty($user_data) || empty($user_data['uuid'])) {
        return persist_login_error();
    }
    if (empty($user_data['salt']) || empty($user_data['password'])) {
        return persist_login_error();
    }
    load_library('encrypt');
    $pw_typed = encrypt($password, $user_data['salt']);
    $pw_stored = $user_data['password'];
    //hash_equals: time safe
    if (hash_equals($pw_stored, $pw_typed) !== true) { 
        //password fail
        return persist_login_error();
    } else if (_persist_user_roles($user_data['email'])) {
        //login success
        _persist_user_features($user_data['email']);
        $_SESSION['username'] = $user_data['email'];
        $_SESSION['user_uuid'] = $user_data['uuid'];
        return true;
    }
    return persist_login_error();
}

function persist_oauth_login($email) {
    run_library('session');
    $user_data = find_user_by_email($email);
    if (empty($user_data) || empty($user_data['uuid'])) {
        return persist_login_error();
    }
    if (_persist_user_roles($user_data['email'])) {
        //login success
        _persist_user_features($user_data['email']);
        $_SESSION['username'] = $user_data['email'];
        $_SESSION['user_uuid'] = $user_data['uuid'];
        return true;
    }
    return persist_login_error();
}

function _persist_user_roles($name) {
    $roles = load_user_roles($name);
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

function _persist_user_features($name) {
    $features = permission_expand_features(load_user_features($name));
    $_SESSION['features'] = array();
    if (empty($features)) {
        $_SESSION['features']['(none)'] = true;
    } else {
        foreach ($features as $k => $v) {
            $_SESSION['features'][$v] = true;
        }
    }
    $user = find_user_by_email($name);
    if (!empty($user['uuid'])) {
        $_SESSION['features']['api_put_users_' . $user['uuid']] = true;
    }
    if (user_has_role($name, 'admin')) {
        $_SESSION['features']['(none)'] = false;
        $_SESSION['features']['(all)'] = true;
    }
}

function user_has_role($username, $role) {
    $roles = load_user_roles($username);
    return is_array($roles) && in_array($role, $roles);
}
