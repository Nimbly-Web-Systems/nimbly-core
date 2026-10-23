<?php

router_deny();
$parts = router_match(__FILE__);
if ($parts === false || count($parts) !== 1) {
    return;
}

$resource = $parts[0];
load_library("data");
if (!data_exists($resource)) {
    return;
}
set_variable('resource-id', $resource);
set_variable('resource-view', (data_meta($resource)['admin_view'] ?? '') === 'documents' ? 'documents' : 'table');
router_accept();
