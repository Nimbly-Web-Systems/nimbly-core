<?php

/**
 * @doc `[header (type)]` outputs a http header, possible header types: css, js, json, woff, 403, 404, 500, csv
 */
function header_sc($params) {
    header_sent($type = current($params), $cached = end($params));
}

function header_sent($type, $cached=false, $modified=0) {
    if ($cached !== false) {
        $t = $modified === 0? time() : $modified;
        $headers = apache_request_headers();
        header('Cache-Control: private');
        if (isset($headers['If-Modified-Since']) && $t <= strtotime($headers['If-Modified-Since'])) {
            header('Last-Modified: '. gmdate('D, d M Y H:i:s', $t).' GMT', true, 304);
            exit();
        }
        $cache_time = 315360000; //10 years
        header("Expires: " . gmdate("D, d M Y H:i:s", $t + $cache_time) . " GMT");
        header('Last-Modified: '. gmdate('D, d M Y H:i:s', $t).' GMT');    
    }
    static $types = [
        'css' => 'Content-type: text/css',
        'js' => 'Content-type: application/javascript',
        'json' => 'Content-type: application/json',
        'woff' => 'Content-type: application/x-font-woff',
        '403' => 'HTTP/1.0 403 Forbidden',
        '404' => 'HTTP/1.0 404 Not Found',
        '500' => 'HTTP/1.1 500 Internal Server Error',
        'csv' => 'Content-type: application/csv; charset=UTF-8'
    ];
    if (isset($types[$type])) {
        header($types[$type]);
    }
}

function header_not_modified($modified) {
    $headers = apache_request_headers();
    if (!isset($headers['If-Modified-Since'])) {
        return;
    }
    $ims = strtotime($headers['If-Modified-Since']);
    if ($modified > $ims) {
        return;
    }
    header('Cache-Control: private');
    header('Last-Modified: '. gmdate('D, d M Y H:i:s', $modified) . ' GMT', true, 304);
    exit(); 
}