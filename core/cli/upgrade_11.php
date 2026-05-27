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

function upgrade_11_tailwind_entrypoint_state(): array
{
    $file = BASE_DIR . 'css/tw/in.css';
    if (!is_file($file)) {
        return [
            'action' => 'missing',
            'file' => $file,
            'message' => 'css/tw/in.css does not exist; skipping Tailwind CSS 4 entrypoint migration.',
        ];
    }

    $content = file_get_contents($file);
    $has_config = preg_match('/^\s*@config\s+["\']\.\.\/\.\.\/tailwind\.config\.js["\'];\s*$/m', $content);
    $has_import = preg_match('/^\s*@import\s+["\']tailwindcss["\'];\s*$/m', $content);
    $has_legacy_tailwind = preg_match('/^\s*@tailwind\s+(base|components|utilities)\s*;\s*$/m', $content);

    if ($has_config && $has_import && !$has_legacy_tailwind) {
        return [
            'action' => 'none',
            'file' => $file,
            'message' => 'Tailwind CSS entrypoint already uses the Tailwind 4 @config/@import format.',
        ];
    }

    return [
        'action' => 'update',
        'file' => $file,
        'message' => 'Update css/tw/in.css to load tailwind.config.js using the Tailwind CSS 4 @config entrypoint.',
    ];
}

function upgrade_11_render_tailwind_entrypoint(string $content): string
{
    $content = preg_replace('/^\s*@config\s+["\'][^"\']+["\'];\s*\R?/m', '', $content);
    $content = preg_replace('/^\s*@import\s+["\']tailwindcss["\'];\s*\R?/m', '', $content);
    $content = preg_replace('/^\s*@tailwind\s+(base|components|utilities)\s*;\s*\R?/m', '', $content);
    $content = ltrim($content);

    $prefix = "@config \"../../tailwind.config.js\";\n@import \"tailwindcss\";\n";
    return $prefix . ($content === '' ? '' : "\n" . $content);
}

function upgrade_11_apply_tailwind_entrypoint(array $state): bool
{
    if (($state['action'] ?? '') !== 'update') {
        return false;
    }

    $content = file_get_contents($state['file']);
    return file_put_contents($state['file'], upgrade_11_render_tailwind_entrypoint($content)) !== false;
}

function upgrade_11_gitignore_state(): array
{
    $file = BASE_DIR . 'ext/.gitignore';
    $rule = '/static/_thumb_/';

    if (!is_file($file)) {
        return [
            'action' => 'missing',
            'file' => $file,
            'rule' => $rule,
            'message' => 'ext/.gitignore does not exist; skipping thumbnail cache ignore rule migration.',
        ];
    }

    $content = file_get_contents($file);
    if (preg_match('/^\s*\/static\/_thumb_\/\s*$/m', $content)) {
        return [
            'action' => 'none',
            'file' => $file,
            'rule' => $rule,
            'message' => 'ext/.gitignore already ignores the generated thumbnail cache.',
        ];
    }

    return [
        'action' => 'update',
        'file' => $file,
        'rule' => $rule,
        'message' => 'Add /static/_thumb_/ to ext/.gitignore for the generated thumbnail cache.',
    ];
}

function upgrade_11_apply_gitignore(array $state): bool
{
    if (($state['action'] ?? '') !== 'update') {
        return false;
    }

    $content = file_get_contents($state['file']);
    $content = rtrim($content, "\r\n") . "\n" . $state['rule'] . "\n";
    return file_put_contents($state['file'], $content) !== false;
}

$yes = in_array('--yes', $argv, true) || in_array('-y', $argv, true);

migrate_10_bootstrap();
$migration = migrate_10_collect();
[$moves, $skipped] = migrate_lib_collect();
$env = upgrade_11_read_env();
$paths = upgrade_11_paths_from_env($env);
$htaccess = upgrade_11_htaccess_state($env['PEPPER'] ?? '', $paths['base_path'], $paths['rewrite_base_path']);
$tailwind_elements_files = upgrade_11_tailwind_elements_files();
$tailwind_entrypoint = upgrade_11_tailwind_entrypoint_state();
$gitignore = upgrade_11_gitignore_state();

$has_work = migrate_10_has_work($migration)
    || !empty($moves)
    || in_array($htaccess['action'], ['write', 'recreate_mod_php'], true)
    || !empty($tailwind_elements_files)
    || $tailwind_entrypoint['action'] === 'update'
    || $gitignore['action'] === 'update';

if (!$has_work) {
    echo "Nimbly 1.1 upgrade checks complete — no automatic upgrade steps are needed.\n";
    if ($htaccess['action'] === 'warn_base_mismatch') {
        echo $htaccess['message'] . "\n";
        echo "Run './nimbly site:setup' if you want to review recreating .htaccess.\n";
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
    echo "  This warning is informational here; use 'site:setup' to review rewrite-base recreation.\n";
}

if ($tailwind_entrypoint['action'] !== 'none') {
    echo "\n[4] Tailwind CSS 4 entrypoint migration\n\n";
    echo '  ' . $tailwind_entrypoint['message'] . "\n";
}

$gitignore_step = $tailwind_entrypoint['action'] !== 'none' ? 5 : 4;
if ($gitignore['action'] !== 'none') {
    echo "\n[{$gitignore_step}] ext/.gitignore migration\n\n";
    echo '  ' . $gitignore['message'] . "\n";
    if ($gitignore['action'] === 'update') {
        echo "  If ext/static/_thumb_/ was already tracked, remove it from the git index after this migration.\n";
    }
}

if (!empty($tailwind_elements_files)) {
    $tailwind_elements_step = $gitignore_step + ($gitignore['action'] !== 'none' ? 1 : 0);
    echo "\n[{$tailwind_elements_step}] Tailwind Elements static asset cleanup\n\n";
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
    echo "Run './nimbly site:setup' if you want to recreate .htaccess for the current BASE_PATH.\n";
}

if ($tailwind_entrypoint['action'] === 'update') {
    echo "\n=== Updating Tailwind CSS entrypoint ===\n";
    if (upgrade_11_apply_tailwind_entrypoint($tailwind_entrypoint)) {
        echo "Updated: css/tw/in.css\n";
    } else {
        echo "ERROR: failed to update css/tw/in.css\n";
    }
}

if ($gitignore['action'] === 'update') {
    echo "\n=== Updating ext/.gitignore ===\n";
    if (upgrade_11_apply_gitignore($gitignore)) {
        echo "Updated: ext/.gitignore\n";
        echo "Note: if ext/static/_thumb_/ is already tracked, run 'git -C ext rm -r --cached static/_thumb_'.\n";
    } else {
        echo "ERROR: failed to update ext/.gitignore\n";
    }
}

if (!empty($tailwind_elements_files)) {
    echo "\n=== Removing Tailwind Elements static assets ===\n";
    $deleted = upgrade_11_apply_tailwind_elements_cleanup($tailwind_elements_files);
    echo "Deleted {$deleted} Tailwind Elements static asset" . ($deleted === 1 ? '' : 's') . ".\n";
}
