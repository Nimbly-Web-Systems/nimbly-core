<?php

/**
 * Nimbly CLI — install-module command
 *
 * Usage: php core/cli/nimbly.php install-module <name>
 *
 * Looks for <name>/.install.inc in ext/modules (then core/modules as fallback)
 * and executes it.
 */

if (php_sapi_name() !== 'cli') {
    die("nimbly.php must be run from the command line.\n");
}

if (!defined('BASE_DIR')) define('BASE_DIR', realpath(__DIR__ . '/../..') . '/');

// -----------------------------------------------------------------------
// Bootstrap
// -----------------------------------------------------------------------

$GLOBALS['SYSTEM'] = [
    'file_base'  => BASE_DIR,
    'env_paths'  => ['ext', 'core'],
    'modules'    => ['root' => '/'],
    'variables'  => [],
    'uri'        => '',
];

require_once BASE_DIR . 'core/lib/find.php';
load_library('salt');

$env_file = BASE_DIR . '.env';
if (!file_exists($env_file)) {
    die("Error: .env not found. Run 'php core/cli/nimbly.php setup' first.\n");
}

$env = [];
foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    [$key, $val] = array_map('trim', explode('=', $line, 2) + [1 => '']);
    $env[$key] = $val;
}

$pepper = $env['PEPPER'] ?? '';
if (empty($pepper)) {
    die("Error: PEPPER not set in .env. Run 'php core/cli/nimbly.php setup' first.\n");
}

$_SERVER['PEPPER'] = $pepper;
load_library('data');
load_library('encrypt');

// -----------------------------------------------------------------------
// Resolve module name
// -----------------------------------------------------------------------

$module = $argv[2] ?? null;

if (empty($module)) {
    die("Usage: php core/cli/nimbly.php install-module <name>\n");
}

// -----------------------------------------------------------------------
// Find and run .install.inc
// -----------------------------------------------------------------------

$install_file = find_path($module, 'modules', '.install.inc');

if ($install_file === false) {
    die("Module '$module' not found or has no .install.inc.\n");
}

echo "Installing module: $module\n";
echo "From: $install_file\n\n";

$ok = require_once $install_file;

if ($ok) {
	echo 'Module `' . $module . '` installed succesfully';
} else {
	echo 'Could not install module `' . $module . '`';
}

echo "\nDone.\n";
