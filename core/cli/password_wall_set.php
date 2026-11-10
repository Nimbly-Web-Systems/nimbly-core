<?php

/**
 * Nimbly CLI — password-wall:set <name> [--features=a,b]
 *
 * Asks for the password and stores its hash, with the features it grants,
 * in ext/data/.password-wall/<name>. Running it again changes the password
 * and closes the wall for every earlier session.
 */

if (php_sapi_name() !== 'cli') {
    die("nimbly.php must be run from the command line.\n");
}

if (!defined('BASE_DIR')) define('BASE_DIR', realpath(__DIR__ . '/../..') . '/');

$GLOBALS['SYSTEM'] = [
    'file_base'  => BASE_DIR,
    'env_paths'  => ['ext', 'core'],
    'modules'    => ['root' => '/'],
    'variables'  => [],
    'uri'        => '',
];

require_once BASE_DIR . 'core/lib/find.php';
load_libraries(['util', 'data', 'encrypt']);
require_once BASE_DIR . 'core/lib/password-wall.php';

$args = array_slice($argv, 1);
$name = '';
$features = null;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--features=')) {
        $features = substr($arg, 11);
    } elseif ($arg !== 'password-wall:set' && !str_starts_with($arg, '-')) {
        $name = $arg;
    }
}
if (password_wall_name($name) === '') {
    fwrite(STDERR, "Usage: ./nimbly password-wall:set <name> [--features=a,b]\nName: lowercase letters, digits and dashes.\n");
    exit(1);
}

echo 'Password: ';
if (stream_isatty(STDIN)) system('stty -echo');
$password = trim((string)fgets(STDIN));
if (stream_isatty(STDIN)) system('stty echo');
echo "\n";
if (strlen($password) < 8) {
    fwrite(STDERR, "Error: use at least 8 characters.\n");
    exit(1);
}

$existing = data_read('.password-wall', $name);
$record = [
    'password' => encrypt($password, generate_salt()),
    'features' => $features ?? (is_array($existing) ? (string)($existing['features'] ?? '') : ''),
];
is_array($existing) ? data_update('.password-wall', $name, $record) : data_create('.password-wall', $name, $record);
echo "Password wall '$name' saved" . ($record['features'] !== '' ? " (features: {$record['features']})" : '') . ".\n";
