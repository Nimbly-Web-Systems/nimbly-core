<?php

load_library('header');

function json_sc($params) {
    $code = get_param_value($params, 'code', current($params));
    json_result($params, $code);
}

function json_result($result, $code = 200, $modified = 0) {
    $result['code'] = $code;
    $result['success'] = $code < 400;
    $result['status'] = $code < 400? 'ok' : 'error';
    $result['memory_usage'] = sprintf("%01.0fKb", memory_get_peak_usage() / 1024);
    $result['execution_time'] = sprintf("%01.3fs", microtime(true) - $GLOBALS['SYSTEM']['request_time']);
    if (empty($code)) {
        return $result;
    }
    header_sent('json', true, $modified);
    http_response_code($code);
    exit(json_encode($result, JSON_UNESCAPED_UNICODE));
}

function json_input($create_uuid = true, $pk_field = 'pk') {
    $data = file_get_contents("php://input");
    $clean_data = strip_tags($data, "<h1><h2><h3><h4><h5><h6><b><strong><a><i><p><blockquote><ol><ul><li><br><img><iframe>");
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