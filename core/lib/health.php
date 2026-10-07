<?php

/**
 * Health route for a load balancer or container check: 200 "ok" when PHP
 * answers and the data folder can be written, 503 otherwise.
 */
function health_sc($params = null): string
{
    $data_base = $GLOBALS['SYSTEM']['data_base'] ?? $GLOBALS['SYSTEM']['file_base'] . 'ext/data';
    $ok = is_dir($data_base) && is_writable($data_base);
    if (!headers_sent()) {
        http_response_code($ok ? 200 : 503);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
    }

    return $ok ? "ok\n" : "unavailable\n";
}
