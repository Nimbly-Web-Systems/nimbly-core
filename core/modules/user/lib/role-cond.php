<?php

/*
 * Implements role based template loading
 * Usage: [role-cond roles=admin,editor tpl=nameoftemplate]
 * Result: loads nameoftemplate if user role is admin or editor
 */

function role_cond_sc($params) {
    load_library("session");
    $roles = get_param_value($params, "roles", current($params));
    $tpl = get_param_value($params, "tpl", null);
    $tpl_else = get_param_value($params, "tpl_else", null);

    if (empty($roles) || empty($tpl)) {
        return;
    }

    session_resume();
    $roles_ls = explode(',', $roles);

    $session_roles = $_SESSION['roles'] ?? ['anonymous' => true];

    foreach ($roles_ls as $r) {
        if (!empty($session_roles[$r]) && $session_roles[$r] === true) {
            run_single_sc($tpl);
            return;
        }
    }

    if (!empty($tpl_else)) {
        run_single_sc($tpl_else);
    }
}
