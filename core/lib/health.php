<?php

/**
 * Health route for a load balancer, container check or uptime monitor:
 * 200 "ok" when PHP answers, the data folder can be written, and both the
 * web PHP and the scheduler's PHP have the extensions Nimbly needs;
 * 503 otherwise.
 */
function health_sc($params = null): string
{
    $data_base = $GLOBALS['SYSTEM']['data_base'] ?? $GLOBALS['SYSTEM']['file_base'] . 'ext/data';
    $ok = is_dir($data_base) && is_writable($data_base)
        && health_missing_extensions() === []
        && health_scheduler_missing_extensions($data_base) === [];
    if (!headers_sent()) {
        http_response_code($ok ? 200 : 503);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
    }

    return $ok ? "ok\n" : "unavailable\n";
}

/**
 * The extensions every Nimbly site needs that this PHP lacks, and 'avif'
 * when it cannot read AVIF images. A release upgrade can remove them while
 * PHP itself keeps answering.
 */
function health_missing_extensions(): array
{
    $required = ['mbstring', 'curl', 'openssl', 'dom', 'gd', 'zip', 'gmp'];
    $missing = array_values(array_filter($required, fn($extension) => !extension_loaded($extension)));
    load_library('thumbnail');
    if (thumbnail_avif_reader() === '') {
        $missing[] = 'avif';
    }
    return $missing;
}

/**
 * What the scheduler's command-line PHP lacked on its last run; it records
 * this in its state because it can be a different PHP than the web one.
 */
function health_scheduler_missing_extensions(string $data_base): array
{
    $state = json_decode((string)@file_get_contents($data_base . '/.state/schedule'), true);
    $missing = is_array($state) ? ($state['php_missing'] ?? []) : [];
    return is_array($missing) ? $missing : [];
}
