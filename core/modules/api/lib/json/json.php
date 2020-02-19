<?php

function json_sc($params) {
    $code = get_param_value($params, 'code', current($params));
    json_result($params, $code);
}

function json_result($result, $code = 200, $final=true, $modified = 0) {
    $result['code'] = $code;
    $result['success'] = $code < 400;
    $result['status'] = $code < 400? 'ok' : 'error';
    $result['memory_usage'] = sprintf("%01.0fKb", memory_get_peak_usage() / 1024);
    $result['execution_time'] = sprintf("%01.3fs", microtime(true) - $GLOBALS['SYSTEM']['request_time']);
    if ($final) {
        load_library('header');
        json_cache_headers($modified);
        header_sent('json');
        http_response_code($code);
        exit(json_encode($result, JSON_UNESCAPED_UNICODE));
    }
    return $result;
}

function json_input($create_uuid = true, $pk_field = 'pk') {
    $data = file_get_contents("php://input");
    $clean_data = strip_tags($data, "<h1><h2><h3><h4><h5><h6><b><strong><a><i><p><blockquote><ol><ul><li><br><img>");
    $result = json_decode($clean_data, true);
    if ($create_uuid && empty($result['uuid'])) {
        if (!empty($result[$pk_field])) {
            $result['uuid'] = md5($result[$pk_field]);
        } else {
            load_library('uuid');
            $result['uuid'] = uuid_sc();
        }
    }
    return $result;
}

function json_cache_headers($modified) {
    if (empty($modified)) {
        return;
    }
    $headers = apache_request_headers();
    session_cache_limiter(false);
    header('Cache-Control: private');
    if (isset($headers['If-Modified-Since']) && strtotime($headers['If-Modified-Since']) <= $modified) {
        header('Last-Modified: '. gmdate('D, d M Y H:i:s', $modified).' GMT', true, 304);
        exit();
    }
    $t = time();
    $cache_time = 315360000; //10 years
    header("Expires: " . gmdate("D, d M Y H:i:s", $t + $cache_time) . " GMT");
    header('Last-Modified: '. gmdate('D, d M Y H:i:s', $modified).' GMT');
}

   