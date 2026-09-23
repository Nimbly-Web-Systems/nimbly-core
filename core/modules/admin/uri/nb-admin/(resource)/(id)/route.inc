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

set_variable('resource-id', $parts[0]);
set_variable('uuid', $parts[1]);
set_variable('admin-record-view', !empty($_GET['view']) ? '1' : '');
set_variable('admin-record-access-feature', !empty($_GET['view']) ? 'view-' . $resource : 'edit-' . $resource);

load_library('admin-return-url');
$return_url = admin_return_url();
if ($return_url !== '') {
    set_variable('admin-return-url', $return_url);
}

router_accept();
