<?php

/*
 * Accept route if it follows the pattern /download/(uuid)
 */

router_deny();
$parts = router_match(__FILE__);
if ($parts === false || count($parts) !== 1) {
    return;
}

$uuid = $parts[0];
load_library("data");
if (!data_exists(".files", $uuid)) {
	http_response_code(404);
    exit();
}

$file_meta = data_read('.files_meta', $uuid);
$file_path = $GLOBALS['SYSTEM']['file_base'] . 'ext/data/.files/' . $uuid;


// browser cache return
header('Cache-Control: private');
$t = time();
$headers = apache_request_headers();
if (isset($headers['If-Modified-Since']) && strtotime($headers['If-Modified-Since']) < $t) {
    header('Last-Modified: '. gmdate('D, d M Y H:i:s', $t).' GMT', true, 304);
    exit();
}

$cache_time = 315360000; //10 years
header("Expires: " . gmdate("D, d M Y H:i:s", $t + $cache_time) . " GMT");
header('Last-Modified: '. gmdate('D, d M Y H:i:s', $t).' GMT');
header('Content-Length: '. filesize($file_path));
header('Content-Type: ' . $file_meta['type']);
readfile($file_path);
exit();