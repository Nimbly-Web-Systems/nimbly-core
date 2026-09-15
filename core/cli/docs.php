#!/usr/bin/env php
<?php

/**
 * Read-only lookup commands for the local Nimbly implementation reference.
 *
 * Usage:
 *   php core/cli/nimbly.php docs:list
 *   php core/cli/nimbly.php docs:section "Template Syntax"
 *   php core/cli/nimbly.php docs:search "router_accept"
 */

if (php_sapi_name() !== 'cli') {
    die("docs.php must be run from the command line.\n");
}

if (!defined('BASE_DIR')) {
    define('BASE_DIR', realpath(__DIR__ . '/../..') . '/');
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || defined('NIMBLY_CLI_DISPATCH')) {
    docs_main($argv);
}

function docs_main(array $argv): void
{
    $docs_path = BASE_DIR . 'NIMBLY.md';
    $docs_contents = file_get_contents($docs_path);
    if ($docs_contents === false) {
        fwrite(STDERR, "Error: cannot read {$docs_path}\n");
        exit(2);
    }

    $headings = docs_parse_headings($docs_contents);
    $command = $argv[1] ?? '';

    if ($command === 'docs:list') {
        docs_print_list($headings);
        exit(0);
    }

    if ($command === 'docs:section') {
        $query = trim(implode(' ', array_slice($argv, 2)));
        if ($query === '') {
            docs_error('usage: ./nimbly docs:section "Heading [> Child]"');
        }
        try {
            $heading = docs_find_section($headings, $query);
        } catch (RuntimeException $error) {
            docs_error($error->getMessage());
        }
        docs_print_section($docs_contents, $headings, $heading);
        exit(0);
    }

    if ($command === 'docs:search') {
        $query = trim(implode(' ', array_slice($argv, 2)));
        if ($query === '') {
            docs_error('usage: ./nimbly docs:search "search text"');
        }
        docs_search($docs_contents, $headings, $query);
        exit(0);
    }

    docs_error('usage: docs:list | docs:section "Heading [> Child]" | docs:search "search text"');
}

function docs_parse_headings(string $contents): array
{
    $headings = [];
    $ancestors = [];
    $line_number = 0;
    $in_fence = false;

    foreach (preg_split('/\R/', $contents) as $line) {
        $line_number++;
        if (preg_match('/^\s*```/', $line)) {
            $in_fence = !$in_fence;
            continue;
        }
        if ($in_fence) {
            continue;
        }
        if (!preg_match('/^(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $match)) {
            continue;
        }

        $level = strlen($match[1]);
        $title = trim($match[2]);
        $match_title = preg_replace('/^\d+(?:\.\d+)*\.\s+/', '', $title) ?? $title;

        while (!empty($ancestors) && end($ancestors)['level'] >= $level) {
            array_pop($ancestors);
        }

        $path_titles = array_map(static function (array $ancestor): string {
            return $ancestor['match_title'];
        }, $ancestors);
        $path_titles[] = $match_title;

        $heading = [
            'level' => $level,
            'line' => $line_number,
            'title' => $title,
            'match_title' => $match_title,
            'path' => implode(' > ', $path_titles),
        ];
        $headings[] = $heading;
        if ($level > 1) {
            $ancestors[] = $heading;
        }
    }

    return $headings;
}

function docs_print_list(array $headings): void
{
    foreach ($headings as $heading) {
        printf("%s[%d] %s\n", str_repeat('  ', $heading['level'] - 1), $heading['line'], $heading['title']);
    }
}

function docs_find_section(array $headings, string $query): array
{
    $parts = array_values(array_filter(array_map('trim', explode('>', $query)), static fn(string $part): bool => $part !== ''));
    if (empty($parts)) {
        throw new RuntimeException('section name cannot be empty');
    }

    $matches = [];
    foreach ($headings as $heading) {
        $path = array_map('trim', explode('>', $heading['path']));
        if (count($path) < count($parts)) {
            continue;
        }
        $candidate = count($parts) === 1 ? [$path[count($path) - 1]] : $path;
        if (docs_titles_equal($candidate, $parts)) {
            $matches[] = $heading;
        }
    }

    if (count($matches) === 0) {
        throw new RuntimeException("section not found: {$query}");
    }
    if (count($matches) > 1) {
        $paths = array_map(static fn(array $heading): string => $heading['path'], $matches);
        throw new RuntimeException("section is ambiguous: {$query}\nMatches:\n- " . implode("\n- ", $paths));
    }

    return $matches[0];
}

function docs_titles_equal(array $left, array $right): bool
{
    if (count($left) !== count($right)) {
        return false;
    }
    foreach ($left as $index => $title) {
        if (strcasecmp($title, $right[$index]) !== 0) {
            return false;
        }
    }
    return true;
}

function docs_print_section(string $contents, array $headings, array $selected): void
{
    $lines = preg_split('/\R/', $contents);
    $start = $selected['line'] - 1;
    $end = count($lines);
    foreach ($headings as $heading) {
        if ($heading['line'] > $selected['line'] && $heading['level'] <= $selected['level']) {
            $end = $heading['line'] - 1;
            break;
        }
    }

    for ($index = $start; $index < $end; $index++) {
        echo $lines[$index] . "\n";
    }
}

function docs_search(string $contents, array $headings, string $query): void
{
    $lines = preg_split('/\R/', $contents);
    $matches = [];
    foreach ($lines as $index => $line) {
        if (stripos($line, $query) !== false) {
            $matches[] = $index + 1;
        }
    }

    if (empty($matches)) {
        docs_error("no matches for: {$query}");
    }

    foreach ($matches as $line_number) {
        $heading = docs_heading_for_line($headings, $line_number);
        $path = $heading['path'] ?? '(document preamble)';
        printf("%s:%d: %s\n", $path, $line_number, trim($lines[$line_number - 1]));
    }
}

function docs_heading_for_line(array $headings, int $line_number): ?array
{
    $current = null;
    foreach ($headings as $heading) {
        if ($heading['line'] > $line_number) {
            break;
        }
        $current = $heading;
    }
    return $current;
}

function docs_error(string $message): void
{
    fwrite(STDERR, "Error: {$message}\n");
    exit(1);
}
