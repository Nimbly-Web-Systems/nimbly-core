<?php

/**
 * Nimbly CLI - jobs command
 *
 * Usage: php core/cli/nimbly.php jobs:run [limit]
 */

if (php_sapi_name() !== 'cli') {
    die("jobs.php must be run from the command line.\n");
}

if (!defined('BASE_DIR')) {
    define('BASE_DIR', realpath(__DIR__ . '/../..') . '/');
}

$GLOBALS['SYSTEM'] = [
    'file_base'  => BASE_DIR,
    'env_paths'  => ['ext', 'core'],
    'modules'    => ['root' => '/'],
    'variables'  => [],
    'uri'        => '',
];

require_once BASE_DIR . 'core/lib/find.php';

$env_file = BASE_DIR . '.env';
if (file_exists($env_file)) {
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        [$key, $val] = array_map('trim', explode('=', $line, 2) + [1 => '']);
        $_SERVER[$key] = $val;
    }
}

load_library('job');

$limit = isset($argv[2]) ? max(1, (int)$argv[2]) : 1;
$result = job_run_queued($limit);

printf(
    "Jobs processed: %d, done: %d, failed: %d\n",
    $result['processed'],
    $result['done'],
    $result['failed']
);
