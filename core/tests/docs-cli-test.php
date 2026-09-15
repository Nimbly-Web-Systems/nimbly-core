<?php

define('BASE_DIR', realpath(__DIR__ . '/../..') . '/');
require BASE_DIR . 'core/cli/docs.php';

function docs_cli_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$fixture = "# Guide\n\n## Alpha\n\n### Shared\nfirst\n\n### Nested\nneedle\n\n## Beta\n\n### Shared\nsecond\n";
$headings = docs_parse_headings($fixture);

docs_cli_test_assert(count($headings) === 6, 'parses nested headings');
docs_cli_test_assert(docs_find_section($headings, 'Alpha > Nested')['title'] === 'Nested', 'finds hierarchical section');
docs_cli_test_assert(docs_find_section($headings, 'nested')['path'] === 'Alpha > Nested', 'matches case-insensitively');

$ambiguous = false;
try {
    docs_find_section($headings, 'Shared');
} catch (Throwable $error) {
    $ambiguous = true;
}
docs_cli_test_assert($ambiguous, 'detects duplicate section names');

docs_cli_test_assert(str_contains(docs_heading_for_line($headings, 10)['path'], 'Alpha > Nested'), 'associates search lines with headings');
docs_cli_test_assert(!str_contains(implode("\n", array_map(static fn(array $heading): string => $heading['title'], docs_parse_headings("## A\n```\n### fenced\n```\n"))), 'fenced'), 'ignores fenced headings');
echo "Documentation CLI tests passed.\n";
