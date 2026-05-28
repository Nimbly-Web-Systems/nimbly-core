<?php

/**
 * Publishes the core schedule defaults into ext for app customization.
 *
 * Usage:
 *   php core/cli/nimbly.php schedule:publish
 *   php core/cli/nimbly.php schedule:publish --force
 */

if (php_sapi_name() !== 'cli') {
    die("schedule_publish.php must be run from the command line.\n");
}

if (!defined('BASE_DIR')) {
    define('BASE_DIR', realpath(__DIR__ . '/../..') . '/');
}
require_once BASE_DIR . 'core/cli/helpers/output.php';

$force = in_array('--force', $argv, true);

$files = [
    'schedule.inc',
    'schedule.prod.inc',
    'schedule.stage.inc',
];

$cli_dir = BASE_DIR . 'ext/cli';
if (!is_dir($cli_dir) && !mkdir($cli_dir, 0750, true) && !is_dir($cli_dir)) {
    die("Error: could not create ext/cli directory.\n");
}

foreach ($files as $file) {
    $src = BASE_DIR . 'core/cli/' . $file;
    $dst = BASE_DIR . 'ext/cli/' . $file;

    if (!file_exists($src)) {
        echo "Skipped: $file (not found in core)\n";
        continue;
    }

    if (file_exists($dst) && !$force) {
        echo "Skipped: ext/cli/$file (already exists — use --force to overwrite)\n";
        continue;
    }

    if (!copy($src, $dst)) {
        die("Error: could not copy $file.\n");
    }
    chmod($dst, 0640);
    echo "Published: ext/cli/$file\n";
}

cli_tip("Edit ext/cli/schedule.prod.inc and schedule.stage.inc for environment-specific commands.");
