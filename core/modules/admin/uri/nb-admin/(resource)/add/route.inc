<?php

router_deny();

$parts = router_match(__FILE__);
if ($parts === false || count($parts) !== 1) {
    return;
}

$resource = $parts[0];

load_library('data');
if (!data_exists($resource)) {
    return;
}

set_variable('resource-id', $parts[0]);

load_library('admin-return-url');
$return_url = admin_return_url();
if ($return_url !== '') {
    set_variable('admin-return-url', $return_url);
}

router_accept();