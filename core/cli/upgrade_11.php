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

require_once BASE_DIR . 'core/cli/helpers/output.php';
require_once BASE_DIR . 'core/cli/helpers/migrate_10.php';
require_once BASE_DIR . 'core/cli/helpers/migrate_lib.php';
require_once BASE_DIR . 'core/cli/helpers/htaccess.php';
require_once BASE_DIR . 'core/cli/helpers/users_email_index.php';

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

function upgrade_11_daisyui_themes_state(): array
{
    $file = BASE_DIR . 'ext/tailwind.theme.js';
    if (!is_file($file)) {
        return [
            'action' => 'missing',
            'file' => $file,
            'message' => 'ext/tailwind.theme.js does not exist; skipping daisyuiThemes check.',
        ];
    }

    $content = file_get_contents($file);
    if (preg_match('/export\s+const\s+daisyuiThemes\s*=/', $content)) {
        return [
            'action' => 'none',
            'file' => $file,
            'message' => 'ext/tailwind.theme.js already exports daisyuiThemes.',
        ];
    }

    return [
        'action' => 'warn',
        'file' => $file,
        'message' => 'ext/tailwind.theme.js is missing the named daisyuiThemes export. Without it, DaisyUI CSS variables (--color-base-content, --border, --depth, etc.) are not injected and admin form fields lose their correct styling.',
    ];
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

function upgrade_11_collect_sc(string $pattern): array
{
    $hits = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(BASE_DIR . 'ext', FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (!in_array($file->getExtension(), ['tpl', 'php', 'inc'], true)) {
            continue;
        }
        $content = file_get_contents($file->getPathname());
        $count = preg_match_all($pattern, $content);
        if ($count > 0) {
            $hits[] = [$file->getPathname(), $count];
        }
    }
    return $hits;
}

function upgrade_11_get_key_collect(): array
{
    return upgrade_11_collect_sc('/\[#get-key\s/');
}

function upgrade_11_apply_get_key(array $hits): int
{
    $migrated = 0;
    foreach ($hits as [$path]) {
        $content = file_get_contents($path);
        $new = preg_replace('/\[#get-key ([^\s#]+) ([^\s#]+)(.*?)#\]/', '[#get $1.$2$3#]', $content);
        if ($new !== $content) {
            file_put_contents($path, $new);
            $migrated++;
        }
    }
    return $migrated;
}

function upgrade_11_jget_collect(): array
{
    return upgrade_11_collect_sc('/\[#jget\s/');
}

function upgrade_11_apply_jget(array $hits): int
{
    $migrated = 0;
    foreach ($hits as [$path]) {
        $content = file_get_contents($path);
        $new = str_replace('[#jget ', '[#get ', $content);
        if ($new !== $content) {
            file_put_contents($path, $new);
            $migrated++;
        }
    }
    return $migrated;
}

function upgrade_11_get_i18n_collect(): array
{
    return upgrade_11_collect_sc('/\[#get-i18n\s/');
}

function upgrade_11_apply_get_i18n(array $hits): int
{
    $migrated = 0;
    foreach ($hits as [$path]) {
        $content = file_get_contents($path);
        // Strip [#detect-language#] positional arg — auto-detect is now the default
        $new = preg_replace('/\[#get-i18n ([^\s#]+) \[#detect-language#\]#\]/', '[#get $1#]', $content);
        // Replace remaining get-i18n with get (lang=xx named params carry through)
        $new = str_replace('[#get-i18n ', '[#get ', $new);
        if ($new !== $content) {
            file_put_contents($path, $new);
            $migrated++;
        }
    }
    return $migrated;
}

function upgrade_11_lookup_collect(): array
{
    return upgrade_11_collect_sc('/\[#lookup\s/');
}

function upgrade_11_apply_lookup(array $hits): int
{
    $migrated = 0;
    foreach ($hits as [$path]) {
        $content = file_get_contents($path);
        $new = str_replace('[#lookup ', '[#get ', $content);
        if ($new !== $content) {
            file_put_contents($path, $new);
            $migrated++;
        }
    }
    return $migrated;
}

function upgrade_11_uuid_collect(): array
{
    $hits = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(BASE_DIR . 'ext', FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (!in_array($file->getExtension(), ['tpl', 'php', 'inc'], true)) {
            continue;
        }
        $content = file_get_contents($file->getPathname());
        $count = preg_match_all("/load_library\(['\"]uuid['\"]\\)|(?<![A-Za-z0-9_])uuid_sc\s*\(|\\[#uuid#\\]/", $content);
        if ($count > 0) {
            $hits[] = [$file->getPathname(), $count];
        }
    }
    return $hits;
}

function upgrade_11_apply_uuid(array $hits): int
{
    $migrated = 0;
    foreach ($hits as [$path]) {
        $content = file_get_contents($path);
        $new = preg_replace("/load_library\(['\"]uuid['\"]\)/", "load_library('util')", $content);
        $new = preg_replace('/(?<![A-Za-z0-9_])uuid_sc\s*\(/', 'generate_uuid(', $new);
        $new = str_replace('[#uuid#]', '[#get uuid#]', $new);
        if ($new !== $content) {
            file_put_contents($path, $new);
            $migrated++;
        }
    }
    return $migrated;
}

function upgrade_11_util_collect(): array
{
    $hits = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(BASE_DIR . 'ext', FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (!in_array($file->getExtension(), ['php', 'inc'], true)) {
            continue;
        }
        $content = file_get_contents($file->getPathname());
        $count = preg_match_all("/load_librar(?:y|ies)\s*\([^)]*['\"](?:salt|md5|slug)['\"][^)]*\)|(?<![A-Za-z0-9_])slug_sc\s*\(/s", $content);
        if ($count > 0) {
            $hits[] = [$file->getPathname(), $count];
        }
    }
    return $hits;
}

function upgrade_11_apply_util(array $hits): int
{
    $migrated = 0;
    foreach ($hits as [$path]) {
        $content = file_get_contents($path);
        $new = preg_replace("/load_library\(['\"](?:salt|md5|slug)['\"]\)/", "load_library('util')", $content);
        $new = preg_replace_callback(
            "/load_libraries\s*\((\[[^\]]*\])\)/s",
            function ($matches) {
                return 'load_libraries(' . preg_replace("/(['\"])(?:salt|md5|slug)\\1/", "'util'", $matches[1]) . ')';
            },
            $new
        );
        $new = preg_replace('/(?<![A-Za-z0-9_])slug_sc\s*\(/', 'make_slug(', $new);
        if ($new !== $content) {
            file_put_contents($path, $new);
            $migrated++;
        }
    }
    return $migrated;
}

$yes = in_array('--yes', $argv, true) || in_array('-y', $argv, true);

migrate_10_bootstrap();
$migration    = migrate_10_collect();
$users_email  = users_email_index_collect();
[$moves, $skipped] = migrate_lib_collect();
$env          = upgrade_11_read_env();
$paths        = upgrade_11_paths_from_env($env);
$htaccess     = upgrade_11_htaccess_state($env['PEPPER'] ?? '', $paths['base_path'], $paths['rewrite_base_path']);
$tw_elements  = upgrade_11_tailwind_elements_files();
$tw_entry     = upgrade_11_tailwind_entrypoint_state();
$daisyui      = upgrade_11_daisyui_themes_state();
$gitignore    = upgrade_11_gitignore_state();
$get_key_hits = upgrade_11_get_key_collect();
$jget_hits    = upgrade_11_jget_collect();
$i18n_hits    = upgrade_11_get_i18n_collect();
$lookup_hits  = upgrade_11_lookup_collect();
$uuid_hits    = upgrade_11_uuid_collect();
$util_hits    = upgrade_11_util_collect();

$has_work = migrate_10_has_work($migration)
    || users_email_index_has_work($users_email)
    || !empty($users_email['duplicates'])
    || !empty($moves)
    || in_array($htaccess['action'], ['write', 'recreate_mod_php'], true)
    || !empty($tw_elements)
    || $tw_entry['action'] === 'update'
    || $daisyui['action'] === 'warn'
    || $gitignore['action'] === 'update'
    || !empty($get_key_hits)
    || !empty($jget_hits)
    || !empty($i18n_hits)
    || !empty($lookup_hits)
    || !empty($uuid_hits)
    || !empty($util_hits);

if (!$has_work) {
    echo "Nimbly 1.1.0 upgrade checks complete — no automatic upgrade steps are needed.\n";
    if ($htaccess['action'] === 'warn_base_mismatch') {
        echo $htaccess['message'] . "\n";
        cli_tip("Run './nimbly system:setup' if you want to review and repair generated runtime files.");
    }
    exit(0);
}

$step = 0;

echo "Nimbly 1.1.0 upgrade plan:\n";

if (migrate_10_has_work($migration)) {
    echo "\n[" . ++$step . "] Resource/data migration\n";
    migrate_10_print_summary($migration);
}

if (users_email_index_has_work($users_email) || !empty($users_email['duplicates'])) {
    echo "\n[" . ++$step . "] Users email index migration\n";
    users_email_index_print_summary($users_email);
}

if (!empty($moves) || !empty($skipped)) {
    echo "\n[" . ++$step . "] Library layout migration\n\n";
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

echo "\n[" . ++$step . "] .htaccess repair\n\n";
echo '  ' . $htaccess['message'] . "\n";
if ($htaccess['action'] === 'warn_base_mismatch') {
    cli_tip("Use 'system:setup' to review and repair generated runtime files.");
}

if ($tw_entry['action'] !== 'none') {
    echo "\n[" . ++$step . "] Tailwind CSS 4 entrypoint migration\n\n";
    echo '  ' . $tw_entry['message'] . "\n";
}

if ($daisyui['action'] === 'warn') {
    echo "\n[" . ++$step . "] DaisyUI theme export missing\n\n";
    echo '  ' . $daisyui['message'] . "\n";
    cli_tip("Add 'export const daisyuiThemes = [{ light: { primary: \"#...\", secondary: \"#...\", ... } }];' to ext/tailwind.theme.js. See NIMBLY.md §19 for the full example.");
}

if ($gitignore['action'] !== 'none') {
    echo "\n[" . ++$step . "] ext/.gitignore migration\n\n";
    echo '  ' . $gitignore['message'] . "\n";
    if ($gitignore['action'] === 'update') {
        echo "  If ext/static/_thumb_/ was already tracked, remove it from the git index after this migration.\n";
    }
}

if (!empty($tw_elements)) {
    echo "\n[" . ++$step . "] Tailwind Elements static asset cleanup\n\n";
    foreach ($tw_elements as $file) {
        echo '  Delete ' . str_replace(BASE_DIR, '', $file) . "\n";
    }
}

foreach ([
    [$get_key_hits, '[#get-key#] → [#get#]'],
    [$jget_hits,    '[#jget#] → [#get#]'],
    [$i18n_hits,    '[#get-i18n#] → [#get#]'],
    [$lookup_hits,  '[#lookup#] → [#get#]'],
    [$uuid_hits,    'load_library(uuid) → util; uuid_sc() → generate_uuid(); [#uuid#] → [#get uuid#]'],
    [$util_hits,    'load_library/load_libraries(salt|md5|slug) → util; slug_sc() → make_slug()'],
] as [$hits, $label]) {
    if (!empty($hits)) {
        $total = array_sum(array_column($hits, 1));
        echo "\n[" . ++$step . "] Replace {$label} ({$total} occurrence" . ($total === 1 ? '' : 's') . " in " . count($hits) . " file" . (count($hits) === 1 ? '' : 's') . ")\n\n";
        foreach ($hits as [$path, $count]) {
            echo '  ' . str_replace(BASE_DIR, '', $path) . " ({$count})\n";
        }
    }
}

if (!$yes) {
    echo "\nProceed with the automatic 1.1.0 upgrade steps? [y/N] ";
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

if (users_email_index_has_work($users_email) || !empty($users_email['duplicates'])) {
    echo "\n=== Updating users email index ===\n";
    $users_email_result = users_email_index_apply($users_email);
    users_email_index_print_done($users_email_result);
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
    cli_tip("Run './nimbly system:setup' if you want to review and repair generated runtime files for the current BASE_PATH.");
}

if ($tw_entry['action'] === 'update') {
    echo "\n=== Updating Tailwind CSS entrypoint ===\n";
    if (upgrade_11_apply_tailwind_entrypoint($tw_entry)) {
        echo "Updated: css/tw/in.css\n";
    } else {
        echo "ERROR: failed to update css/tw/in.css\n";
    }
}

if ($gitignore['action'] === 'update') {
    echo "\n=== Updating ext/.gitignore ===\n";
    if (upgrade_11_apply_gitignore($gitignore)) {
        echo "Updated: ext/.gitignore\n";
        cli_tip("If ext/static/_thumb_/ is already tracked, run: git -C ext rm -r --cached static/_thumb_");
    } else {
        echo "ERROR: failed to update ext/.gitignore\n";
    }
}

if (!empty($tw_elements)) {
    echo "\n=== Removing Tailwind Elements static assets ===\n";
    $deleted = upgrade_11_apply_tailwind_elements_cleanup($tw_elements);
    echo "Deleted {$deleted} Tailwind Elements static asset" . ($deleted === 1 ? '' : 's') . ".\n";
}

if (!empty($get_key_hits)) {
    echo "\n=== Replacing [#get-key#] with [#get#] ===\n";
    echo "Updated " . upgrade_11_apply_get_key($get_key_hits) . " file(s).\n";
}

if (!empty($jget_hits)) {
    echo "\n=== Replacing [#jget#] with [#get#] ===\n";
    echo "Updated " . upgrade_11_apply_jget($jget_hits) . " file(s).\n";
}

if (!empty($i18n_hits)) {
    echo "\n=== Replacing [#get-i18n#] with [#get#] ===\n";
    echo "Updated " . upgrade_11_apply_get_i18n($i18n_hits) . " file(s).\n";
}

if (!empty($lookup_hits)) {
    echo "\n=== Replacing [#lookup#] with [#get#] ===\n";
    echo "Updated " . upgrade_11_apply_lookup($lookup_hits) . " file(s).\n";
}

if (!empty($uuid_hits)) {
    echo "\n=== Migrating uuid to util ===\n";
    echo "Updated " . upgrade_11_apply_uuid($uuid_hits) . " file(s).\n";
}

if (!empty($util_hits)) {
    echo "\n=== Migrating salt/md5/slug to util ===\n";
    echo "Updated " . upgrade_11_apply_util($util_hits) . " file(s).\n";
}
