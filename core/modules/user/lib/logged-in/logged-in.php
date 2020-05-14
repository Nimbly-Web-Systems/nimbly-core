<?php

/**
 * @doc `[logged-in]` returns "logged-in" if there is a user session
 */
function logged_in_sc($params) {
    load_library('session', 'user');
    if (session_user()) {
        return "logged-in";
    }
    return "";
}
