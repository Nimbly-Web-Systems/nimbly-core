<?php

$parts = router_match(__FILE__);
if ($parts === false || count($parts) !== 3) {
    return;
}
load_library('change-email');
$user = change_email_confirm($parts[0], $parts[1], $parts[2]);
if ($user === false) {
    return;
}

// logout
run_library('session');
$_SESSION = array();
session_initialize();

load_library('set');
set_variable('old_email', $user['old_email']);
set_variable('new_email', $user['email']);
set_variable('name', $user['name']);

router_accept();
