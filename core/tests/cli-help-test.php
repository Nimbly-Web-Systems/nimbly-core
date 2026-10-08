<?php

// Every command answers --help with its own text and does not run.

$cli = dirname(__DIR__) . '/cli/nimbly.php';
$source = (string)file_get_contents($cli);
preg_match_all("~^    '([a-z0-9:-]+)'\\s*=> \\['file' => '(core/[^']+)'~m", $source, $matches, PREG_SET_ORDER);

function cli_help_assert(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

cli_help_assert(count($matches) > 40, 'the command list is read: ' . count($matches));
foreach ($matches as [, $name, $file]) {
    $output = [];
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($cli) . ' ' . escapeshellarg($name) . ' --help 2>&1', $output, $status);
    $text = implode("\n", $output);
    cli_help_assert($status === 0 && str_starts_with($text, $name . "\n"), "$name --help prints its own help");
    cli_help_assert(str_contains($text, 'Usage'), "$name --help has a usage line ($file)");
    cli_help_assert(!str_contains($text, 'php core/cli/nimbly.php'), "$name --help is written as ./nimbly");
}

echo "cli help tests passed\n";
