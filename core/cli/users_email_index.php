<?php

/**
 * Nimbly CLI - users:email-index command
 *
 * Usage: php core/cli/nimbly.php users:email-index [--yes]
 *
 * Adds email lookup metadata to the users resource and rebuilds its email index.
 */

if (php_sapi_name() !== 'cli') {
    die("nimbly.php must be run from the command line.\n");
}

if (!defined('BASE_DIR')) {
    define('BASE_DIR', realpath(__DIR__ . '/../..') . '/');
}

require_once BASE_DIR . 'core/cli/helpers/output.php';
require_once BASE_DIR . 'core/cli/helpers/users_email_index.php';

$yes = in_array('--yes', $argv, true) || in_array('-y', $argv, true);

users_email_index_bootstrap();
$state = users_email_index_collect();

if (!users_email_index_has_work($state) && empty($state['duplicates'])) {
    echo "Users email index is already configured — rebuilding index entries.\n";
} else {
    users_email_index_print_summary($state);
}

if (empty($state['exists'])) {
    exit(0);
}

if (!$yes) {
    echo "\nProceed with users email index migration? [y/N] ";
    $confirm = trim(fgets(STDIN));
    if (strtolower($confirm) !== 'y') {
        die("Aborted.\n");
    }
}

echo "\n=== Updating users email index ===\n";
$result = users_email_index_apply($state);
users_email_index_print_done($result);
