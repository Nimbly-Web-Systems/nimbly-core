<?php

/**
 * Removes expired session files.
 *
 * Usage: php core/cli/nimbly.php sessions:prune [--dry-run]
 *
 * Prints the counts as JSON. With --dry-run it only counts.
 */

require_once __DIR__ . '/cli_bootstrap.inc';
load_library('session');
$counts = session_prune(in_array('--dry-run', $argv, true));
echo json_encode($counts, JSON_THROW_ON_ERROR) . "\n";
exit($counts['failed'] > 0 ? 1 : 0);
