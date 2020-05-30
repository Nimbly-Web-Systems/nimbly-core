<?php

$parts = router_match(__FILE__);
if ($parts === false || count($parts) !== 2) {
    return;
}

load_library('user-get');
load_library('api', 'api');
load_library('files', 'api');
return api_method_switch_with_subkey('files_id', 'avatars', $parts[0], $parts[1]);