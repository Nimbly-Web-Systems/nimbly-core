<?php

/*
 * Accept route if it follows the pattern /img/(id)/*
 */

router_deny();
$parts = $GLOBALS['SYSTEM']['uri_parts'];
if (count($parts) < 2 || $parts[0] !== 'img') {
    return;
}

$uuid = $parts[1];
load_library("data");
if (!data_exists(".files", $uuid)) {
	if ($uuid === '(empty)') {
		header('Content-Type: image/png');
		exit(hex2bin('89504e470d0a1a0a0000000d494844520000000100000001010300000025db56ca00000003504c5445000000a77a3dda0000000174524e530040e6d8660000000a4944415408d76360000000020001e221bc330000000049454e44ae426082'));
	} else {
		http_response_code(404);
    	exit();
	}
}

$meta = data_read(".files_meta", $uuid);
if (($meta['type'] ?? '') === 'image/svg+xml') {
	$file = data_path(".files", $uuid);
	if (!is_file($file)) {
		http_response_code(404);
		exit();
	}

	$t = time();
	$cache_time = 315360000; //10 years
	header('Cache-Control: private');
	header("Expires: " . gmdate("D, d M Y H:i:s", $t + $cache_time) . " GMT");
	header('Last-Modified: '. gmdate('D, d M Y H:i:s', $t).' GMT');
	header('Content-Length: '. filesize($file));
	header('Content-Type: image/svg+xml');
	readfile($file);
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

// prepare thumbnail creation
$size = $parts[count($parts) - 1] ?? 240;
$ratio_text = (string) get_variable('ratio', '');
$ratio = 0;
if ($ratio_text !== '') {
	$valid_ratio = strlen($ratio_text) <= 24
		&& preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/D', $ratio_text) === 1;
	if (!$valid_ratio) {
		http_response_code(400);
		header('Content-Length: 0');
		exit();
	}
	$ratio = (float) $ratio_text;
	if (!is_finite($ratio) || $ratio <= 0 || $ratio > 100) {
		http_response_code(400);
		header('Content-Length: 0');
		exit();
	}
}
$m = substr($size, -1);
if ($m === 'f' || $m === 'c') { // fit, crop
	$mode = $m;
	$size = rtrim($size, 'fc');
} else if ($m === 'w' || $m === 'h') { 
	$mode = $m;
	$size = rtrim($size, 'wh');
} else {
	$mode = 'h';
}
if (is_numeric($size)) { 
	// nothing
} else if ($size === 'large') {
    $size = 1080;
} else if ($size === 'medium') {
    $size = 720;
} else if ($size === 'small') {
    $size = 480;
} else if ($mode !== 'f' && $mode !== 'c') {
	$size = 240;
} else {
	$parts = explode('x', $size, 2);
	if (count($parts) == 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
		$size = $parts[0] + 0;
		$ratio = $size / ($parts[1] + 0);
	} else {
		$size = 240;
	}
	if ($mode === 'c') {
		$mode = 'w';
	}
}

// limit size (default max is full hd)
$max_w = get_variable('max_img_w', 1920);
$max_h = get_variable('max_img_h', 1080);
if ($size > $max_h && ($mode === 'h' || ($ratio > 0 && $ratio < 1))) {
	$size = $max_h;
} else if ($size > $max_w) {
	$size = $max_w;
}

load_library("thumbnail");
$file = thumbnail_create($uuid, $size, $ratio, $mode);
if (empty($file)) {
    return;
}

$type = $meta['type'] ?? 'image/jpg';
if ($type === 'image/jpeg' || $type === 'image/bmp' || $type === 'image/jpg') {
	$type = 'image/webp'; //'image/avif'; 
} else if ($type === 'image/png') {
	$type = 'image/webp';
}

// return thumb with cache headers
$cache_time = 315360000; //10 years
header("Expires: " . gmdate("D, d M Y H:i:s", $t + $cache_time) . " GMT");
header('Last-Modified: '. gmdate('D, d M Y H:i:s', $t).' GMT');
header('Content-Length: '. filesize($file));
header('Content-Type: ' . $type);
readfile($file);
exit();
