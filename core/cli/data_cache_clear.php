<?php

/**
 * Throws away the cached query results of every resource; the next read
 * builds them again.
 *
 * Usage: php core/cli/nimbly.php data:cache:clear
 *
 * Needed after a record file was edited in place by hand: a cached list does
 * not see that until the next write to its resource. Saving through the site,
 * the API, Git or ext:sync needs no clearing.
 */

require_once __DIR__ . '/cli_bootstrap.inc';
load_library('data');
echo 'Data cache cleared: ' . data_cache_clear() . " files\n";
