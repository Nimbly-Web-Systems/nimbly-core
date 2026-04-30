<?php

/**
 * @doc * [jget varname] returns value of variable named `varname`
 * @doc * [jget varname.subkey] returns value of variable named `varname[subkey]`
 * @doc * [jget varname default=somevalue] if variable `varname` has no value, the default value `somevalue` is returned
 * @doc * [jget varname default=somevalue echo] echo outputs the value
 */
function jget_sc($params, $default = null) {
    $result = null;
    if (is_array($params)) {
        $key = current($params);
    } else {
        $key = $params;
    }
    $keys = explode('.', $key);

    if (count($keys) === 1) { 
        // fallback to normal get
        return get_variable($params, $default);
    }

    $a = $GLOBALS['SYSTEM']['variables'];
    foreach ($keys as $k) {

        $k = preg_replace('/[^a-zA-Z0-9_-]/', '_', $k);

        if (!array_key_exists($k, $a)) {
            return $default === null? get_param_value($params, "default") : $default;
        }

        if (is_scalar($a[$k])) {
            $result = $a[$k];
            break;
        } else if (is_array($a[$k])) {
            $a = $a[$k];
        } else {
            return $default === null? get_param_value($params, "default") : $default;
        }
    }

    if (empty($result)) {
        $result = get_param_value($params, "empty", $result);
    }

    if (get_param_value($params, "echo", false)) {
        echo $result;
        return;
    }
    return $result;
}




function jget_value($array, $id) {

}

function jget_variable($key, $default = null) {
    return jget_sc($key, $default);
}

    
