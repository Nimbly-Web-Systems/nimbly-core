<?php

load_library("get");

function fmt_sc($params) {
    $result = "";
    $var = get_param_value($params, 'var');
    if (empty($var)) {
        $val = get_param_value($params, 'val', current($params));
    } else {
        $val = get_variable($var);
    }
    $type = get_param_value($params, 'type', end($params)) ?? 'text';
    if (empty($val)) {
        return get_param_value($params, 'empty', '(empty)');
    }

    $max_length = intval(get_param_value($params, 'max_length') ?? 0);
    
    
    switch ($type) {
        case 'html':
            $result = trim(strip_tags($val));
            break;
        case 'date':
            $result = date(get_param_value($params, 'fmt', 'Y-m-d'), is_numeric($val)? $val : strtotime($val));
            break;
        default:
            if (is_array($val)) {
                $result =  fmt_sc([implode(', ', $val)]);
            } else if (is_object($val)) {
                $result =  json_encode($val);
            } else $result =  strval($val);
            break;
    }
    
    if ($max_length > 0 && strlen($result) > $max_length) {
        $result = substr($result, 0, $max_length) . '…';        
    }

    return $result;
}