<?php

/**
 * Nimbly CLI — setup command
 *
 * Usage: php core/cli/nimbly.php setup [alias=oddone|base_path=/oddone] [app_env=stage]
 *
 * Safe to re-run — existing records and files are never overwritten.
 */

if (php_sapi_name() !== 'cli') {
    die("nimbly.php must be run from the command line.\n");
}

if (!defined('BASE_DIR')) define('BASE_DIR', realpath(__DIR__ . '/../../..') . '/');
define('SETUP_DIR', __DIR__ . '/');

// -----------------------------------------------------------------------
// Bootstrap Nimbly minimally (no HTTP context)
// -----------------------------------------------------------------------

$GLOBALS['SYSTEM'] = [
    'file_base'  => BASE_DIR,
    'env_paths'  => ['ext', 'core'],
    'modules'    => ['root' => '/'],
    'variables'  => [],
    'uri'        => '',
];

require_once BASE_DIR . 'core/lib/find.php';
load_library('salt');

// -----------------------------------------------------------------------
// Prompt helpers
// -----------------------------------------------------------------------

function nb_prompt(string $question, string $default = '', string $env_var = ''): string {
    if ($env_var !== '' && ($env_val = getenv($env_var)) !== false && $env_val !== '') {
        echo $question . ': ' . $env_val . " [from \$$env_var]\n";
        return $env_val;
    }
    $hint = $default !== '' ? " [$default]" : '';
    echo $question . $hint . ': ';
    $input = fgets(STDIN);
    if ($input === false) {
        fwrite(STDERR, "\nError: input is required.\n");
        exit(1);
    }
    $value = trim($input);
    return $value !== '' ? $value : $default;
}

function nb_prompt_password(string $question, string $env_var = ''): string {
    if ($env_var !== '' && ($env_val = getenv($env_var)) !== false && $env_val !== '') {
        echo $question . ': [from $' . $env_var . "]\n";
        return $env_val;
    }
    echo $question . ': ';
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        system('stty -echo');
    }
    $input = fgets(STDIN);
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        system('stty echo');
    }
    echo "\n";
    if ($input === false) {
        fwrite(STDERR, "Error: input is required.\n");
        exit(1);
    }
    $value = trim($input);
    return $value;
}

