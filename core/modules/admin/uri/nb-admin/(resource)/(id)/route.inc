<?php

router_deny();

$parts = router_match(__FILE__);
if ($parts === false || count($parts) !== 2) {
    return;
}

$resource = $parts[0];
$uuid = $parts[1];
load_library("data");

load_library('data');
if (!data_exists($resource, $uuid)) {
    return;
}

set_variable('resource', $parts[0]);
set_variable('uuid', $parts[1]);
load_module('admin');

router_accept();