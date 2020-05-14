<?php

/*
 * Accept route if it follows the pattern /video/(uuid)
 */


$parts = router_match(__FILE__);
if ($parts === false || count($parts) !== 1) {
    return;
}

$uuid = $parts[0];
load_library('data', 'data');
if (!data_exists(".files", $uuid) || !data_exists(".files_meta", $uuid)) {
	http_response_code(404);
    exit();
}


// browser cache return
header('Cache-Control: private');
$t = time();
$headers = apache_request_headers();
if (isset($headers['If-Modified-Since']) && strtotime($headers['If-Modified-Since']) < $t) {
    header('Last-Modified: '. gmdate('D, d M Y H:i:s', $t).' GMT', true, 304);
    exit();
}

// get video file
$meta = data_read('.files_meta', $uuid);

if (empty($meta['type']) || !in_array($meta['type'], ['video/mp4', 'video/ogg'])) {
	http_response_code(404);
    exit();
}

$file = sprintf("%s/.files/%s", $GLOBALS['SYSTEM']['data_base'], $uuid);

$cache_time = 315360000; //10 years
header("Expires: " . gmdate("D, d M Y H:i:s", $t + $cache_time) . " GMT");
header('Last-Modified: '. gmdate('D, d M Y H:i:s', $t).' GMT');
header('Content-Length: '. filesize($file));
header('Content-Type: ' . $meta['type']);
readfile($file);
exit();