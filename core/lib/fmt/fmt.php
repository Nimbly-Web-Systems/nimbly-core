<?php

load_library("get");
load_library("set");

function fmt_sc($params) {
    $result = "";
    $var = get_param_value($params, 'var');
    if (empty($var)) {
        $val = get_param_value($params, 'val', current($params));
    } else {
        $val = get_variable($var);
    }
    $type = get_param_value($params, 'type', end($params)) ?? 'text';

    if (empty($val) && $type !== 'boolean') {
        return get_param_value($params, 'empty', '(empty)');
    }

    $max_length = intval(get_param_value($params, 'max_length') ?? 0);
    
    switch ($type) {
        case 'html':
            $result = trim(strip_tags($val));
            break;
        case 'date':
            $result = date((string)get_param_value($params, 'fmt', 'Y-m-d'), is_numeric($val)? $val : strtotime($val));
            break;
        case 'ago':
            $result = ago(is_numeric($val)? $val : strtotime($val));
            break;
        case 'json':
            $result = json_encode($val, JSON_UNESCAPED_UNICODE);
            break;
        case 'bytes':
            $result = fmt_bytes($val, 1);
            break;
        case 'boolean':
            $bools = explode('|', (string)get_param_value($params, 'boolean', 'yes|no'), 2);    
            $result = empty($val)? $bools[1] : $bools[0];
            break;
        case 'image':
            set_variable('_img_uuid', is_array($val)? current($val) : $val, true);
            return run_buffered(dirname(__FILE__) . '/image.tpl');
        case 'file':
            set_variable('_file_uuid', is_array($val)? current($val) : $val, true);
            return run_buffered(dirname(__FILE__) . '/file.tpl');
            break;
        default:
            if (is_array($val)) {
                $result =  fmt_sc([implode(', ', $val)]);
            } else if (is_object($val)) {
                $result =  json_encode($val);
            } else $result =  strval($val);
            break;
    }
    
    if ($max_length > 0 && mb_strlen($result) > $max_length) {
        $result = mb_substr($result, 0, $max_length) . '…';        
    }

    return $result;
}

function ago($dt) {
    $now = time();
    $elapsed = $now - $dt;
    $days = $elapsed / (24*60*60);
    $years = $days / 365;
    if ($years > 1) {
        return $years < 2 ? '1 year ago' : round($years) . ' years ago';
    }
    $months = $days / 30;
    if ($months > 1) {
        return $months < 2? '1 month ago' : round($months) . ' months ago';
    }
    $weeks = $days / 7;
    if ($weeks > 1) {
        return $weeks < 2? '1 week ago' : round($weeks) . ' weeks ago';
    }
    if ($days > 1) {
        return $days < 2? '1 day ago' : round($days) . ' days ago';
    }
    $hours = $elapsed / (60*60);
    if ($hours > 1) {
        return $hours < 2? '1 hour ago' : round($hours) . ' hours ago';
    }
    $minutes = $elapsed / 60;
    if ($minutes > 1) {
        return $minutes < 2? '1 minute ago' : round($minutes) . ' minutes ago';
    }
    return $elapsed < 2? '1 second ago' : round($elapsed) . ' seconds ago';       
}

function fmt_bytes($bytes, $decimals = 2){
    if (!is_numeric($bytes)) {
        $bytes = 0;
    }
    $size = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}