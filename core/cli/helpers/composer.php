<?php

require_once __DIR__ . '/output.php';

/**
 * Ensures a Composer package is required in ext/composer.json and installed
 * into ext/vendor/. Meant to be run locally by a developer (from a module's
 * .install.inc) — the resulting ext/composer.json, ext/composer.lock and
 * ext/vendor/ are committed, not fetched at deploy time.
 */
function cli_composer_require_ext(string $package, string $version): bool
{
    if (trim((string)shell_exec('command -v composer 2>/dev/null')) === '') {
        echo "Error: the `composer` binary was not found on PATH.\n";
        echo "Install Composer (https://getcomposer.org) and try again.\n";
        return false;
    }

    $ext_dir = rtrim($GLOBALS['SYSTEM']['file_base'], '/') . '/ext';
    $composer_json_path = $ext_dir . '/composer.json';

    $composer_json = file_exists($composer_json_path)
        ? json_decode((string)file_get_contents($composer_json_path), true)
        : null;
    if (!is_array($composer_json)) {
        $composer_json = ['require' => []];
    }
    if (!isset($composer_json['require']) || !is_array($composer_json['require'])) {
        $composer_json['require'] = [];
    }

    if (($composer_json['require'][$package] ?? null) !== $version) {
        $composer_json['require'][$package] = $version;
        file_put_contents(
            $composer_json_path,
            json_encode($composer_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
        cli_status("Added $package ($version) to ext/composer.json");
    } else {
        cli_status("$package ($version) already required in ext/composer.json");
    }

    cli_status('Running composer install in ext/ ...');
    $cmd = 'composer install --working-dir=' . escapeshellarg($ext_dir) . ' --no-interaction 2>&1';
    exec($cmd, $output, $exit_code);
    if (!empty($output)) {
        echo implode("\n", $output) . "\n";
    }

    if ($exit_code !== 0) {
        echo "Error: composer install failed (exit code $exit_code).\n";
        return false;
    }

    cli_status('composer install completed');
    return true;
}
