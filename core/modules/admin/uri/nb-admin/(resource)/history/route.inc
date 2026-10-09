<?php

router_deny();

$parts = router_match(__FILE__);
if ($parts === false || count($parts) !== 1) {
    return;
}

load_library('record-history-link');
if (record_history_route($parts[0]) === '') {
    return;
}

router_accept();
