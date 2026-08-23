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
    error_log('Nimbly: ' . $str);
}

function log_system_event(string $event, array $context = []) {
    $safe_context = [];
    foreach (['route_pattern', 'reason', 'stage'] as $key) {
        if (isset($context[$key]) && is_scalar($context[$key])) {
            $safe_context[$key] = (string)$context[$key];
        }
    }
    log_system('event=' . $event . ' ' . json_encode($safe_context, JSON_UNESCAPED_SLASHES));
}
