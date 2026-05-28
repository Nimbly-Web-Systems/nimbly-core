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

$src = BASE_DIR . 'core/cli/schedule.inc';
$dst = BASE_DIR . 'ext/cli/schedule.inc';
$force = in_array('--force', $argv, true);

if (!file_exists($src)) {
    die("Error: core schedule defaults not found.\n");
}

if (!is_dir(dirname($dst)) && !mkdir(dirname($dst), 0750, true) && !is_dir(dirname($dst))) {
    die("Error: could not create ext/cli directory.\n");
}

if (file_exists($dst) && !$force) {
    echo "Skipped: ext/cli/schedule.inc already exists.\n";
    cli_tip("Use --force to overwrite it with the core defaults.");
    exit(0);
}

if (!copy($src, $dst)) {
    die("Error: could not copy schedule defaults.\n");
}

chmod($dst, 0640);
echo "Published: ext/cli/schedule.inc\n";
