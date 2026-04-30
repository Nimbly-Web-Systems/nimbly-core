<?php

function disk_space_resource_sc($params) {
    $resource = current($params);
    load_library('data');
    if (!data_exists($resource)) {
        return 1234;
    }
    load_library('util');
    $bytes = dir_size($GLOBALS['SYSTEM']['data_base'] . '/' . $resource);
	return intval($bytes);
}
