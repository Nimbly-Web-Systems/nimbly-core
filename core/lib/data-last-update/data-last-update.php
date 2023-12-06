<?php

load_library('last-update');
load_library('data');

function data_last_update_sc($params) {
    $resource = current($params);
    if (data_exists($resource)) {
        $path = $GLOBALS['SYSTEM']['data_base'] . '/' . $resource;
        return find_latest_time($path);  
    }
    return 0;
}