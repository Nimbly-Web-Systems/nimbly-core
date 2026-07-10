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

function upgrade_11_collect_files(string $root, array $extensions): array
{
    if (!is_dir($root)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && in_array($file->getExtension(), $extensions, true)) {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

function upgrade_11_ui_migration_collect(): array
{
    $hits = [
        'footer_class' => [],
        'borderless_fields' => [],
    ];
    $roots = [BASE_DIR . 'ext/tpl', BASE_DIR . 'ext/uri', BASE_DIR . 'ext/modules', BASE_DIR . 'ext/theme.css'];

    foreach ($roots as $root) {
        $files = is_file($root) ? [$root] : upgrade_11_collect_files($root, ['tpl', 'css']);
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $relative = str_replace(BASE_DIR, '', $file);

            if (preg_match('/class\s*=\s*["\'][^"\']*(?<![a-z0-9_-])footer(?![a-z0-9_-])[^"\']*["\']/i', $content)
                || preg_match('/(^|[},\s])\.footer(?:\s|\{|,|:)/m', $content)) {
                $hits['footer_class'][] = $relative;
            }

            if (preg_match('/<(?:input|textarea|select)\b[^>]*class\s*=\s*["\'][^"\']*\bborder-0\b[^"\']*["\']/is', $content)) {
                $hits['borderless_fields'][] = $relative;
            }
        }
    }

    $hits['footer_class'] = array_values(array_unique($hits['footer_class']));
    $hits['borderless_fields'] = array_values(array_unique($hits['borderless_fields']));
    return $hits;
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

function upgrade_11_config_upsert_state(): array
{
    if (!data_exists('.config', '.meta')) {
        return [
            'action' => 'none',
            'message' => '.config resource does not exist yet; system:setup will create it with upsert support.',
        ];
    }

    $meta = data_read('.config', '.meta');
    if (!empty($meta['upsert'])) {
        return [
            'action' => 'none',
            'message' => '.config/.meta already has upsert enabled.',
        ];
    }

    return [
        'action' => 'update',
        'meta' => $meta,
        'message' => 'Add "upsert": true to .config/.meta so page settings are created on first save instead of pre-created on every page view.',
    ];
}

function upgrade_11_apply_config_upsert(array $state): bool
{
    if (($state['action'] ?? '') !== 'update') {
        return false;
    }
    $meta = $state['meta'];
    $meta['upsert'] = true;
    return data_create('.config', '.meta', $meta) !== false;
}

function upgrade_11_gitignore_rules(): array
{
    return [
        [
            'lines' => ['/static/_thumb_/'],
            'untrack' => 'static/_thumb_',
            'label' => 'generated thumbnail cache',
        ],
        [
            'lines' => ['/data/.jobs/*', '!/data/.jobs/.meta'],
            'untrack' => 'data/.jobs',
            'label' => 'job queue records (churns constantly, not real content)',
        ],
        [
            'lines' => ['/data/.state/*', '!/data/.state/.meta'],
            'untrack' => 'data/.state',
            'label' => 'scheduler run state (mutates every minute; tracking it causes recurring ext:sync rebase conflicts — see NIMBLY.md §19)',
        ],
    ];
}

function upgrade_11_gitignore_state(): array
{
    $file = BASE_DIR . 'ext/.gitignore';
    $rules = upgrade_11_gitignore_rules();

    if (!is_file($file)) {
        return [
            'action' => 'missing',
            'file' => $file,
            'missing' => $rules,
            'message' => 'ext/.gitignore does not exist; skipping generated/runtime data ignore rule migration.',
        ];
    }

    $content = file_get_contents($file);
    $missing = [];
    foreach ($rules as $rule) {
        foreach ($rule['lines'] as $line) {
            if (!preg_match('/^\s*' . preg_quote($line, '/') . '\s*$/m', $content)) {
                $missing[] = $rule;
                continue 2;
            }
        }
    }

    if (empty($missing)) {
        return [
            'action' => 'none',
            'file' => $file,
            'missing' => [],
            'message' => 'ext/.gitignore already ignores generated/runtime data (thumbnail cache, job queue, scheduler state).',
        ];
    }

    return [
        'action' => 'update',
        'file' => $file,
        'missing' => $missing,
        'message' => 'Add missing ignore rules to ext/.gitignore for: ' . implode(', ', array_column($missing, 'label')) . '.',
    ];
}

function upgrade_11_apply_gitignore(array $state): bool
{
    if (($state['action'] ?? '') !== 'update') {
        return false;
    }

    $content = rtrim(file_get_contents($state['file']), "\r\n") . "\n";
    foreach ($state['missing'] as $rule) {
        $content .= implode("\n", $rule['lines']) . "\n";
    }
    return file_put_contents($state['file'], $content) !== false;
}

function upgrade_11_collect_sc(string $pattern): array
{
    $hits = [];
    foreach (['tpl', 'uri', 'modules', 'lib'] as $source_dir) {
        $root = BASE_DIR . 'ext/' . $source_dir;
        foreach (upgrade_11_collect_files($root, ['tpl', 'php', 'inc']) as $file) {
            $content = file_get_contents($file);
            $count = preg_match_all($pattern, $content);
            if ($count > 0) {
                $hits[] = [$file, $count];
            }
        }
    }
    return $hits;
}

function upgrade_11_php_usage_count(string $content, array $function_names = [], array $library_names = []): int
{
    $tokens = token_get_all($content);
    $function_lookup = array_fill_keys($function_names, true);
    $library_lookup = array_fill_keys($library_names, true);
    $count = 0;

    foreach ($tokens as $i => $token) {
        if (!is_array($token) || $token[0] !== T_STRING) {
            continue;
        }

        $name = $token[1];

        if (isset($function_lookup[$name])) {
            for ($j = $i + 1; $j < count($tokens); $j++) {
                $next = $tokens[$j];
                if (is_array($next) && in_array($next[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                if ($next === '(') {
                    $count++;
                }
                break;
            }
            continue;
        }

        if (!in_array($name, ['load_library', 'load_libraries'], true)) {
            continue;
        }

        $depth = 0;
        $inside = false;
        for ($j = $i + 1; $j < count($tokens); $j++) {
            $next = $tokens[$j];
            if ($next === '(') {
                $depth++;
                $inside = true;
                continue;
            }
            if ($next === ')') {
                $depth--;
                if ($inside && $depth <= 0) {
                    break;
                }
                continue;
            }
            if (!$inside || !is_array($next) || $next[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            $value = trim($next[1], "'\"");
            if (isset($library_lookup[$value])) {
                $count++;
            }
        }
    }

    return $count;
}

function upgrade_11_collect_php_usage(array $function_names = [], array $library_names = []): array
{
    $hits = [];
    foreach (['uri', 'modules', 'lib'] as $source_dir) {
        $root = BASE_DIR . 'ext/' . $source_dir;
        foreach (upgrade_11_collect_files($root, ['php', 'inc']) as $file) {
            $content = file_get_contents($file);
            $count = upgrade_11_php_usage_count($content, $function_names, $library_names);
            if ($count > 0) {
                $hits[] = [$file, $count];
            }
        }
    }
    return $hits;
}

function upgrade_11_merge_hits(array ...$hit_sets): array
{
    $by_path = [];
    foreach ($hit_sets as $hits) {
        foreach ($hits as [$path, $count]) {
            if (!isset($by_path[$path])) {
                $by_path[$path] = 0;
            }
            $by_path[$path] += $count;
        }
    }

    $result = [];
    foreach ($by_path as $path => $count) {
        $result[] = [$path, $count];
    }
    return $result;
}

function upgrade_11_rewrite_php_usage(string $content, array $function_map = [], array $library_map = []): string
{
    $tokens = token_get_all($content);
    $out = '';
    $library_pending = false;
    $library_depth = null;

    foreach ($tokens as $i => $token) {
        $text = is_array($token) ? $token[1] : $token;

        if ($library_pending) {
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $out .= $text;
                continue;
            }
            if ($text === '(') {
                $library_pending = false;
                $library_depth = 1;
                $out .= $text;
                continue;
            }
            $library_pending = false;
        }

        if ($library_depth !== null) {
            if ($text === '(') {
                $library_depth++;
            } elseif ($text === ')') {
                $library_depth--;
            } elseif (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $quote = $text[0];
                $value = trim($text, "'\"");
                if (isset($library_map[$value])) {
                    $text = $quote . $library_map[$value] . $quote;
                }
            }

            $out .= $text;
            if ($library_depth <= 0) {
                $library_depth = null;
            }
            continue;
        }

        if (is_array($token) && $token[0] === T_STRING) {
            if (isset($function_map[$token[1]])) {
                for ($j = $i + 1; $j < count($tokens); $j++) {
                    $next = $tokens[$j];
                    if (is_array($next) && in_array($next[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        continue;
                    }
                    if ($next === '(') {
                        $text = $function_map[$token[1]];
                    }
                    break;
                }
            } elseif (in_array($token[1], ['load_library', 'load_libraries'], true)) {
                $library_pending = true;
            }
        }

        $out .= $text;
    }

    return $out;
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

function upgrade_11_lookup_helper_collect(): array
{
    return upgrade_11_collect_php_usage(['lookup_data'], ['lookup']);
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

function upgrade_11_apply_lookup_helper(array $hits): int
{
    $migrated = 0;
    foreach ($hits as [$path]) {
        $content = file_get_contents($path);
        $new = upgrade_11_rewrite_php_usage($content, ['lookup_data' => 'data_lookup'], ['lookup' => 'data']);
        if ($new !== $content) {
            file_put_contents($path, $new);
            $migrated++;
        }
    }
    return $migrated;
}

function upgrade_11_uuid_collect(): array
{
    return upgrade_11_merge_hits(
        upgrade_11_collect_php_usage(['uuid_sc'], ['uuid']),
        upgrade_11_collect_sc('/\[#uuid#\]/')
    );
}

function upgrade_11_apply_uuid(array $hits): int
{
    $migrated = 0;
    foreach ($hits as [$path]) {
        $content = file_get_contents($path);
        $new = upgrade_11_rewrite_php_usage($content, ['uuid_sc' => 'generate_uuid'], ['uuid' => 'util']);
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
    return upgrade_11_collect_php_usage(['salt_sc', 'slug_sc'], ['salt', 'md5', 'slug']);
}

function upgrade_11_apply_util(array $hits): int
{
    $migrated = 0;
    foreach ($hits as [$path]) {
        $content = file_get_contents($path);
        $new = upgrade_11_rewrite_php_usage(
            $content,
            ['salt_sc' => 'generate_salt', 'slug_sc' => 'make_slug'],
            ['salt' => 'util', 'md5' => 'util', 'slug' => 'util']
        );
        if ($new !== $content) {
            file_put_contents($path, $new);
            $migrated++;
        }
    }
    return $migrated;
}

function upgrade_11_manual_helper_collect(): array
{
    $removed_helpers = [
        'get_gallery_json_sc',
        'get_meta_data_sc',
        'get_pages_sc',
        'host_sc',
        'implode_sc',
        'int_sc',
        'is_dev_env_sc',
        'jget_value',
        'jget_variable',
        'key_access_sc',
        'md5_sc',
        'reverse_lookup_data',
        'reverse_lookup_sc',
        'rkey_sc',
        'sticky_post_sc',
        'strip_sc',
        'sys_libraries_sc',
        'unquote_sc',
    ];

    return upgrade_11_collect_php_usage($removed_helpers);
}

function upgrade_11_removed_library_collect(): array
{
    $removed_libraries = [
        'get-gallery-json',
        'get-meta-data',
        'get-pages',
        'host',
        'implode',
        'int',
        'is-dev-env',
        'key-access',
        'reverse-lookup',
        'rkey',
        'sticky-post',
        'strip',
        'sys-libraries',
        'unquote',
    ];
    $library_pattern = implode('|', array_map('preg_quote', $removed_libraries));

    return upgrade_11_merge_hits(
        upgrade_11_collect_php_usage([], $removed_libraries),
        upgrade_11_collect_sc("/\[#(?:{$library_pattern})(?:\s|#)/s")
    );
}

function upgrade_11_role_permissions_collect(): array
{
    load_library('permissions');
    $roles = data_read('roles');
    if (!is_array($roles)) {
        return [];
    }
    $result = [];
    foreach ($roles as $role_id => $role) {
        if (!is_array($role)) {
            continue;
        }
        $features = $role['features'] ?? '';
        if (trim((string)$features) === '(all)') {
            continue;
        }
        $tokens = permission_token_list($features);
        $expanded = permission_expand_features($tokens);
        if ($tokens === $expanded) {
            continue;
        }
        $result[$role_id] = [
            'from' => implode(',', $tokens),
            'to' => implode(',', $expanded),
        ];
    }
    return $result;
}

function upgrade_11_role_permissions_apply(array $roles): int
{
    $updated = 0;
    foreach ($roles as $role_id => $change) {
        if (data_update('roles', $role_id, ['features' => $change['to']]) !== false) {
            $updated++;
        }
    }
    return $updated;
}

function upgrade_11_core_routes_collect(): array
{
    $routes = [
        ['route' => 'nb-admin/roles/(id)', 'order' => 200],
    ];
    $missing = [];
    foreach ($routes as $route) {
        if (!data_exists('.routes', md5($route['route']))) {
            $missing[] = $route;
        }
    }
    return $missing;
}

function upgrade_11_core_routes_apply(array $routes): int
{
    $created = 0;
    foreach ($routes as $route) {
        if (data_create('.routes', md5($route['route']), $route)) {
            $created++;
        }
    }
    return $created;
}

$yes = in_array('--yes', $argv, true) || in_array('-y', $argv, true);

migrate_10_bootstrap();
$migration    = migrate_10_collect();
$role_permissions = upgrade_11_role_permissions_collect();
$core_routes = upgrade_11_core_routes_collect();
$users_email  = users_email_index_collect();
[$moves, $skipped] = migrate_lib_collect();
$env          = upgrade_11_read_env();
$paths        = upgrade_11_paths_from_env($env);
$htaccess     = upgrade_11_htaccess_state($env['PEPPER'] ?? '', $paths['base_path'], $paths['rewrite_base_path']);
$tw_elements  = upgrade_11_tailwind_elements_files();
$ui_migration = upgrade_11_ui_migration_collect();
$tw_entry     = upgrade_11_tailwind_entrypoint_state();
$daisyui      = upgrade_11_daisyui_themes_state();
$config_upsert = upgrade_11_config_upsert_state();
$gitignore    = upgrade_11_gitignore_state();
$get_key_hits = upgrade_11_get_key_collect();
$jget_hits    = upgrade_11_jget_collect();
$i18n_hits    = upgrade_11_get_i18n_collect();
$lookup_hits  = upgrade_11_lookup_collect();
$lookup_helper_hits = upgrade_11_lookup_helper_collect();
$uuid_hits    = upgrade_11_uuid_collect();
$util_hits    = upgrade_11_util_collect();
$manual_helper_hits = upgrade_11_manual_helper_collect();
$removed_library_hits = upgrade_11_removed_library_collect();

$has_work = migrate_10_has_work($migration)
    || !empty($role_permissions)
    || !empty($core_routes)
    || users_email_index_has_work($users_email)
    || !empty($users_email['duplicates'])
    || !empty($moves)
    || in_array($htaccess['action'], ['write', 'recreate_mod_php', 'recreate_cgi_pass_auth'], true)
    || !empty($tw_elements)
    || !empty($ui_migration['footer_class'])
    || !empty($ui_migration['borderless_fields'])
    || $tw_entry['action'] === 'update'
    || $daisyui['action'] === 'warn'
    || $config_upsert['action'] === 'update'
    || $gitignore['action'] === 'update'
    || !empty($get_key_hits)
    || !empty($jget_hits)
    || !empty($i18n_hits)
    || !empty($lookup_hits)
    || !empty($lookup_helper_hits)
    || !empty($uuid_hits)
    || !empty($util_hits)
    || !empty($manual_helper_hits)
    || !empty($removed_library_hits);

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

if (!empty($role_permissions)) {
    echo "\n[" . ++$step . "] Role permission migration\n\n";
    foreach ($role_permissions as $role_id => $change) {
        echo "  roles/{$role_id}\n";
        echo "    " . $change['from'] . "\n";
        echo "    -> " . $change['to'] . "\n";
    }
}

if (!empty($core_routes)) {
    echo "\n[" . ++$step . "] Core route registration\n\n";
    foreach ($core_routes as $route) {
        echo '  Create .routes/' . md5($route['route']) . ' for ' . $route['route'] . "\n";
    }
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

if ($config_upsert['action'] === 'update') {
    echo "\n[" . ++$step . "] .config upsert migration\n\n";
    echo '  ' . $config_upsert['message'] . "\n";
}

if ($gitignore['action'] !== 'none') {
    echo "\n[" . ++$step . "] ext/.gitignore migration\n\n";
    echo '  ' . $gitignore['message'] . "\n";
    if ($gitignore['action'] === 'update') {
        foreach ($gitignore['missing'] as $rule) {
            echo "  If ext/{$rule['untrack']} is already tracked, remove it from the git index after this migration.\n";
        }
    }
}

if (!empty($tw_elements)) {
    echo "\n[" . ++$step . "] Tailwind Elements static asset cleanup\n\n";
    foreach ($tw_elements as $file) {
        echo '  Delete ' . str_replace(BASE_DIR, '', $file) . "\n";
    }
}

if (!empty($ui_migration['footer_class']) || !empty($ui_migration['borderless_fields'])) {
    echo "\n[" . ++$step . "] Manual Tailwind Elements / DaisyUI visual review\n\n";
    if (!empty($ui_migration['footer_class'])) {
        echo "  Generic .footer usage may inherit DaisyUI 5's grid layout and gap:\n";
        foreach ($ui_migration['footer_class'] as $file) {
            echo "    - {$file}\n";
        }
        echo "  Rename project footers to a project-specific class unless DaisyUI's footer component is intended.\n\n";
    }
    if (!empty($ui_migration['borderless_fields'])) {
        echo "  Form controls with border-0 need a visual check after Tailwind Elements removal:\n";
        foreach ($ui_migration['borderless_fields'] as $file) {
            echo "    - {$file}\n";
        }
        echo "  Add an explicit visible border when the removed data-te input styling previously supplied it.\n";
    }
    cli_tip("These diagnostics are warnings only; the command does not rewrite project-specific UI classes.");
}

foreach ([
    [$get_key_hits, '[#get-key#] → [#get#]'],
    [$jget_hits,    '[#jget#] → [#get#]'],
    [$i18n_hits,    '[#get-i18n#] → [#get#]'],
    [$lookup_hits,  '[#lookup#] → [#get#]'],
    [$lookup_helper_hits, 'load_library(lookup) → data; lookup_data() → data_lookup()'],
    [$uuid_hits,    'load_library(uuid) → util; uuid_sc() → generate_uuid(); [#uuid#] → [#get uuid#]'],
    [$util_hits,    'load_library/load_libraries(salt|md5|slug) → util; salt_sc() → generate_salt(); slug_sc() → make_slug()'],
] as [$hits, $label]) {
    if (!empty($hits)) {
        $total = array_sum(array_column($hits, 1));
        echo "\n[" . ++$step . "] Replace {$label} ({$total} occurrence" . ($total === 1 ? '' : 's') . " in " . count($hits) . " file" . (count($hits) === 1 ? '' : 's') . ")\n\n";
        foreach ($hits as [$path, $count]) {
            echo '  ' . str_replace(BASE_DIR, '', $path) . " ({$count})\n";
        }
    }
}

if (!empty($manual_helper_hits)) {
    $total = array_sum(array_column($manual_helper_hits, 1));
    echo "\n[" . ++$step . "] Manual migration required for removed PHP helpers ({$total} occurrence" . ($total === 1 ? '' : 's') . " in " . count($manual_helper_hits) . " file" . (count($manual_helper_hits) === 1 ? '' : 's') . ")\n\n";
    foreach ($manual_helper_hits as [$path, $count]) {
        echo '  ' . str_replace(BASE_DIR, '', $path) . " ({$count})\n";
    }
    cli_tip("Review each call site. These helpers were removed in core 1.1.0 and do not all have a safe automatic rewrite.");
}

if (!empty($removed_library_hits)) {
    $total = array_sum(array_column($removed_library_hits, 1));
    echo "\n[" . ++$step . "] Manual migration required for removed core libraries/shortcodes ({$total} occurrence" . ($total === 1 ? '' : 's') . " in " . count($removed_library_hits) . " file" . (count($removed_library_hits) === 1 ? '' : 's') . ")\n\n";
    foreach ($removed_library_hits as [$path, $count]) {
        echo '  ' . str_replace(BASE_DIR, '', $path) . " ({$count})\n";
    }
    cli_tip("These zero-use core libraries were removed before 1.1.0; replace them with project code or current core APIs.");
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

if (!empty($role_permissions)) {
    echo "\n=== Updating role permissions ===\n";
    $updated = upgrade_11_role_permissions_apply($role_permissions);
    echo "Updated {$updated} role" . ($updated === 1 ? '' : 's') . ".\n";
}

if (!empty($core_routes)) {
    echo "\n=== Registering core routes ===\n";
    $created = upgrade_11_core_routes_apply($core_routes);
    echo "Created {$created} route" . ($created === 1 ? '' : 's') . ".\n";
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

if (in_array($htaccess['action'], ['write', 'recreate_mod_php', 'recreate_cgi_pass_auth'], true)) {
    echo "\n=== Repairing .htaccess ===\n";
    upgrade_11_apply_htaccess($htaccess);
    if ($htaccess['action'] === 'write') {
        echo "Written: .htaccess\n";
    } elseif ($htaccess['action'] === 'recreate_cgi_pass_auth') {
        echo "Recreated: .htaccess (added CGIPassAuth for Bearer token API support under PHP-FPM)\n";
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

if ($config_upsert['action'] === 'update') {
    echo "\n=== Updating .config/.meta ===\n";
    if (upgrade_11_apply_config_upsert($config_upsert)) {
        echo "Updated: .config/.meta (upsert enabled)\n";
    } else {
        echo "ERROR: failed to update .config/.meta\n";
    }
}

if ($gitignore['action'] === 'update') {
    echo "\n=== Updating ext/.gitignore ===\n";
    if (upgrade_11_apply_gitignore($gitignore)) {
        echo "Updated: ext/.gitignore\n";
        foreach ($gitignore['missing'] as $rule) {
            cli_tip("If ext/{$rule['untrack']} is already tracked, run: git -C ext rm -r --cached {$rule['untrack']}");
        }
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

if (!empty($lookup_helper_hits)) {
    echo "\n=== Migrating lookup helper calls ===\n";
    echo "Updated " . upgrade_11_apply_lookup_helper($lookup_helper_hits) . " file(s).\n";
}

if (!empty($uuid_hits)) {
    echo "\n=== Migrating uuid to util ===\n";
    echo "Updated " . upgrade_11_apply_uuid($uuid_hits) . " file(s).\n";
}

if (!empty($util_hits)) {
    echo "\n=== Migrating salt/md5/slug to util ===\n";
    echo "Updated " . upgrade_11_apply_util($util_hits) . " file(s).\n";
}