function nb_cli_options(array $argv): array {
    $options = [];
    foreach (array_slice($argv, 2) as $arg) {
        $arg = ltrim($arg, '-');
        if ($arg === '' || !str_contains($arg, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $arg, 2);
        $key = str_replace('-', '_', trim($key));
        if ($key !== '') {
            $options[$key] = trim($value);
        }
    }
    return $options;
}

function nb_normalize_base_path(string $base_path): string {
    $base_path = trim($base_path);
    if ($base_path === '' || $base_path === '/') {
        return '/';
    }
    $base_path = trim($base_path, '/');
    return '/' . $base_path . '/';
}

function nb_env_set(array $lines, string $key, string $value, ?string $after_key = null): array {
    $found = false;
    foreach ($lines as $i => $line) {
        if (preg_match('/^' . preg_quote($key, '/') . '\s*=/', $line)) {
            $lines[$i] = $key . '=' . $value;
            $found = true;
            break;
        }
    }
    if ($found) {
        return $lines;
    }
    if ($after_key !== null) {
        foreach ($lines as $i => $line) {
            if (preg_match('/^' . preg_quote($after_key, '/') . '\s*=/', $line)) {
                array_splice($lines, $i + 1, 0, [$key . '=' . $value]);
                return $lines;
            }
        }
    }
    $lines[] = $key . '=' . $value;
    return $lines;
}

function nb_render_htaccess(string $pepper, string $base_path, string $rewrite_base_path): string {
    $content = file_get_contents(SETUP_DIR . 'htaccess.tpl');
    $content = str_replace('%%PEPPER%%', $pepper, $content);
    $content = str_replace('%%REWRITE_BASE%%', $base_path, $content);
    $content = str_replace('%%REWRITE_BASE_PATH%%', $rewrite_base_path, $content);
    return $content;
}

// -----------------------------------------------------------------------
// Load / create .env
// -----------------------------------------------------------------------

$env_file = BASE_DIR . '.env';
$env = [];
$env_lines_existing = [];
$cli_options = nb_cli_options($argv);

if (file_exists($env_file)) {
    $env_lines_existing = file($env_file, FILE_IGNORE_NEW_LINES) ?: [];
    foreach ($env_lines_existing as $line) {
        if (trim($line) === '') continue;
        if (str_starts_with(trim($line), '#')) continue;
        [$key, $val] = array_map('trim', explode('=', $line, 2) + [1 => '']);
        $env[$key] = $val;
    }
}

// Resolve BASE_PATH (CLI overrides env var; env var overrides .env)
$base_path_arg = $cli_options['base_path'] ?? $cli_options['base'] ?? null;
if ($base_path_arg === null && isset($cli_options['alias'])) {
    $base_path_arg = $cli_options['alias'];
}
$base_path_env = getenv('BASE_PATH');
$base_path = $base_path_arg ?? ($base_path_env !== false && $base_path_env !== '' ? $base_path_env : ($env['BASE_PATH'] ?? '/'));
$base_path = nb_normalize_base_path($base_path);
$base_path_requested = $base_path_arg !== null || ($base_path_env !== false && $base_path_env !== '');

// APP_ENV selects environment-specific schedules and gives projects one
// shared environment label. Existing .env files are never overwritten.
$app_env_env = getenv('APP_ENV');
$app_env = $cli_options['app_env'] ?? $cli_options['env'] ?? ($app_env_env !== false && $app_env_env !== '' ? $app_env_env : ($env['APP_ENV'] ?? 'dev'));

// RewriteBase path without leading slash, used in RewriteCond patterns
// e.g. "" for root install, "mysite/" for subdirectory install
$rewrite_base_path = ltrim($base_path, '/');

// Pepper (env var overrides .env; generate if neither exists)
$pepper = getenv('PEPPER') ?: ($env['PEPPER'] ?? '');
if (empty($pepper)) {
    $pepper = salt_sc();
    echo "Generated new PEPPER: $pepper\n";
}

// Write/update .env
if (!file_exists($env_file)) {
    $env_lines = [
        '# Nimbly site configuration',
        '',
        'APP_ENV=' . $app_env,
    ];
    if ($base_path !== '/') {
        $env_lines[] = 'BASE_PATH=' . $base_path;
    }
    $env_lines[] = 'PEPPER=' . $pepper;

    $env_content = implode("\n", $env_lines) . "\n";
    file_put_contents($env_file, $env_content);
    echo "Written: .env\n";
} else {
    $env_lines = $env_lines_existing;
    $changed = false;

    if (!isset($env['APP_ENV'])) {
        $env_lines = nb_env_set($env_lines, 'APP_ENV', $app_env);
        $changed = true;
    } elseif (isset($cli_options['app_env']) || isset($cli_options['env'])) {
        $env_lines = nb_env_set($env_lines, 'APP_ENV', $app_env);
        $changed = true;
    }

    if ($base_path !== '/' && (!isset($env['BASE_PATH']) || $base_path_requested)) {
        $env_lines = nb_env_set($env_lines, 'BASE_PATH', rtrim($base_path, '/'), 'APP_ENV');
        $changed = true;
    }

    if (!isset($env['PEPPER'])) {
        $env_lines = nb_env_set($env_lines, 'PEPPER', $pepper);
        $changed = true;
    }

    if ($changed) {
        file_put_contents($env_file, implode("\n", $env_lines) . "\n");
        echo "Updated: .env\n";
    } else {
        echo "Skipped: .env (already exists)\n";
    }
}

// Make PEPPER available to encrypt library
$_SERVER['PEPPER'] = $pepper;
$_SERVER['APP_ENV'] = $app_env;

// Load data/encrypt now that PEPPER is set
load_library('data');
load_library('encrypt');

// -----------------------------------------------------------------------
// Check / generate .htaccess
// -----------------------------------------------------------------------

$htaccess_file = BASE_DIR . '.htaccess';

if (!file_exists($htaccess_file)) {
    file_put_contents($htaccess_file, nb_render_htaccess($pepper, $base_path, $rewrite_base_path));
    chmod($htaccess_file, 0640);
    echo "Written: .htaccess\n";
} else {
    $htaccess_content = file_get_contents($htaccess_file);
    $expected_htaccess = nb_render_htaccess($pepper, $base_path, $rewrite_base_path);
    $has_mod_php = (bool) preg_match('/^php_(flag|value)\s/m', $htaccess_content);
    $existing_base = null;
    if (preg_match('/^RewriteBase\s+(.+)$/m', $htaccess_content, $m)) {
        $existing_base = trim($m[1]);
    }
    $base_mismatch = $existing_base !== null && $existing_base !== $base_path;

    if ($has_mod_php) {
        // mod_php directives (php_flag/php_value) are not supported under PHP-FPM — regenerate silently
        file_put_contents($htaccess_file, $expected_htaccess);
        chmod($htaccess_file, 0640);
        echo "Recreated: .htaccess (removed mod_php directives, not supported under PHP-FPM)\n";
    } elseif ($base_mismatch) {
        echo "Warning: .htaccess has RewriteBase '$existing_base' but BASE_PATH is '$base_path'.\n";
        $choice = nb_prompt('How to proceed? [leave/recreate]', 'leave');
        if (strtolower(trim($choice)) === 'recreate') {
            file_put_contents($htaccess_file, $expected_htaccess);
            chmod($htaccess_file, 0640);
            echo "Recreated: .htaccess\n";
        } else {
            echo "Skipped: .htaccess (left as-is)\n";
        }
    } elseif ($base_path_requested && trim($htaccess_content) !== trim($expected_htaccess)) {
        echo "Warning: .htaccess differs from the setup template for BASE_PATH '$base_path'.\n";
        $choice = nb_prompt('How to proceed? [leave/recreate]', 'recreate');
        if (strtolower(trim($choice)) === 'recreate') {
            file_put_contents($htaccess_file, $expected_htaccess);
            chmod($htaccess_file, 0640);
            echo "Recreated: .htaccess\n";
        } else {
            echo "Skipped: .htaccess (left as-is)\n";
        }
    } else {
        echo "Skipped: .htaccess (already exists)\n";
    }
}

// -----------------------------------------------------------------------
// Create directory structure and htaccess guards
// -----------------------------------------------------------------------

$dirs_deny  = ['ext', 'core'];
$dirs_allow = ['ext/data/.tmp/cache', 'ext/static', 'core/static'];
$dirs_create = [
    'ext', 'ext/data', 'ext/static', 'ext/lib', 'ext/modules',
    'ext/tpl', 'ext/uri', 'ext/data/.tmp', 'ext/data/.tmp/cache',
    'ext/data/.tmp/logs', 'ext/data/.tmp/sessions', 'ext/data/.config',
    'ext/data/.i18n', 'ext/data/.state',
];

foreach ($dirs_create as $dir) {
    $path = BASE_DIR . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0750, true);
        echo "Created dir: $dir\n";
    }
}

