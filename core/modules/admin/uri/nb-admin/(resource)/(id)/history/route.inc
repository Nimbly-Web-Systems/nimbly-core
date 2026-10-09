<?php

router_deny();

$parts = router_match(__FILE__);
if ($parts === false || count($parts) !== 2) {
    return;
}

$uuid = $parts[1];

load_library('record-history-link');
$resource = record_history_route($parts[0]);
// A deleted record has a history too; a record that never existed has not
if ($resource === '' || $uuid[0] === '.' || (!data_exists($resource, $uuid) && history_list($resource, $uuid) === [])) {
    return;
}

set_variable('uuid', $uuid);

router_accept();
