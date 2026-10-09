<?php

router_deny();

$parts = router_match(__FILE__);
if ($parts === false || count($parts) !== 2) {
    return;
}

[$resource, $uuid] = $parts;

load_library('data');
load_library('history');
// A deleted record has a history too; a record that never existed has not
if (!data_exists($resource) || !history_enabled($resource) || $uuid[0] === '.'
    || (!data_exists($resource, $uuid) && history_list($resource, $uuid) === [])) {
    return;
}

set_variable('resource-id', $resource);
set_variable('uuid', $uuid);

router_accept();