foreach ($dirs_deny as $dir) {
    $dst = BASE_DIR . $dir . '/.htaccess';
    if (!file_exists($dst)) {
        copy(SETUP_DIR . 'deny.htaccess', $dst);
    }
}

foreach ($dirs_allow as $dir) {
    $dst = BASE_DIR . $dir . '/.htaccess';
    if (!file_exists($dst)) {
        copy(SETUP_DIR . 'allow.htaccess', $dst);
    }
}

// -----------------------------------------------------------------------
// Copy theme scaffold
// -----------------------------------------------------------------------

$theme_dst = BASE_DIR . 'ext/tailwind.theme.js';
if (!file_exists($theme_dst)) {
    copy(SETUP_DIR . 'tailwind.theme.js', $theme_dst);
    echo "Copied: ext/tailwind.theme.js\n";
}
if (!file_exists(BASE_DIR . 'ext/theme.css')) {
    touch(BASE_DIR . 'ext/theme.css');
    echo "Created: ext/theme.css\n";
}

// Create ext/.gitignore from template
$gitignore_dst = BASE_DIR . 'ext/.gitignore';
if (!file_exists($gitignore_dst)) {
    copy(SETUP_DIR . '.gitignore.tpl', $gitignore_dst);
    chmod($gitignore_dst, 0640);
    echo "Created: ext/.gitignore\n";
}

