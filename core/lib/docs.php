<?php

/**
 * The Nimbly implementation reference (NIMBLY.md): headings, sections and search.
 * Used by the docs:* CLI commands and by agents that read the docs.
 */

function docs_reference(): string
{
    $contents = @file_get_contents(BASE_DIR . 'NIMBLY.md');
    if ($contents === false) {
        throw new RuntimeException('The Nimbly reference is unavailable');
    }
    return $contents;
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

function docs_section_text(string $contents, array $headings, array $selected): string
{
    $lines = preg_split('/\R/', $contents);
    $end = count($lines);
    foreach ($headings as $heading) {
        if ($heading['line'] > $selected['line'] && $heading['level'] <= $selected['level']) {
            $end = $heading['line'] - 1;
            break;
        }
    }
    return implode("\n", array_slice($lines, $selected['line'] - 1, $end - $selected['line'] + 1)) . "\n";
}

/** Matching lines as "Heading > Path:line: text". */
function docs_search_lines(string $contents, array $headings, string $query): array
{
    $result = [];
    foreach (preg_split('/\R/', $contents) as $index => $line) {
        if (stripos($line, $query) !== false) {
            $heading = docs_heading_for_line($headings, $index + 1);
            $result[] = sprintf('%s:%d: %s', $heading['path'] ?? '(document preamble)', $index + 1, trim($line));
        }
    }
    return $result;
}
