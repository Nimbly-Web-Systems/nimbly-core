<?php

/**
 * @doc '[log text]' adds text to log file in data/.tmp/logs/system.log
 */
function log_sc($params) {
    log_system(current($params));
}

function log_system($str) {
    if (is_array($str)) {
        $str = print_r($str, true);
    }
    error_log($str);
}
