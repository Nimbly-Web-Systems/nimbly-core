<?php

/**
 * Nimbly CLI — test:setup command
 *
 * Creates the test role, test user, test-records resource and two seed records.
 * Idempotent: skips anything that already exists.
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
    fwrite(STDERR, "Error: .env not found.\n");
    exit(1);
}
foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    [$key, $val] = array_map('trim', explode('=', $line, 2) + [1 => '']);
    $_ENV[$key] = $val;
}
$_SERVER['PEPPER'] = $_ENV['PEPPER'] ?? '';
if (empty($_SERVER['PEPPER'])) {
    fwrite(STDERR, "Error: PEPPER not set in .env.\n");
    exit(1);
}

load_library('data');
load_library('encrypt');

cli_section('test:setup', true);

// ── Role ─────────────────────────────────────────────────────────────────────
if (data_exists('roles', 'test')) {
    echo "skip  role 'test' already exists\n";
} else {
    data_create('roles', 'test', [
        'name'        => 'Test',
        'description' => 'Automated test role — created and removed by test:setup / test:teardown',
        'features'    => 'view-admin-dashboard,view-nimblybar,manage-test-records,test',
    ]);
    echo "ok    created role 'test'\n";
}

// ── User ─────────────────────────────────────────────────────────────────────
$test_email = 'test@nimbly.dev';
$test_uuid  = md5($test_email);

if (data_exists('users', $test_uuid)) {
    echo "skip  user '$test_email' already exists\n";
} else {
    $salt = generate_salt();
    data_create('users', $test_uuid, [
        'email'    => $test_email,
        'roles'    => 'test',
        'salt'     => $salt,
        'password' => encrypt('testpass123', $salt),
    ]);
    echo "ok    created user '$test_email'\n";
}

// ── Resource ──────────────────────────────────────────────────────────────────
if (data_exists('test-records', '.meta')) {
    echo "skip  resource 'test-records' already exists\n";
} else {
    data_create_resource('test-records', [
        'fields' => [
            'title' => ['type' => 'text',     'name' => 'Title',       'required' => true],
            'score' => ['type' => 'number',   'name' => 'Score'],
            'notes' => ['type' => 'textarea', 'name' => 'Notes'],
        ],
    ]);
    echo "ok    created resource 'test-records'\n";
}

// ── Seed records ─────────────────────────────────────────────────────────────
$records = [
    'test-001' => ['title' => 'Alpha record', 'score' => '42', 'notes' => 'First test record'],
    'test-002' => ['title' => 'Beta record',  'score' => '7',  'notes' => 'Second test record'],
];

foreach ($records as $uuid => $data) {
    if (data_exists('test-records', $uuid)) {
        echo "skip  record '$uuid' already exists\n";
    } else {
        data_create('test-records', $uuid, $data);
        echo "ok    created record '$uuid'\n";
    }
}

// ── Thumbnail fixture ────────────────────────────────────────────────────────
$test_image_bytes = hex2bin(
    '89504e470d0a1a0a0000000d494844520000000a0000000a0802000000025058ea' .
    '000000097048597300000ec400000ec401952b0e1b0000001449444154189563e4' .
    '51b260c00d98f0c88d60690087de007ac66e42290000000049454e44ae426082'
) . 'nimbly-test-thumbnail-ratio';
$test_image_uuid = md5($test_image_bytes);
$test_image_path = data_path('.files', $test_image_uuid);

if (data_exists('.files_meta', $test_image_uuid) || file_exists($test_image_path)) {
    echo "skip  thumbnail fixture '$test_image_uuid' already exists\n";
} else {
    @mkdir(dirname($test_image_path), 0750, true);
    file_put_contents($test_image_path, $test_image_bytes);
    data_create('.files_meta', $test_image_uuid, [
        'name' => 'nimbly-test-thumbnail-ratio.png',
        'type' => 'image/png',
        'size' => strlen($test_image_bytes),
        'width' => 10,
        'height' => 10,
        'orientation' => 'landscape',
        'aspect_ratio' => 1,
    ]);
    echo "ok    created thumbnail fixture '$test_image_uuid'\n";
}

echo "\ntest:setup complete.\n";
