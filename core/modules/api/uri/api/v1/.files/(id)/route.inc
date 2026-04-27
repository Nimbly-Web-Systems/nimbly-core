<?php

router_deny();

$parts = $GLOBALS['SYSTEM']['uri_parts'];
if (count($parts) !== 4 || $parts[0] !== 'api' || $parts[1] !== 'v1' || $parts[2] !== '.files') {
    return;
}

load_library('api');
load_library('files');
return api_method_switch("files_id", '.files_meta', $parts[3]);