// -----------------------------------------------------------------------
// Determine what still needs to be set up
// -----------------------------------------------------------------------

$need_repo = !is_dir(BASE_DIR . 'ext/.git');
$need_site = !data_exists('.config', 'site');

$users_dir  = BASE_DIR . 'ext/data/users/';
$need_user  = true;
if (is_dir($users_dir)) {
    $user_files = array_filter(
        glob($users_dir . '*') ?: [],
        fn($f) => basename($f) !== '.meta'
    );
    $need_user = empty($user_files);
}

// -----------------------------------------------------------------------
// Prompt only for what is missing
// -----------------------------------------------------------------------

$ext_repo = '';
$sitename = '';
$email    = '';
$password = '';

if ($need_repo || $need_site || $need_user) {
    echo "\n--- Nimbly Setup ---\n\n";

    if ($need_repo) {
        $ext_repo = nb_prompt('Project repo URL (ext)', '', 'EXT_REPO');
    }

    if ($need_site) {
        $sitename = nb_prompt('Site name', 'My Nimbly Site', 'SITE_NAME');
    }

    if ($need_user) {
        $email = nb_prompt('Admin email', '', 'ADMIN_EMAIL');
        while (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if (!empty(getenv('ADMIN_EMAIL'))) die("Error: ADMIN_EMAIL env var is not a valid email address.\n");
            echo "Please enter a valid email address.\n";
            $email = nb_prompt('Admin email', '', 'ADMIN_EMAIL');
        }
        $password = nb_prompt_password('Admin password', 'ADMIN_PASSWORD');
        while (strlen($password) < 8) {
            if (!empty(getenv('ADMIN_PASSWORD'))) die("Error: ADMIN_PASSWORD env var must be at least 8 characters.\n");
            echo "Password must be at least 8 characters.\n";
            $password = nb_prompt_password('Admin password', 'ADMIN_PASSWORD');
        }
    }
}

// -----------------------------------------------------------------------
// Create ext/readme.md from template (needs $ext_repo)
// -----------------------------------------------------------------------

$readme_dst = BASE_DIR . 'ext/readme.md';
if (!file_exists($readme_dst)) {
    $core_repo = trim(shell_exec('git remote get-url origin 2>/dev/null') ?? '');
    $site_slug = basename(BASE_DIR);
    $readme = file_get_contents(SETUP_DIR . 'readme.md.tpl');
    $readme = str_replace('%%CORE_REPO%%', $core_repo ?: 'git@github.com:your-org/nimbly-core.git', $readme);
    $readme = str_replace('%%EXT_REPO%%',  $ext_repo  ?: 'git@github.com:your-org/your-project.git', $readme);
    $readme = str_replace('%%SITE_NAME%%', $site_slug ?: 'myproject', $readme);
    file_put_contents($readme_dst, $readme);
    chmod($readme_dst, 0640);
    echo "Created: ext/readme.md\n";
}

// -----------------------------------------------------------------------
// Create .config/site
// -----------------------------------------------------------------------

if ($need_site) {
    data_create('.config', 'site', [
        'name'        => $sitename,
        'description' => $sitename . ': a Nimbly site',
    ]);
    echo "Created: .config/site\n";
} else {
    echo "Skipped: .config/site (already exists)\n";
}

// -----------------------------------------------------------------------
// Create .content resource
// -----------------------------------------------------------------------

if (!data_exists('.content', '.meta')) {
    data_create_resource('.content', ['fields' => false]);
    echo "Created: .content resource\n";
} else {
    echo "Skipped: .content resource (already exists)\n";
}

// -----------------------------------------------------------------------
// Create .jobs resource
// -----------------------------------------------------------------------

load_library('job');
if (!data_exists('.jobs', '.meta')) {
    job_ensure_resource();
    echo "Created: .jobs resource\n";
} else {
    echo "Skipped: .jobs resource (already exists)\n";
}

// -----------------------------------------------------------------------
// Create core routes
// -----------------------------------------------------------------------

