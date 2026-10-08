<?php

/**
 * Archives every finished day of request stats and refreshes the summaries
 * of the months they belong to.
 *
 * Usage: php core/cli/nimbly.php stats:rollup [--rebuild]
 *
 * Prints the archived dates as JSON. The scheduler runs it every hour.
 */

require_once __DIR__ . '/cli_bootstrap.inc';
load_library('stats');
try {
    $dates = stats_rollup(null, in_array('--rebuild', $argv, true));
} catch (Throwable $e) {
    fwrite(STDERR, 'stats:rollup: ' . $e->getMessage() . "\n");
    exit(1);
}
echo json_encode(['locked' => $dates === null, 'archived' => $dates ?? []], JSON_THROW_ON_ERROR) . "\n";
exit(0);
