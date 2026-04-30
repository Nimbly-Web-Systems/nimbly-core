<?php

load_library('set');

function sys_info_sc() {
   set_variable('mem_info', get_mem_info());
}

function get_mem_info() {       
    $data = explode("\n", trim(file_get_contents("/proc/meminfo")));
    $meminfo = array();
    foreach ($data as $line) {
        list($key, $val) = explode(":", $line);
        $v = trim($val);
        if (str_ends_with($v, 'kB')) {
            $v = 1024 * intval($v);
        }
        $meminfo[$key] = $v;
    }
    return $meminfo;
}