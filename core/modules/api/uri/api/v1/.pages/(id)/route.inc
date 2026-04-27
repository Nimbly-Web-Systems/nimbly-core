<?php

router_deny();

$parts = $GLOBALS['SYSTEM']['uri_parts'];
if (count($parts) !== 4 || $parts[0] !== 'api' || $parts[1] !== 'v1' || $parts[2] !== '.pages') {
    return;
}

load_library('api');
load_library('pages');
return api_method_switch("pages_id", 'pages', $parts[3]);
