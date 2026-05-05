<?php

/**
 * Nimbly CLI - migrate-lib-flat command
 *
 * Usage: php core/cli/nimbly.php migrate-lib-flat [--yes]
 *
 * Migrates single-file library directories from:
 *
 *   lib/name/name.php
 *
 * to:
 *
 *   lib/name.php
 *
 * Directories with support files or subdirectories are left untouched.
 */

if (php_sapi_name() !== 'cli') {
    die("migrate_lib.php must be run from the command line.\n");
}

if (!defined('BASE_DIR')) {
    define('BASE_DIR', realpath(__DIR__ . '/../..') . '/');
}

require_once BASE_DIR . 'core/cli/helpers/migrate_lib.php';

$yes = in_array('--yes', $argv, true) || in_array('-y', $argv, true);
[$moves, $skipped] = migrate_lib_collect();

if (empty($moves)) {
    echo "No single-file library directories to migrate.\n";
    if (!empty($skipped)) {
        echo "\nSkipped:\n";
        foreach ($skipped as $item) {
            echo "  - {$item}\n";
        }
    }
    exit(0);
}

echo "Single-file library directories to migrate:\n\n";
foreach ($moves as [$from, $to]) {
    echo '  ' . str_replace(BASE_DIR, '', $from) . "\n";
    echo '    -> ' . str_replace(BASE_DIR, '', $to) . "\n";
}

if (!empty($skipped)) {
    echo "\nSkipped:\n";
    foreach ($skipped as $item) {
        echo "  - {$item}\n";
    }
}

if (!$yes) {
    echo "\nProceed? [y/N] ";
    $confirm = trim(fgets(STDIN));
    if (strtolower($confirm) !== 'y') {
        die("Aborted.\n");
    }
}

$migrated = migrate_lib_apply($moves);

echo "\nMigrated {$migrated} library entr" . ($migrated === 1 ? "y" : "ies") . ".\n";
