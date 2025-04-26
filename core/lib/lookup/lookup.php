<?php

load_library('util');
load_library('data');

/*
 * @doc `[lookup resource.uuid.key]` gets value of field key for a specific resource, 
 * @doc e.g. `[lookup users.1234.fullname]` to get the fullname field
 */
function lookup_sc($params) {
    $parts = dot2rs(current($params));
    if (!$parts) {
        return;
    }
    $resource = $parts[0];
    $uuid = $parts[1];
    $key = $parts[2];
    $empty = get_param_value($params, 'empty');
    $v = lookup_data($resource, $uuid, $key);

    if (!empty($v)) {
        echo $v;
    } else if ($empty) {
        echo $empty;
    }
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
