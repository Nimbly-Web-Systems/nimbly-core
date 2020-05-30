<?php

function return_img($resource, $uuid, $size) {

    load_library('data', 'data');
    if (!data_exists($resource, $uuid)) {
        return false;
    }
    
    /* Browser Cache */
    header('Cache-Control: private');
    $t = time();
    $headers = apache_request_headers();
    if (isset($headers['If-Modified-Since']) && strtotime($headers['If-Modified-Since']) < $t) {
        header('Last-Modified: '. gmdate('D, d M Y H:i:s', $t).' GMT', true, 304);
        exit();
    }

    /* Create Thumbnail */
    $ratio = get_variable('ratio', 0);
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
    load_library('init-img', 'media');
    run_single_sc('init-img resource="' . $resource . '" uuid=' . $uuid);
    $max_w = get_variable('max_img_w', 1920);
    $max_h = get_variable('max_img_h', 1080);
    if ($size > $max_h && ($mode === 'h' || ($ratio > 0 && $ratio < 1))) {
        $size = $max_h;
    } else if ($size > $max_w) {
        $size = $max_w;
    }

    load_library('thumbnail', 'media');
    $file = thumbnail_create($resource, $uuid, $size, $ratio, $mode);

    if (empty($file)) {
        return;
    }

    // return thumb with cache headers
    $cache_time = 315360000; //10 years
    header("Expires: " . gmdate("D, d M Y H:i:s", $t + $cache_time) . " GMT");
    header('Last-Modified: '. gmdate('D, d M Y H:i:s', $t).' GMT');
    header('Content-Length: '. filesize($file));
    header('Content-Type: image/jpg');
    readfile($file);
    exit();
}