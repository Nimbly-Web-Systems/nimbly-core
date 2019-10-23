<?php

/**
 * @doc * [get-key varname key] returns value of array variable named `varname` at key `key`
 */
function get_key_sc($params, $default = null) {
    load_library('get');
    $var = get_variable(current($params));
    $key = end($params);
    if (empty($var[$key])) {
        return get_param_value($params, 'default', '');
    }
    return $var[$key];
}