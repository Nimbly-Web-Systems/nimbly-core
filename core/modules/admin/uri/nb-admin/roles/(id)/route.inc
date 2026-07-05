<?php

router_deny();

$parts = router_match(__FILE__);
if ($parts === false || count($parts) !== 1) {
    return;
}

$role_id = $parts[0];
load_library('data');
if (!data_exists('roles', $role_id)) {
    return;
}

set_variable('role-id', $role_id);
router_accept();
