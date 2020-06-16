<?php 

$parts = router_match(__FILE__);
if ($parts === false || count($parts) !== 1) {
    return;
}

load_library('api', 'api');
load_library('files', 'api');
return api_method_switch_with_subkey('files', 'avatars', $parts[0], null);