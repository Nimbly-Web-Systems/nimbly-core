<?php

/**
 * @doc * [get-key varname key] returns value of array variable named `varname` at key `key`
 */
function get_key_sc($params, $default = null) {
    load_library('get');

    $varname = current($params);
    $key = next($params);

    $data = get_variable($varname);
    $default = get_param_value($params, 'default', $default ?? '');

    if (!is_array($data)) {
        return $default;
    }

    if (isset($data[$key]) && is_scalar($data[$key])) {
        return $data[$key];
    }

    if (strpos($key, '.') !== false) {
        $parts = explode('.', $key);
        foreach ($parts as $k) {
            if (!array_key_exists($k, $data)) {
                return $default;
            }
            $data = $data[$k];
        }
        if (is_scalar($data)) {
            return $data;
        }           
    }

    return $default;
}