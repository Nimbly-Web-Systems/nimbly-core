<?php

/**
 * Checks the managed page declarations and reports addresses that collide.
 *
 * Usage: php core/cli/nimbly.php pages:check
 *
 * Exit code 1 when it finds a problem.
 */

if (php_sapi_name() !== 'cli') {
    die("nimbly.php must be run from the command line.\n");
}
if (!defined('BASE_DIR')) define('BASE_DIR', realpath(__DIR__ . '/../..') . '/');

$GLOBALS['SYSTEM'] = [
    'file_base' => BASE_DIR,
    'env_paths' => ['ext', 'core'],
    'modules' => ['root' => '/'],
    'variables' => [],
    'uri' => '',
];
require_once BASE_DIR . 'core/lib/find.php';
load_libraries(['data', 'managed-pages']);

$errors = managed_pages_check();
if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "- {$error}\n");
    }
    exit(1);
}
echo "Managed page check passed.\n";
