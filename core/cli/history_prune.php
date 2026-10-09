<?php

/**
 * Removes record history older than a given number of days.
 *
 * Usage:
 *   php core/cli/nimbly.php history:prune              (changes older than 90 days)
 *   php core/cli/nimbly.php history:prune --days=30
 *   php core/cli/nimbly.php history:prune --dry-run
 */

require_once __DIR__ . '/cli_bootstrap.inc';
load_library('data');
load_library('history');

$dry_run = in_array('--dry-run', $argv, true);

$days = 90;
foreach ($argv as $arg) {
    if (preg_match('/^--days=(\d+)$/', $arg, $m)) {
        $days = (int)$m[1];
        break;
    }
}

$pruned = history_prune($days, $dry_run);

if ($dry_run) {
    printf("%-12s %s  (changes older than %d days)\n", 'Mode:', 'dry run', $days);
}
printf("%-12s %d\n", 'Pruned:', $pruned);
