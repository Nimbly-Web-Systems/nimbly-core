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

const LOGIN_FAILURES_MAX = 5;
const LOGIN_FAILURES_SECONDS = 900;

function user_login_failures_path($uuid) {
    return $GLOBALS['SYSTEM']['file_base'] . 'ext/data/.tmp/login-failures/' . md5((string)$uuid);
}

/** Times of the wrong passwords typed for this account in the last quarter of an hour. */
function user_login_failures($uuid) {
    $path = user_login_failures_path($uuid);
    $times = is_file($path) ? (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [];
    $since = time() - LOGIN_FAILURES_SECONDS;
    return array_values(array_filter(array_map('intval', $times), fn($time) => $time > $since));
}

function user_login_failed($uuid) {
    $path = user_login_failures_path($uuid);
    if (!is_dir(dirname($path))) {
        @mkdir(dirname($path), 0775, true);
    }
    $times = user_login_failures($uuid);
    $times[] = time();
    @file_put_contents($path, implode("\n", $times) . "\n", LOCK_EX);
}

function user_login_failures_clear($uuid) {
    @unlink(user_login_failures_path($uuid));
}

/**
 * Checks a typed password against the user's stored hash. A hash made with
 * older settings is replaced once the password has matched. After five wrong
 * passwords the account takes no password until the oldest is 15 minutes old.
 */
function user_password_check($user_data, $password) {
    load_library('encrypt');
    $stored = $user_data['password'] ?? '';
    if (empty($user_data['uuid'])) {
        return false;
    }
    if (count(user_login_failures($user_data['uuid'])) >= LOGIN_FAILURES_MAX) {
        return false;
    }
    if (password_matches($password, $stored) !== true) {
        user_login_failed($user_data['uuid']);
        return false;
    }
    user_login_failures_clear($user_data['uuid']);
    if (password_is_outdated($stored)) {
        data_update('users', $user_data['uuid'], ['password' => encrypt($password, $user_data['salt'])]);
    }
    return true;
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
    if (user_password_check($user_data, $password) !== true) {
        //password fail
        return persist_login_error();
    } else if (_persist_user_roles($user_data['email'])) {
        //login success
        _persist_user_features($user_data['email']);
        $_SESSION['username'] = $user_data['email'];
        $_SESSION['user_uuid'] = $user_data['uuid'];
        session_login_completed();
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
        session_login_completed();
        return true;
    }
    return persist_login_error();
}

function _persist_user_roles($name) {
    run_library('session');
    if (!isset($_SESSION['roles']) || !is_array($_SESSION['roles'])) {
        $_SESSION['roles'] = [];
    }
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
    $_SESSION['features'] = user_feature_map($name);
}

/** A user's features as the session holds them, computed from their roles right now. */
function user_feature_map($name): array {
    $features = permission_expand_features(load_user_features($name));
    $map = array();
    if (empty($features)) {
        $map['(none)'] = true;
    } else {
        foreach ($features as $v) {
            $map[$v] = true;
        }
    }
    $user = find_user_by_email($name);
    if (!empty($user['uuid'])) {
        $map['api_put_users_' . $user['uuid']] = true;
    }
    if (user_has_role($name, 'admin')) {
        $map['(none)'] = false;
        $map['(all)'] = true;
    }
    return $map;
}

function user_has_role($username, $role) {
    $roles = load_user_roles($username);
    return is_array($roles) && in_array($role, $roles);
}
