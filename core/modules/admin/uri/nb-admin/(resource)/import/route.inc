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

set_variable('resource', $parts[0]);
load_module('admin');

router_accept();