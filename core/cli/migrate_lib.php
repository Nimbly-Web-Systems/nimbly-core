<?php

/**
 * Nimbly CLI - migrate-lib command
 *
 * Usage: php core/cli/nimbly.php migrate-lib [--yes]
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

$yes = in_array('--yes', $argv, true) || in_array('-y', $argv, true);
$roots = [
    BASE_DIR . 'core/lib',
    BASE_DIR . 'ext/lib',
];

foreach (glob(BASE_DIR . 'core/modules/*/lib') ?: [] as $root) {
    $roots[] = $root;
}
foreach (glob(BASE_DIR . 'ext/modules/*/lib') ?: [] as $root) {
    $roots[] = $root;
}

$moves = [];
$skipped = [];

foreach ($roots as $root) {
    if (!is_dir($root)) {
        continue;
    }
    foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $name = basename($dir);
        $from = $dir . '/' . $name . '.php';
        $to = dirname($dir) . '/' . $name . '.php';
        $files = glob($dir . '/*') ?: [];

        if (!file_exists($from)) {
            continue;
        }
        if (count($files) !== 1) {
            $skipped[] = str_replace(BASE_DIR, '', $dir) . ' (support files present)';
            continue;
        }
        if (file_exists($to)) {
            $skipped[] = str_replace(BASE_DIR, '', $dir) . ' (target exists)';
            continue;
        }

        $moves[] = [$from, $to, $dir];
    }
}

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

$migrated = 0;
foreach ($moves as [$from, $to, $dir]) {
    if (!rename($from, $to)) {
        echo "Failed: " . str_replace(BASE_DIR, '', $from) . "\n";
        continue;
    }
    @rmdir($dir);
    $migrated++;
}

echo "\nMigrated {$migrated} library entr" . ($migrated === 1 ? "y" : "ies") . ".\n";
