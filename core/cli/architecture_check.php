<?php

/**
 * Nimbly CLI - test:architecture command
 *
 * Usage:
 *   php core/cli/nimbly.php test:architecture [--strict] [--path=ext]
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
architecture_check_languages($ext_path, $warnings);

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
    if (in_array(($args[0] ?? ''), ['test:architecture', 'architecture:check'], true)) {
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

function architecture_check_languages(string $ext_path, array &$warnings): void
{
    $site_file = $ext_path . '/data/.config/site';
    if (!is_file($site_file)) {
        return;
    }
    $site = json_decode((string)file_get_contents($site_file), true);
    $languages = $site['languages'] ?? null;
    if (!is_array($languages) || $languages === []) {
        $warnings[] = 'data/.config/site has no languages array; runtime will use the legacy English fallback.';
        return;
    }
    foreach ($languages as $language) {
        if (!is_string($language) || !preg_match('/^[a-z]{2}$/', $language)) {
            $warnings[] = 'data/.config/site contains an invalid language; use unique lowercase two-letter codes.';
            return;
        }
    }
    if (count($languages) !== count(array_unique($languages))) {
        $warnings[] = 'data/.config/site contains duplicate languages.';
    }
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
            $warnings[] = "{$relative} defines dynamic route `{$route}` but ext/data/.routes has no matching record. Run `./nimbly routes:sync`.";
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

        foreach (architecture_check_inline_edit_hosts($contents) as $problem) {
            $warnings[] = "{$relative}:{$problem['line']} {$problem['message']}";
        }
    }
}

// Formatted inline-editable text needs a <div> host: a <p> cannot hold
// paragraphs, lists or media, and Enter in a <p> host nests <p>s. Only plain
// text may live in a <p>. Headings and inline elements (span, strong, ...) may
// take inline formatting (bold, italic, anchor), never block formatting or media.
function architecture_check_inline_edit_hosts(string $contents): array
{
    $containers = ['div', 'section', 'article', 'aside', 'header', 'footer', 'main', 'li', 'td', 'th', 'blockquote', 'figcaption'];
    $headings = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
    $block_buttons = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'orderedlist', 'unorderedlist', 'quote', 'pre'];
    $problems = [];

    preg_match_all('/<([a-z][a-z0-9]*)\b[^>]*\bdata-nb-edit=/i', $contents, $matches, PREG_OFFSET_CAPTURE);
    foreach ($matches[0] as $i => $match) {
        $tag = strtolower($matches[1][$i][0]);
        $offset = $match[1];
        $end = strpos($contents, '>', $offset + strlen($match[0]));
        $opening = substr($contents, $offset, $end === false ? null : $end - $offset);
        $options = [];
        if (preg_match("/data-nb-edit-options='([^']*)'/", $opening, $option_match)) {
            $options = json_decode($option_match[1], true) ?: [];
        }
        if (($options['plain'] ?? false) === true || in_array($tag, $containers, true)) {
            continue;
        }

        $buttons = array_map('trim', explode(',', (string)($options['buttons'] ?? '')));
        $has_block = !empty($options['media']) || array_intersect($buttons, $block_buttons);
        if ($tag !== 'p' && !$has_block) {
            continue;
        }

        $line = substr_count($contents, "\n", 0, $offset) + 1;
        $problems[] = [
            'line' => $line,
            'message' => $tag === 'p'
                ? "inline-editable <p> holds formatted text. Formatted text needs a <div> host; only plain text ({\"plain\":true}) may live in a <p>."
                : "inline-editable <{$tag}> has block formatting or media. A <{$tag}> host takes inline formatting only (bold, italic, anchor); rich text needs a <div> host.",
        ];
    }

    return $problems;
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
