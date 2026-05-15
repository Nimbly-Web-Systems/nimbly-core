<?php

/**
 * Nimbly CLI — system:upgrade-11 command
 *
 * Usage: php core/cli/nimbly.php system:upgrade-11 [--yes]
 */

if (php_sapi_name() !== 'cli') {
    die("nimbly.php must be run from the command line.\n");
}

if (!defined('BASE_DIR')) {
    define('BASE_DIR', realpath(__DIR__ . '/../..') . '/');
}

require_once BASE_DIR . 'core/cli/helpers/migrate_10.php';
require_once BASE_DIR . 'core/cli/helpers/migrate_lib.php';
require_once BASE_DIR . 'core/cli/helpers/htaccess.php';

function upgrade_11_tailwind_elements_files(): array
{
    $files = glob(BASE_DIR . 'ext/static/tw-elements*') ?: [];
    return array_values(array_filter($files, 'is_file'));
}

function upgrade_11_apply_tailwind_elements_cleanup(array $files): int
{
    $deleted = 0;
    foreach ($files as $file) {
        if (is_file($file) && unlink($file)) {
            $deleted++;
        }
    }
    return $deleted;
}

$yes = in_array('--yes', $argv, true) || in_array('-y', $argv, true);

migrate_10_bootstrap();
$migration = migrate_10_collect();
[$moves, $skipped] = migrate_lib_collect();
$env = upgrade_11_read_env();
$paths = upgrade_11_paths_from_env($env);
$htaccess = upgrade_11_htaccess_state($env['PEPPER'] ?? '', $paths['base_path'], $paths['rewrite_base_path']);
$tailwind_elements_files = upgrade_11_tailwind_elements_files();

$has_work = migrate_10_has_work($migration)
    || !empty($moves)
    || in_array($htaccess['action'], ['write', 'recreate_mod_php'], true)
    || !empty($tailwind_elements_files);

if (!$has_work) {
    echo "Nimbly 1.1 upgrade checks complete — no automatic upgrade steps are needed.\n";
    if ($htaccess['action'] === 'warn_base_mismatch') {
        echo $htaccess['message'] . "\n";
        echo "Run 'php core/cli/nimbly.php setup' if you want to review recreating .htaccess.\n";
    }
    exit(0);
}

echo "Nimbly 1.1 upgrade plan:\n";

if (migrate_10_has_work($migration)) {
    echo "\n[1] Resource/data migration\n";
    migrate_10_print_summary($migration);
}

if (!empty($moves) || !empty($skipped)) {
    echo "\n[2] Library layout migration\n\n";
    if (!empty($moves)) {
        foreach ($moves as [$from, $to]) {
            echo '  ' . str_replace(BASE_DIR, '', $from) . "\n";
            echo '    -> ' . str_replace(BASE_DIR, '', $to) . "\n";
        }
    } else {
        echo "  No single-file library directories need migration.\n";
    }
    if (!empty($skipped)) {
        echo "\n  Skipped:\n";
        foreach ($skipped as $item) {
            echo "    - {$item}\n";
        }
    }
}

echo "\n[3] .htaccess repair\n\n";
echo '  ' . $htaccess['message'] . "\n";
if ($htaccess['action'] === 'warn_base_mismatch') {
    echo "  This warning is informational here; use 'setup' to review rewrite-base recreation.\n";
}

if (!empty($tailwind_elements_files)) {
    echo "\n[4] Tailwind Elements static asset cleanup\n\n";
    foreach ($tailwind_elements_files as $file) {
        echo '  Delete ' . str_replace(BASE_DIR, '', $file) . "\n";
    }
}

if (!$yes) {
    echo "\nProceed with the automatic 1.1 upgrade steps? [y/N] ";
    $confirm = trim(fgets(STDIN));
    if (strtolower($confirm) !== 'y') {
        die("Aborted.\n");
    }
}

if (migrate_10_has_work($migration)) {
    echo "\n=== Running resource/data migration ===\n";
    migrate_10_apply($migration);
    migrate_10_print_done($migration);
}

if (!empty($moves)) {
    echo "\n=== Running library layout migration ===\n\n";
    $migrated = migrate_lib_apply($moves);
    echo "\nMigrated {$migrated} library entr" . ($migrated === 1 ? 'y' : 'ies') . ".\n";
}

if (in_array($htaccess['action'], ['write', 'recreate_mod_php'], true)) {
    echo "\n=== Repairing .htaccess ===\n";
    upgrade_11_apply_htaccess($htaccess);
    if ($htaccess['action'] === 'write') {
        echo "Written: .htaccess\n";
    } else {
        echo "Recreated: .htaccess (removed mod_php directives, not supported under PHP-FPM)\n";
    }
}

if ($htaccess['action'] === 'warn_base_mismatch') {
    echo "\n" . $htaccess['message'] . "\n";
    echo "Run 'php core/cli/nimbly.php setup' if you want to recreate .htaccess for the current BASE_PATH.\n";
}

if (!empty($tailwind_elements_files)) {
    echo "\n=== Removing Tailwind Elements static assets ===\n";
    $deleted = upgrade_11_apply_tailwind_elements_cleanup($tailwind_elements_files);
    echo "Deleted {$deleted} Tailwind Elements static asset" . ($deleted === 1 ? '' : 's') . ".\n";
}
