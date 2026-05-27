<?php

/**
 * Nimbly CLI — user:create command
 *
 * Usage: php core/cli/nimbly.php user:create
 *
 * Creates a new user account with a specified role.
 */

if (php_sapi_name() !== 'cli') {
    die("nimbly.php must be run from the command line.\n");
}

if (!defined('BASE_DIR')) define('BASE_DIR', realpath(__DIR__ . '/../..') . '/');
require_once BASE_DIR . 'core/cli/helpers/output.php';

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
    die("Error: .env not found. Run './nimbly init' first.\n");
}

$env = [];
foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    [$key, $val] = array_map('trim', explode('=', $line, 2) + [1 => '']);
    $env[$key] = $val;
}

$pepper = $env['PEPPER'] ?? '';
if (empty($pepper)) {
    die("Error: PEPPER not set in .env. Run './nimbly init' first.\n");
}

$_SERVER['PEPPER'] = $pepper;
load_library('data');
load_library('encrypt');

// -----------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------

function nb_prompt(string $question, string $default = ''): string {
    $hint = $default !== '' ? " [$default]" : '';
    echo $question . $hint . ': ';
    $input = fgets(STDIN);
    if ($input === false) {
        fwrite(STDERR, "\nError: input is required.\n");
        exit(1);
    }
    $value = trim($input);
    return $value !== '' ? $value : $default;
}

function nb_prompt_password(string $question): string {
    echo $question . ': ';
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        system('stty -echo');
    }
    $input = fgets(STDIN);
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        system('stty echo');
    }
    echo "\n";
    if ($input === false) {
        fwrite(STDERR, "Error: input is required.\n");
        exit(1);
    }
    $value = trim($input);
    return $value;
}

// -----------------------------------------------------------------------
// Resolve available roles
// -----------------------------------------------------------------------

$roles_dir = BASE_DIR . 'ext/data/roles/';
$available_roles = [];
if (is_dir($roles_dir)) {
    foreach (glob($roles_dir . '*') as $f) {
        $name = basename($f);
        if ($name !== '.meta') {
            $available_roles[] = $name;
        }
    }
}

// -----------------------------------------------------------------------
// Prompt
// -----------------------------------------------------------------------

cli_section('User details', true);

$email = nb_prompt('Email');
while (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Please enter a valid email address.\n";
    $email = nb_prompt('Email');
}

$default_role = in_array('admin', $available_roles) ? 'admin' : ($available_roles[0] ?? 'admin');
if ($available_roles) {
    cli_tip('Available roles: ' . implode(', ', $available_roles));
}
$role = nb_prompt('Role', $default_role);

$password = nb_prompt_password('Password');
while (strlen($password) < 8) {
    echo "Password must be at least 8 characters.\n";
    $password = nb_prompt_password('Password');
}

// -----------------------------------------------------------------------
// Create user
// -----------------------------------------------------------------------

$user_uuid = md5($email);
if (data_exists('users', $user_uuid)) {
    die("User $email already exists.\n");
}

cli_section('Result', true);

$salt = salt_sc();
data_create('users', $user_uuid, [
    'email'    => $email,
    'roles'    => $role . ',user',
    'salt'     => $salt,
    'password' => encrypt($password, $salt),
]);

echo "Created user: $email (role: $role)\n";
