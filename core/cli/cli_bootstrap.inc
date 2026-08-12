<?php

if (php_sapi_name() !== 'cli') {
    die("CLI bootstrap may only be loaded from the command line.\n");
}

if (!defined('BASE_DIR')) {
    define('BASE_DIR', realpath(__DIR__ . '/../..') . '/');
}

$GLOBALS['SYSTEM'] = $GLOBALS['SYSTEM'] ?? [
    'file_base' => BASE_DIR,
    'env_paths' => ['ext', 'core'],
    'modules' => ['root' => '/'],
    'variables' => [],
    'uri' => '',
];

require_once BASE_DIR . 'core/lib/find.php';
load_libraries(['util', 'run']);

$env_file = BASE_DIR . '.env';
if (file_exists($env_file)) {
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2) + [1 => '']);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
