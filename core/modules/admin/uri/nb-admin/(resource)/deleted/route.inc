<?php

router_deny();

$parts = router_match(__FILE__);
if ($parts === false || count($parts) !== 1) {
    return;
}

$resource = $parts[0];

load_library('data');
load_library('history');
if (!data_exists($resource) || !history_enabled($resource)) {
    return;
}

set_variable('resource-id', $resource);

router_accept();
