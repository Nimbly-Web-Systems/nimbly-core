<?php

/**
 * Nimbly CLI - architecture:check command
 *
 * Usage:
 *   php core/cli/nimbly.php architecture:check [--strict] [--path=ext]
 *   php core/cli/architecture_check.php [--strict] [--path=/tmp/project/ext]
 */

if (php_sapi_name() !== 'cli') {
    die("architecture_check.php must be run from the command line.\n");
}

if (!defined('BASE_DIR')) {
    define('BASE_DIR', realpath(__DIR__ . '/../..') . '/');
}

$options = architecture_check_options($argv ?? []);
$ext_path = architecture_check_ext_path($options['path']);
$warnings = [];

if (!is_dir($ext_path)) {
    fwrite(STDERR, "Error: ext path does not exist: {$ext_path}\n");
    exit(2);
}

architecture_check_php_libraries($ext_path, $warnings);
architecture_check_dynamic_routes($ext_path, $warnings);
architecture_check_templates($ext_path, $warnings);

if (empty($warnings)) {
    echo "Architecture check passed: no warnings.\n";
    exit(0);
}

foreach ($warnings as $warning) {
    echo "Warning: {$warning}\n";
}

echo "\nArchitecture check found " . count($warnings) . " warning(s).\n";
exit($options['strict'] ? 1 : 0);

function architecture_check_options(array $argv): array
{
    $options = [
        'path' => 'ext',
        'strict' => false,
    ];

    $args = array_slice($argv, 1);
    if (($args[0] ?? '') === 'architecture:check') {
        array_shift($args);
    }

    foreach ($args as $arg) {
        if ($arg === '--strict') {
            $options['strict'] = true;
            continue;
        }
        if (str_starts_with($arg, '--path=')) {
            $options['path'] = substr($arg, strlen('--path='));
            continue;
        }
        if ($arg !== '') {
            $options['path'] = $arg;
        }
    }

    return $options;
}

function architecture_check_ext_path(string $path): string
{
    if (!str_starts_with($path, '/')) {
        $path = BASE_DIR . $path;
    }

    $path = rtrim($path, '/');
    if (is_dir($path . '/ext')) {
        return realpath($path . '/ext') ?: $path . '/ext';
    }

    return realpath($path) ?: $path;
}

function architecture_check_php_libraries(string $ext_path, array &$warnings): void
{
    $roots = [
        $ext_path . '/lib',
        $ext_path . '/modules',
    ];

    foreach (architecture_check_files($roots, '/\.php$/') as $file) {
        $relative = architecture_check_relative($ext_path, $file);
        if (!architecture_check_is_library_path($relative)) {
            continue;
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            continue;
        }

        $matches = architecture_check_html_string_matches($contents);
        foreach ($matches as $line => $snippet) {
            $warnings[] = "{$relative}:{$line} contains HTML markup in a PHP library string (`{$snippet}`). Move markup to a .tpl file and keep PHP for data preparation.";
        }
    }
}

function architecture_check_is_library_path(string $relative): bool
{
    if (str_starts_with($relative, 'lib/')) {
        return true;
    }

    return (bool)preg_match('#^modules/[^/]+/lib/#', $relative);
}

function architecture_check_html_string_matches(string $contents): array
{
    $tokens = token_get_all($contents);
    $matches = [];
    $html_pattern = '#</?(a|article|aside|button|div|figure|footer|form|h[1-6]|header|img|input|label|li|main|nav|ol|option|p|section|select|span|svg|table|tbody|td|textarea|th|thead|tr|ul)\b#i';

    foreach ($tokens as $token) {
        if (!is_array($token)) {
            continue;
        }

        [$type, $value, $line] = $token;
        if (!in_array($type, [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
            continue;
        }

        if (preg_match($html_pattern, $value, $match)) {
            $snippet = trim(str_replace(["\n", "\r", "\t"], ' ', $match[0]));
            $matches[$line] = $snippet;
        }
    }

    return $matches;
}

function architecture_check_dynamic_routes(string $ext_path, array &$warnings): void
{
    $route_files = architecture_check_files([
        $ext_path . '/uri',
        $ext_path . '/modules',
    ], '#/route\.inc$#');

    $existing_routes = architecture_check_existing_routes($ext_path);

    foreach ($route_files as $route_file) {
        $route = architecture_check_route_from_file($ext_path, $route_file);
        $relative = architecture_check_relative($ext_path, $route_file);
        $contents = (string)file_get_contents($route_file);

        if ($route === null) {
            $warnings[] = "{$relative} could not be resolved to a route.";
            continue;
        }

        if (!str_contains($route, '(')) {
            $warnings[] = "{$relative} is a static route using route.inc. Static routes should use index.tpl only.";
            continue;
        }

        if (!file_exists(dirname($route_file) . '/index.tpl') && str_contains($contents, 'router_accept')) {
            $warnings[] = "{$relative} calls router_accept() but has no sibling index.tpl to render.";
            continue;
        }

        if (!isset($existing_routes[$route])) {
            $warnings[] = "{$relative} defines dynamic route `{$route}` but ext/data/.routes has no matching record. Run `./nimbly routes:add`.";
        }
    }
}

function architecture_check_existing_routes(string $ext_path): array
{
    $routes = [];
    $routes_path = $ext_path . '/data/.routes';
    if (!is_dir($routes_path)) {
        return $routes;
    }

    foreach (glob($routes_path . '/*') ?: [] as $file) {
        if (!is_file($file) || basename($file) === '.meta') {
            continue;
        }

        $data = json_decode((string)file_get_contents($file), true);
        if (!is_array($data) || empty($data['route']) || !is_scalar($data['route'])) {
            continue;
        }

        $routes[trim(str_replace('\\/', '/', (string)$data['route']), '/')] = true;
    }

    return $routes;
}

function architecture_check_route_from_file(string $ext_path, string $route_file): ?string
{
    $relative = architecture_check_relative($ext_path, $route_file);
    $marker = '/uri/';

    if (str_starts_with($relative, 'uri/')) {
        $route = substr($relative, strlen('uri/'));
    } else {
        $pos = strrpos($relative, $marker);
        if ($pos === false) {
            return null;
        }
        $route = substr($relative, $pos + strlen($marker));
    }

    $suffix = '/route.inc';
    if (!str_ends_with($route, $suffix)) {
        return null;
    }

    return trim(substr($route, 0, -strlen($suffix)), '/');
}

function architecture_check_templates(string $ext_path, array &$warnings): void
{
    foreach (architecture_check_files([$ext_path . '/tpl', $ext_path . '/uri', $ext_path . '/modules'], '/\.tpl$/') as $file) {
        $relative = architecture_check_relative($ext_path, $file);
        $contents = (string)file_get_contents($file);

        if (str_contains($contents, '[#/if#]') || str_contains($contents, '[#/#if]')) {
            $warnings[] = "{$relative} uses block-style [#if#] syntax. [#if#] is always self-closing; conditional content belongs in a tpl= partial.";
        }
    }
}

function architecture_check_files(array $roots, string $pattern): array
{
    $files = [];
    foreach ($roots as $root) {
        if (!is_dir($root)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            if (preg_match($pattern, $path)) {
                $files[] = $path;
            }
        }
    }
    sort($files);
    return $files;
}

function architecture_check_relative(string $ext_path, string $file): string
{
    $relative = str_replace('\\', '/', $file);
    $base = rtrim(str_replace('\\', '/', $ext_path), '/') . '/';

    if (str_starts_with($relative, $base)) {
        return substr($relative, strlen($base));
    }

    return $relative;
}
