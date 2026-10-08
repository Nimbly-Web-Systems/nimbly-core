<?php

function cli_use_color(): bool
{
    if (getenv('NO_COLOR') !== false) {
        return false;
    }
    if (function_exists('stream_isatty')) {
        return stream_isatty(STDOUT);
    }
    if (function_exists('posix_isatty')) {
        return posix_isatty(STDOUT);
    }
    return false;
}

function cli_color(string $text, string $color): string
{
    if (!cli_use_color()) {
        return $text;
    }
    $colors = [
        'cyan' => '36',
        'green' => '32',
        'dim' => '2',
        'bold' => '1',
    ];
    $code = $colors[$color] ?? '';
    return $code === '' ? $text : "\033[" . $code . "m" . $text . "\033[0m";
}

function cli_rule(): string
{
    return str_repeat('─', 80);
}

function cli_compact_output(): bool
{
    return getenv('NIMBLY_COMPACT_OUTPUT') === '1';
}

function cli_section(string $title, bool $leading_newline = false): void
{
    if (cli_compact_output()) {
        return;
    }
    if ($leading_newline) {
        echo "\n";
    }
    echo cli_color(cli_rule(), 'cyan') . "\n";
    echo $title . "\n";
    echo cli_color(cli_rule(), 'cyan') . "\n";
}

function cli_tip(string $message): void
{
    if (cli_compact_output()) {
        return;
    }
    echo cli_color('→', 'green') . ' ' . cli_color($message, 'dim') . "\n";
}

function cli_status(string $message): void
{
    if (cli_compact_output()) {
        return;
    }
    echo cli_color('✓', 'green') . ' ' . $message . "\n";
}

/**
 * The help text of a command: its name and description, then the comment at
 * the top of its file, written the way the command is typed.
 */
function cli_command_help(string $name, array $meta, string $base_dir): string
{
    $help = $name . "\n  " . ($meta['desc'] ?? '') . "\n";
    $file = $base_dir . ($meta['file'] ?? '');
    $source = is_file($file) ? (string)file_get_contents($file) : '';
    if (!preg_match('~^(?:#![^\n]*\n)?<\?php\s*/\*\*(.*?)\*/~s', $source, $match)) {
        return $help;
    }
    $lines = [];
    foreach (explode("\n", trim($match[1])) as $line) {
        $line = rtrim((string)preg_replace('~^\s*\* ?~', '', $line));
        if (preg_match('~^Nimbly CLI\b.*\bcommand$~', $line)) {
            continue;
        }
        $lines[] = str_replace('php core/cli/nimbly.php ', './nimbly ', $line);
    }
    $text = trim(implode("\n", $lines));
    return $text === '' ? $help : $help . "\n" . $text . "\n";
}
