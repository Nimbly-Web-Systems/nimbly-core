<?php

/**
 * Whether core changed build inputs after ext/static was built.
 *
 * The asset build records the core commit in ext/static/app.core. Tailwind
 * scans core templates and scripts for classes and esbuild bundles js/ and
 * css/, so a core update touching those leaves app.css and app.js stale until
 * the assets are rebuilt. Unknown states (no stamp, no git, unknown commit)
 * never warn.
 */
function app_build_stale(): bool
{
    return app_build_state() === 'stale';
}

/**
 * `current`, `stale` or `unknown` for the project at $base (default: this one).
 */
function app_build_state(?string $base = null): string
{
    $base = $base ?? $GLOBALS['SYSTEM']['file_base'];
    $stamp = @file_get_contents($base . 'ext/static/app.core');
    $commit = trim((string)$stamp);
    if (!preg_match('/^[0-9a-f]{40}$/', $commit)) {
        return 'unknown';
    }
    $paths = [
        'css', 'js', 'tailwind.config.js', 'package.json', 'package-lock.json',
        ':(glob)core/**/*.tpl', ':(glob)core/**/*.inc', ':(glob)core/**/*.html',
        ':(glob)core/**/*.js', ':(glob)core/**/*.jsx', ':(glob)core/**/*.mjs',
        ':(glob)core/**/*.css', ':(glob)core/**/*.po', ':(glob)core/static/**',
        ':(exclude)core/tests',
    ];
    $command = 'git -C ' . escapeshellarg($base) . ' diff --quiet ' . $commit . ' HEAD -- '
        . implode(' ', array_map('escapeshellarg', $paths)) . ' 2>/dev/null';
    exec($command, $output, $status);
    // 1 = differences; anything above means the commit is unknown here.
    if ($status === 0) {
        return 'current';
    }
    return $status === 1 ? 'stale' : 'unknown';
}
