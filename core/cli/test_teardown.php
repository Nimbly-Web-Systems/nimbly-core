<?php

/**
 * Nimbly CLI — test:teardown command
 *
 * Removes the test role, test user, and test-records resource created by test:setup.
 */

if (php_sapi_name() !== 'cli') {
    die("nimbly.php must be run from the command line.\n");
}

if (!defined('BASE_DIR')) define('BASE_DIR', realpath(__DIR__ . '/../..') . '/');
require_once BASE_DIR . 'core/cli/helpers/output.php';

$GLOBALS['SYSTEM'] = [
    'file_base'  => BASE_DIR,
    'env_paths'  => ['ext', 'core'],
    'modules'    => ['root' => '/'],
    'variables'  => [],
    'uri'        => '',
];

require_once BASE_DIR . 'core/lib/find.php';
load_library('util');

$env_file = BASE_DIR . '.env';
if (!file_exists($env_file)) {
    die("Error: .env not found.\n");
}
foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    [$key, $val] = array_map('trim', explode('=', $line, 2) + [1 => '']);
    $_ENV[$key] = $val;
}
$_SERVER['PEPPER'] = $_ENV['PEPPER'] ?? '';

load_library('data');

cli_section('test:teardown', true);

// ── User ─────────────────────────────────────────────────────────────────────
$test_email = 'test@nimbly.dev';
$test_uuid  = md5($test_email);

if (!data_exists('users', $test_uuid)) {
    echo "skip  user '$test_email' not found\n";
} else {
    data_delete('users', $test_uuid);
    echo "ok    deleted user '$test_email'\n";
}

// ── Role ─────────────────────────────────────────────────────────────────────
if (!data_exists('roles', 'test')) {
    echo "skip  role 'test' not found\n";
} else {
    data_delete('roles', 'test');
    echo "ok    deleted role 'test'\n";
}

// ── Resource ──────────────────────────────────────────────────────────────────
if (!data_exists('test-records', '.meta')) {
    echo "skip  resource 'test-records' not found\n";
} else {
    data_delete('test-records');
    echo "ok    deleted resource 'test-records'\n";
}

echo "\ntest:teardown complete.\n";
