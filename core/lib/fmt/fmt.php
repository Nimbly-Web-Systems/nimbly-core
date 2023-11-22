<?php

load_library("get");

function fmt_sc($params) {
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
    
    switch ($type) {
        case 'date':
            $fmt = get_param_value($params, 'fmt', 'Y-m-d');
            return date($fmt, is_numeric($val)? $val : strtotime($val));
        default:
            if (is_array($val)) {
                return fmt_sc([implode(', ', $val)]);
            }
            if (is_object($val)) {
                return json_encode($val);
            }
            return strval($val);
    }
}