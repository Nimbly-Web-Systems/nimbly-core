<?php

/**
 * Nimbly CLI - jobs command
 *
 * Usage: php core/cli/nimbly.php jobs:run [limit]
 */

if (php_sapi_name() !== 'cli') {
    die("jobs.php must be run from the command line.\n");
}

require_once __DIR__ . '/cli_bootstrap.inc';

load_library('job');

$limit = isset($argv[2]) ? max(1, (int)$argv[2]) : 1;
$result = job_run_queued($limit);

printf(
    "Jobs processed: %d, done: %d, failed: %d\n",
    $result['processed'],
    $result['done'],
    $result['failed']
);
