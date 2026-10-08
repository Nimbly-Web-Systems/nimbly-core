<?php

/**
 * Imports Apache access log lines from stdin into the request stats.
 *
 * Usage:
 *   zcat -f /var/log/apache2/site-access.log.1 | sudo -u www-data php core/cli/nimbly.php stats:import-apache
 *
 * --mode=enrich|full  enrich (default) takes only what never reaches PHP; full takes every line
 * --before=<ISO-8601> Only lines before this moment, e.g. 2026-09-25T02:02:00Z
 * --base=/subdir/     The site's base path on the server
 * --host=example.com  The site's host name; without it, the one in SITE_URL
 */

require_once __DIR__ . '/cli_bootstrap.inc';
load_library('stats');

$options = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--(mode|before|base|host)=(.*)$/', $arg, $match)) {
        $options[$match[1]] = $match[2];
    }
}
$mode = $options['mode'] ?? 'enrich';
$before = isset($options['before']) ? strtotime($options['before']) : null;
if (!in_array($mode, ['enrich', 'full'], true) || $before === false) {
    fwrite(STDERR, "Usage: stats:import-apache [--mode=enrich|full] [--before=ISO-8601] [--base=/subdir/] [--host=domain] < access.log\n");
    exit(2);
}

$lines = (function () {
    while (($line = fgets(STDIN)) !== false) {
        yield $line;
    }
})();
$import_options = array_intersect_key($options, array_flip(['base', 'host']));
echo json_encode(stats_import_apache($lines, $mode, $before, $import_options), JSON_THROW_ON_ERROR) . "\n";
