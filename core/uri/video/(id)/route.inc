<?php

/*
 * Accept route if it follows the pattern /video/(uuid)
 */


$parts = router_match(__FILE__);
if ($parts === false || count($parts) !== 1) {
    return;
}

$uuid = $parts[0];
load_library("data");
if (!data_exists(".files", $uuid) || !data_exists(".files_meta", $uuid)) {
	http_response_code(404);
    exit();
}
load_library('file-access');
if (!file_access_allowed($uuid)) {
	http_response_code(404);
	exit();
}


// browser cache return
header('Cache-Control: private');
$t = time();
$modified = (int)@filemtime(sprintf("%s/.files/%s", $GLOBALS['SYSTEM']['data_base'], $uuid));
load_library('download-headers');
if (download_is_not_modified($modified, $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '')) {
    header('Last-Modified: '. gmdate('D, d M Y H:i:s', $modified).' GMT', true, 304);
    exit();
}

// get video file
$meta = data_read('.files_meta', $uuid);

if (empty($meta['type']) || !in_array($meta['type'], ['video/mp4', 'video/ogg', 'video/x-matroska'])) {
	http_response_code(404);
    exit();
}

$file = sprintf("%s/.files/%s", $GLOBALS['SYSTEM']['data_base'], $uuid);

$cache_time = 315360000; //10 years
header("Expires: " . gmdate("D, d M Y H:i:s", $t + $cache_time) . " GMT");
header('Last-Modified: '. gmdate('D, d M Y H:i:s', $modified).' GMT');
header('Content-Type: ' . $meta['type']);
header('Accept-Ranges: bytes');
$size = filesize($file);
$range = download_range($size, $_SERVER['HTTP_RANGE'] ?? '');
if ($range === false) {
    header('Content-Range: bytes */' . $size, true, 416);
    exit();
}
if ($range === null) {
    header('Content-Length: ' . $size);
    readfile($file);
    exit();
}
[$start, $end] = $range;
http_response_code(206);
header(sprintf('Content-Range: bytes %d-%d/%d', $start, $end, $size));
header('Content-Length: ' . ($end - $start + 1));
$handle = fopen($file, 'rb');
fseek($handle, $start);
$left = $end - $start + 1;
while ($left > 0 && !feof($handle)) {
    $chunk = fread($handle, min(65536, $left));
    $left -= strlen($chunk);
    echo $chunk;
}
fclose($handle);
exit();