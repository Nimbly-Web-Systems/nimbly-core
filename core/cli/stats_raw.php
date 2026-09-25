<?php

require_once __DIR__ . '/cli_bootstrap.inc';
load_library('stats');
$date = (string)($argv[1] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    fwrite(STDERR, "Usage: stats:raw YYYY-MM-DD [--apache]\n");
    exit(2);
}
try {
    $file = stats_raw_path(stats_dir(), $date, in_array('--apache', $argv, true) ? 'apache' : 'app');
    foreach (stats_read_lines($file, stats_key()) as $entry) {
        echo json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'stats:raw: ' . $e->getMessage() . "\n");
    exit(1);
}
