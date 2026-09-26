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

require_once BASE_DIR . 'core/lib/docs.php';

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || defined('NIMBLY_CLI_DISPATCH')) {
    docs_main($argv);
}

function docs_main(array $argv): void
{
    try {
        $docs_contents = docs_reference();
    } catch (RuntimeException $error) {
        fwrite(STDERR, "Error: cannot read " . BASE_DIR . "NIMBLY.md\n");
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

function docs_print_list(array $headings): void
{
    foreach ($headings as $heading) {
        printf("%s[%d] %s\n", str_repeat('  ', $heading['level'] - 1), $heading['line'], $heading['title']);
    }
}

function docs_print_section(string $contents, array $headings, array $selected): void
{
    echo docs_section_text($contents, $headings, $selected);
}

function docs_search(string $contents, array $headings, string $query): void
{
    $matches = docs_search_lines($contents, $headings, $query);
    if (empty($matches)) {
        docs_error("no matches for: {$query}");
    }
    echo implode("\n", $matches) . "\n";
}

function docs_error(string $message): void
{
    fwrite(STDERR, "Error: {$message}\n");
    exit(1);
}
