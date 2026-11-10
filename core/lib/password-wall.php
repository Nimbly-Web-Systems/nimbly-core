<?php

/**
 * A password instead of a user. ext/data/.password-wall/<name> holds a password
 * hash and the features it grants (set with ./nimbly password-wall:set <name>).
 *
 * [#password-wall lms-artifacts#] at the top of a page lets in a session that
 * typed that wall's password, or a logged-in user who has all its features.
 * Anyone else goes to /password-wall and returns to the page afterwards.
 * The right password adds the wall's features to the (anonymous) session, so
 * [#access feature=...#] and [#feature-cond#] work as usual. A new password
 * closes the wall again for every earlier session.
 *
 * On the /password-wall route, [#password-wall form#] sets password_wall_name
 * and password_wall_return for the form.
 */

load_libraries(['session', 'redirect', 'data', 'get', 'set', 'login-return']);

function password_wall_sc($params) {
    $name = (string)current($params ?: []);
    if ($name === 'form') {
        set_variable('password_wall_name', password_wall_name((string)($_POST['name'] ?? $_GET['name'] ?? '')));
        set_variable('password_wall_return', login_return_value());
        return;
    }
    $name = password_wall_name($name);
    if ($name === '') {
        redirect('errors/403');
    }
    if (password_wall_open($name)) {
        return;
    }
    $url = 'password-wall?name=' . rawurlencode($name);
    $return = strtok((string)($GLOBALS['SYSTEM']['request_uri'] ?? ''), '?');
    if (login_return_is_safe($return)) {
        $url .= '&return=' . rawurlencode($return);
    }
    redirect($url);
}

function password_wall_name($name) {
    return preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', (string)$name) === 1 ? $name : '';
}

function password_wall_record($name) {
    $record = data_read('.password-wall', $name);
    return is_array($record) && !empty($record['password']) ? $record : null;
}

function password_wall_features($record) {
    return array_values(array_filter(array_map('trim', explode(',', (string)($record['features'] ?? '')))));
}

/** What the session keeps: tied to the current hash, so a new password ends every pass. */
function password_wall_pass($record) {
    return hash('sha256', (string)$record['password']);
}

function password_wall_open($name) {
    $record = password_wall_record($name);
    if ($record === null || !session_resume()) {
        return false;
    }
    if (hash_equals(password_wall_pass($record), (string)($_SESSION['password_walls'][$name] ?? ''))) {
        return true;
    }
    $features = password_wall_features($record);
    if (empty($_SESSION['user_uuid']) || $features === []) {
        return false;
    }
    load_library('permissions');
    foreach ($features as $feature) {
        if (!permission_session_has($feature)) {
            return false;
        }
    }
    return true;
}

/** Same guessing limit as the login: five wrong passwords per wall and address per 15 minutes. */
function password_wall_enter($name, $password) {
    load_libraries(['access', 'encrypt']);
    $record = password_wall_record($name);
    $key = 'password-wall:' . $name;
    if ($record === null || count(user_login_failures($key)) >= LOGIN_FAILURES_MAX) {
        return false;
    }
    if (password_matches($password, $record['password']) !== true) {
        user_login_failed($key);
        return false;
    }
    user_login_failures_clear($key);
    session_sc();
    session_regenerate_id(true);
    $_SESSION['password_walls'][$name] = password_wall_pass($record);
    foreach (password_wall_features($record) as $feature) {
        $_SESSION['features'][$feature] = true;
    }
    return true;
}
