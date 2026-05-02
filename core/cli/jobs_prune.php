<?php

/**
 * Deletes completed jobs older than a given number of days.
 *
 * Usage:
 *   php core/cli/nimbly.php jobs:prune              (done jobs older than 30 days)
 *   php core/cli/nimbly.php jobs:prune --days=7
 *   php core/cli/nimbly.php jobs:prune --dry-run
 */

$GLOBALS['SYSTEM'] = [
    'file_base' => BASE_DIR,
    'env_paths' => ['ext', 'core'],
    'modules'   => ['root' => '/'],
    'variables' => [],
    'uri'       => '',
];

require_once BASE_DIR . 'core/lib/find.php';

load_library('data');

$dry_run = in_array('--dry-run', $argv, true);

$days = 30;
foreach ($argv as $arg) {
    if (preg_match('/^--days=(\d+)$/', $arg, $m)) {
        $days = (int)$m[1];
        break;
    }
}

$cutoff = time() - ($days * 86400);

$jobs = data_read('.jobs');
if (empty($jobs) || !is_array($jobs)) {
    echo "No jobs found.\n";
    exit(0);
}

$pruned  = 0;
$kept    = 0;
$skipped = 0;

foreach ($jobs as $uuid => $job) {
    if ($uuid === '.meta') {
        continue;
    }

    if (($job['status'] ?? '') !== 'done') {
        $skipped++;
        continue;
    }

    $completed_at = (int)($job['completed_at'] ?? 0);
    if ($completed_at === 0 || $completed_at > $cutoff) {
        $kept++;
        continue;
    }

    if (!$dry_run) {
        data_delete('.jobs', $uuid);
    }
    $pruned++;
}

$mode = $dry_run ? 'dry run' : 'live';
printf("%-12s %s  (done jobs older than %d days)\n", 'Mode:', $mode, $days);
printf("%-12s %d\n", 'Pruned:', $pruned);
printf("%-12s %d\n", 'Kept:', $kept);
printf("%-12s %d\n", 'Skipped:', $skipped);
