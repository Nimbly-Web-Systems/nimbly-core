<?php

/*
 * Reads Apache access log lines from stdin, e.g. as root:
 *   zcat -f /var/log/apache2/site-access.log.1 | sudo -u www-data ./nimbly stats:import-apache
 * Options: --mode=enrich|full  --before=2026-09-25T02:02:00Z  --base=/subdir/  --host=example.com
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