$routes = [
    ['route' => 'api',                                 'order' => 900],
    ['route' => 'api/v1/(resource)',                   'order' => 500],
    ['route' => 'api/v1/(resource)/import',            'order' => 100],
    ['route' => 'api/v1/(resource)/export',            'order' => 100],
    ['route' => 'api/v1/(resource)/(id)',              'order' => 200],
    ['route' => 'api/v1/(resource)/(id)/(id)',         'order' => 200],
    ['route' => 'api/v1/.pages/(id)',                  'order' => 200],
    ['route' => 'api/v1/.files/(id)',                  'order' => 200],
    ['route' => 'nb-admin/(resource)',                 'order' => 400],
    ['route' => 'nb-admin/(resource)/(id)',            'order' => 500],
    ['route' => 'nb-admin/(resource)/add',            'order' => 300],
    ['route' => 'nb-admin/(resource)/import',         'order' => 300],
    ['route' => 'nb-admin/pages/(id)',                 'order' => 200],
    ['route' => 'nb-admin/files/(id)',                 'order' => 200],
    ['route' => 'img/(id)',                            'order' => 500],
    ['route' => 'download/(id)',                       'order' => 500],
    ['route' => 'video/(id)',                          'order' => 500],
    ['route' => 'password-reset/(uuid)/(key)',         'order' => 200],
    ['route' => 'change-email/(uuid)/(newuuid)/(key)', 'order' => 200],
];

$routes_created = 0;
foreach ($routes as $route) {
    $uuid = md5($route['route']);
    if (!data_exists('.routes', $uuid)) {
        data_create('.routes', $uuid, $route);
        $routes_created++;
    }
}
echo $routes_created > 0
    ? "Created: $routes_created route(s)\n"
    : "Skipped: all routes (already exist)\n";

// -----------------------------------------------------------------------
// Create roles
// -----------------------------------------------------------------------

if (!data_exists('roles', 'admin')) {
    data_create('roles', 'admin', [
        'name'        => 'Admin',
        'description' => 'Technical system administration',
        'features'    => '(all)',
    ]);
    echo "Created: roles/admin\n";
}

if (!data_exists('roles', 'editor')) {
    data_create('roles', 'editor', [
        'name'        => 'Editor',
        'description' => 'Content writers, site maintainers',
        'features'    => 'manage-content',
    ]);
    echo "Created: roles/editor\n";
}

// -----------------------------------------------------------------------
// Create users resource
// -----------------------------------------------------------------------

if (!data_exists('users', '.meta')) {
    data_create('users', '.meta', [
        'fields' => [
            'email'    => ['type' => 'text',     'required' => true,  'name' => 'email'],
            'password' => ['type' => 'password', 'required' => true,  'name' => 'password', 'admin_col' => false],
            'roles'    => ['type' => 'select',   'multi'    => true,  'name' => 'roles', 'resource' => 'roles'],
            'name'     => ['type' => 'text',                          'name' => 'name'],
        ],
        'encrypt' => 'password',
    ]);
    echo "Created: users/.meta\n";
}

// -----------------------------------------------------------------------
// Create admin user
// -----------------------------------------------------------------------

if ($need_user) {
    $user_uuid = md5($email);
    if (!data_exists('users', $user_uuid)) {
        $salt = salt_sc();
        data_create('users', $user_uuid, [
            'email'    => $email,
            'roles'    => 'admin,user',
            'salt'     => $salt,
            'password' => encrypt($password, $salt),
        ]);
        echo "Created: admin user ($email)\n";
    } else {
        echo "Skipped: admin user ($email) already exists\n";
    }
} else {
    echo "Skipped: users already exist\n";
}

// -----------------------------------------------------------------------
// Done
// -----------------------------------------------------------------------

$cron_command = '* * * * * ' . PHP_BINARY . ' ' . BASE_DIR . 'core/cli/nimbly.php schedule:run';

echo "\nSetup complete. Run 'npm run build' to compile assets.\n";
echo "\nScheduler cron:\n";
echo "  $cron_command\n";
echo "\nThis single cron entry runs due scheduled commands, including queued jobs.\n";
echo "To customize the app schedule, run: php core/cli/nimbly.php schedule:publish\n";
