<?php

load_library('set');

function api_session_sc() {
	load_library('api', 'api');
	api_method_switch('api_session');
}

function api_session_post() {
    $data = json_input(false);
    $stored = [];
    foreach ($data as $k => $v) {
        $key = 'api_' . $k;
        set_session_variable($key, $v, true);
        $stored[$key] = $v;
    }
    return json_result([
    	'stored' => $stored, 
    	'count' => count($stored)
    ]);
}
