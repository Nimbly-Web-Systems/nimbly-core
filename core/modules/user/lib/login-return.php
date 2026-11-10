<?php

/**
 * The page a visitor came from before an [#access ... redirect=login#] sent
 * them to the login page. Only a relative path inside this site is accepted.
 */
function login_return_is_safe($path) {
    return is_string($path)
        && preg_match('#^[A-Za-z0-9][A-Za-z0-9/_.()~-]*$#', $path) === 1
        && !str_contains($path, '..')
        && !str_contains($path, '//');
}

function login_return_value() {
    $path = (string)($_POST['return'] ?? $_GET['return'] ?? '');
    return login_return_is_safe($path) ? $path : '';
}

function login_return_sc($params = null) {
    return htmlspecialchars(login_return_value(), ENT_QUOTES);
}
