<?php

// 1. Initialize global SYSTEM variable and get the requested URI from the webserver
$SYSTEM['request_time'] = microtime(true);
$SYSTEM['uri_base'] = trim(dirname($_SERVER['SCRIPT_NAME']), '\\') . '/';
if ($SYSTEM['uri_base'] === '//') {
    $SYSTEM['uri_base'] = '/';
}
$SYSTEM['file_base'] = dirname(__FILE__) . '/';
$SYSTEM['request_uri'] = trim(substr($_SERVER['REQUEST_URI'], strlen($SYSTEM['uri_base'])), '/\ ');
if (!empty($_SERVER['QUERY_STRING'])) {
    $SYSTEM['request_uri'] = substr($SYSTEM['request_uri'], 0, strlen($SYSTEM['request_uri']) - 1 - strlen($_SERVER['QUERY_STRING']));
}

// 2. Locate and include the "find" library
$SYSTEM['env_paths'] = ['ext', 'contrib', 'core'];
foreach ($SYSTEM['env_paths'] as $env_path) {
    $path = $SYSTEM['file_base'] . $env_path . '/lib/find/find.php';
    if (file_exists($path)) {
        include($path);
        break;
    }
}

// 3. Run the URI 
load_library('run');
run_uri($SYSTEM['request_uri']);

// 4. If this point is reached, the core is broken: return a 500
header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);