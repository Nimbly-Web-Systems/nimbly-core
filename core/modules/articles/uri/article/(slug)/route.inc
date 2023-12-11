<?php

$parts = router_match(__FILE__);
if ($parts === false || count($parts) !== 1) {
    return;
}

load_libraries(['md5', 'data', 'set']);

$resource = 'articles';
$id = md5_uuid($parts[0]);

if (!(data_exists($resource, $id))) {
    return;
}

set_variable_dot('record', data_read($resource, $id));
router_accept();