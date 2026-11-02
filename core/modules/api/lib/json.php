<?php

load_library('http-header');

function json_sc($params) {
    $code = get_param_value($params, 'code', current($params));
    json_result($params, $code);
}

function json_result($result, $code = 200, $modified = 0) {
    // the jobs a save queued, see job_queued_summary()
    if (!empty($GLOBALS['SYSTEM']['queued_jobs']) && !isset($result['jobs'])) {
        $result['jobs'] = job_queued_summary();
    }
    $result['code'] = $code;
    $result['success'] = $code < 400;
    $result['status'] = $code < 400? 'ok' : 'error';
    $result['memory_usage'] = sprintf("%01.0fKb", memory_get_peak_usage() / 1024);
    $result['execution_time'] = sprintf("%01.3fs", microtime(true) - $GLOBALS['SYSTEM']['request_time']);
    if (empty($code)) {
        return $result;
    }
    // Data can change at any moment: the browser asks again each time, and a list that did not change answers 304.
    http_header_sent('json');
    header('Cache-Control: private, no-cache');
    if ($modified > 0) {
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
    }
    http_response_code($code);
    exit(json_encode($result, JSON_UNESCAPED_UNICODE));
}

function json_input($create_uuid = true) {
    $data = file_get_contents("php://input");
    $clean_data = strip_tags($data, "<h1><h2><h3><h4><h5><h6><b><strong><a><i><p><blockquote><ol><ul><li><br><img><figure><figcaption><video><source><iframe>");
    $result = json_decode($clean_data, true);
    if ($create_uuid && empty($result['uuid'])) {
        load_library('util');
        $result['uuid'] = generate_uuid();
    }
    return $result;
}   