<?php

/*
 * @doc `[lookup resource uuid key]` gets value of field key for a specific resource, 
 * @doc e.g. `[lookup users admin@local.test fullname]` to get the fullname field
 */
function lookup_sc($params) {
    if (count($params) === 1) {
        //dot notation like users.1.name
        $params = explode('.', current($params));
    }

    if (count($params) < 3) {
        return;
    }

    $resource = get_param_value($params, "resource", current($params));
    $uuid = get_param_value($params, "uuid", next($params));
    $key = get_param_value($params, "key", next($params));

    $v = lookup_data($resource, $uuid, $key);
    echo $v;
}

function lookup_data($resource, $uuid, $key, $default = '') {
    // check if value is already stored in memory
    $var = "data." . trim($resource, '.');
    if (isset($GLOBALS['SYSTEM']['variables'][$var])) {
        $data = $GLOBALS['SYSTEM']['variables'][$var];
        if (isset($data[$uuid][$key])) {
            return $data[$uuid][$key];
        }
    }

    //read it from data file
    $result = data_read($resource, $uuid, $key);
    return $result ?? $default;
}
