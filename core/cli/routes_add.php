<?php

/**
 * Nimbly CLI - routes:sync command
 *
 * Usage: php core/cli/nimbly.php routes:sync [--order=200] [--dry-run] [path]
 *
 * Scans route.inc files in ext/uri and ext/modules module URI folders, then creates missing
 * .routes records for dynamic routes.
 */

if (php_sapi_name() !== 'cli') {
    die("routes_add.php must be run from the command line.\n");
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
require_once BASE_DIR . 'core/cli/helpers/output.php';

$env_file = BASE_DIR . '.env';
if (!file_exists($env_file)) {
    die("Error: .env not found. Run './nimbly system:setup' first.\n");
}

$env = [];
foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    [$key, $val] = array_map('trim', explode('=', $line, 2) + [1 => '']);
    $env[$key] = $val;
}
$_SERVER['PEPPER'] = $env['PEPPER'] ?? '';

load_library('data');

$order = 200;
$dry_run = false;
$scan_roots = [];

foreach (array_slice($argv, 2) as $arg) {
    if ($arg === '--dry-run') {
        $dry_run = true;
        continue;
    }
    if (str_starts_with($arg, '--order=')) {
        $order = (int)substr($arg, strlen('--order='));
        continue;
    }
    if ($arg !== '') {
        $scan_roots[] = $arg;
    }
}

if ($order < 1) {
    die("Error: --order must be a positive number.\n");
}

if (empty($scan_roots)) {
    $scan_roots = [
        'ext/uri',
        'ext/modules',
    ];
}

if (!data_exists('.routes', '.meta')) {
    if ($dry_run) {
        echo "Would create .routes resource.\n";
    } else {
        data_create_resource('.routes', routes_add_meta());
        cli_status('Created .routes resource');
    }
} else if (data_read('.routes', '.meta', 'fields') === false) {
    if ($dry_run) {
        echo "Would update .routes resource metadata.\n";
    } else {
        data_create('.routes', '.meta', routes_add_meta());
        cli_status('Updated .routes resource metadata');
    }
}

$route_files = [];
foreach ($scan_roots as $scan_root) {
    $root = routes_add_absolute_path($scan_root);
    if (!is_dir($root)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getFilename() === 'route.inc') {
            $route_files[] = $file->getPathname();
        }
    }
}

sort($route_files);

$created = 0;
$skipped = 0;
$warnings = 0;
$existing_routes = [];

foreach (data_read('.routes') as $existing_route) {
    if (!empty($existing_route['route']) && is_scalar($existing_route['route'])) {
        $existing_routes[(string)$existing_route['route']] = true;
    }
}

foreach ($route_files as $route_file) {
    $route = routes_add_route_from_file($route_file);
    if ($route === null) {
        $warnings++;
        echo "Warning: could not resolve route for $route_file\n";
        continue;
    }
    if (!str_contains($route, '(')) {
        $warnings++;
        echo "Warning: static route has route.inc, skipped: $route\n";
        continue;
    }
    if (!file_exists(dirname($route_file) . '/index.tpl')) {
        $warnings++;
        echo "Warning: route.inc has no sibling index.tpl, skipped: $route\n";
        continue;
    }

    $uuid = md5($route);
    if (data_exists('.routes', $uuid) || isset($existing_routes[$route])) {
        $skipped++;
        echo "Exists:  $route\n";
        continue;
    }

    if ($dry_run) {
        $created++;
        echo "Create:  $route order=$order\n";
        continue;
    }

    if (data_create('.routes', $uuid, ['route' => $route, 'order' => $order])) {
        $created++;
        $existing_routes[$route] = true;
        echo "Created: $route\n";
    } else {
        $warnings++;
        echo "Error: could not create route: $route\n";
    }
}

echo "\n";
echo ($dry_run ? "Would create" : "Created") . ": $created route(s)\n";
echo "Skipped: $skipped existing route(s)\n";
if ($warnings > 0) {
    echo "Warnings: $warnings\n";
}

function routes_add_absolute_path(string $path): string
{
    if (str_starts_with($path, '/')) {
        return $path;
    }
    return BASE_DIR . $path;
}

function routes_add_route_from_file(string $route_file): ?string
{
    $relative = str_replace('\\', '/', $route_file);
    $base = str_replace('\\', '/', BASE_DIR);

    if (str_starts_with($relative, $base)) {
        $relative = substr($relative, strlen($base));
    }

    $marker = '/uri/';
    $pos = strrpos($relative, $marker);
    if ($pos === false) {
        if (str_starts_with($relative, 'ext/uri/')) {
            $route = substr($relative, strlen('ext/uri/'));
        } else {
            return null;
        }
    } else {
        $route = substr($relative, $pos + strlen($marker));
    }

    $suffix = '/route.inc';
    if (!str_ends_with($route, $suffix)) {
        return null;
    }

    $route = substr($route, 0, -strlen($suffix));
    return trim($route, '/');
}

function routes_add_meta(): array
{
    return [
        'fields' => [
            'route' => [
                'name' => 'Route',
                'type' => 'text',
                'required' => true,
            ],
            'order' => [
                'name' => 'Order',
                'type' => 'number',
                'required' => true,
            ],
        ],
    ];
}
