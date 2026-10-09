<?php

/**
 * Checks used by system:repair. Each one compares a generated file or a
 * system record with what the current core would write and reports the
 * difference.
 *
 * A check is a pair of functions: repair_<name>_state() returns
 * ['action' => ok|write|skip, 'message' => ...] and repair_<name>_apply()
 * takes that state and returns whether the change was made. A 'note' is
 * printed whatever the action.
 */

require_once __DIR__ . '/guards.php';
require_once __DIR__ . '/users_email_index.php';

function repair_checks(): array
{
    return ['htaccess', 'guards', 'gitignore', 'config_upsert', 'core_routes', 'users_email'];
}

function repair_htaccess_render(string $base_dir, string $pepper, string $base_path): string
{
    $content = (string) file_get_contents($base_dir . 'core/cli/setup/htaccess.tpl');
    $content = str_replace('%%PEPPER%%', $pepper, $content);
    $content = str_replace('%%REWRITE_BASE%%', $base_path, $content);
    return str_replace('%%REWRITE_BASE_PATH%%', ltrim($base_path, '/'), $content);
}

/**
 * Root .htaccess against the setup template. The pepper and the rewrite base
 * are taken from the file itself: they are what the site runs on.
 *
 * Returns ['action' => ok|write|skip, 'message' => ..., 'path' => ..., 'content' => ...].
 */
function repair_htaccess_state(string $base_dir): array
{
    $path = $base_dir . '.htaccess';
    $state = ['action' => 'skip', 'message' => '', 'path' => $path, 'content' => null];

    if (!is_file($path)) {
        $state['message'] = '.htaccess is missing; system:setup creates it.';
        return $state;
    }

    $current = (string) file_get_contents($path);
    if (!preg_match('/^[ \t]*SetEnv[ \t]+PEPPER[ \t]+(\S+)[ \t]*\r?$/m', $current, $pepper)) {
        $state['message'] = '.htaccess has no SetEnv PEPPER line; left alone.';
        return $state;
    }
    if (!preg_match('/^[ \t]*RewriteBase[ \t]+(\S+)[ \t]*\r?$/m', $current, $base)) {
        $state['message'] = '.htaccess has no RewriteBase line; left alone.';
        return $state;
    }

    $base_path = '/' . trim($base[1], '/');
    if ($base_path !== '/') {
        $base_path .= '/';
    }

    $content = repair_htaccess_render($base_dir, $pepper[1], $base_path);
    if ($content === $current) {
        $state['action'] = 'ok';
        $state['message'] = '.htaccess matches the current template.';
        return $state;
    }

    $state['action'] = 'write';
    $state['message'] = '.htaccess differs from the current template.';
    $state['content'] = $content;
    return $state;
}

/**
 * Writes the new .htaccess in place, so owner and mode stay as they are. The
 * previous file is kept next to it as .htaccess.before-repair.
 */
function repair_htaccess_apply(array $state): bool
{
    if ($state['action'] !== 'write') {
        return false;
    }
    if (!copy($state['path'], $state['path'] . '.before-repair')) {
        return false;
    }
    @chmod($state['path'] . '.before-repair', 0640);
    return file_put_contents($state['path'], $state['content']) !== false;
}

/**
 * Directory guards (the .htaccess files under ext/ and core/). A guard with
 * site-specific content is reported and never replaced.
 */
function repair_guards_state(string $base_dir): array
{
    $write = [];
    $custom = [];
    foreach (setup_sync_guards($base_dir, $base_dir . 'core/cli/setup/', false) as $guard) {
        if ($guard['action'] === 'custom') {
            $custom[] = $guard['dir'];
        } else {
            $write[] = $guard['dir'] . ($guard['action'] === 'created' ? ' (missing)' : ' (old form)');
        }
    }

    $message = empty($write) ? 'Directory guards match the current templates.' : 'Directory guards to write: ' . implode(', ', $write) . '.';
    $note = empty($custom) ? '' : 'Directory guards left alone, own content: ' . implode(', ', $custom) . '.';
    return ['action' => empty($write) ? 'ok' : 'write', 'message' => $message, 'note' => $note, 'base_dir' => $base_dir];
}

function repair_guards_apply(array $state): bool
{
    setup_sync_guards($state['base_dir'], $state['base_dir'] . 'core/cli/setup/');
    return repair_guards_state($state['base_dir'])['action'] === 'ok';
}

/**
 * Runtime data that must stay out of the ext repository: it changes on every
 * server by itself, so tracking it makes ext:sync conflict.
 */
function repair_gitignore_rules(): array
{
    return [
        'thumbnail cache' => ['/static/_thumb_/'],
        'job queue'       => ['/data/.jobs/*', '!/data/.jobs/.meta'],
        'scheduler state' => ['/data/.state/*', '!/data/.state/.meta'],
        'agent chats and runs' => [
            '/data/.agent_runs/*', '/data/.agent_events/*', '/data/.agent_actions/*', '/data/.agent_approvals/*',
            '/data/.agent_state/*', '/data/.agent_steps/*', '/data/.agent_conversations/*',
        ],
    ];
}

function repair_gitignore_state(string $base_dir): array
{
    $path = $base_dir . 'ext/.gitignore';
    if (!is_file($path)) {
        return ['action' => 'skip', 'message' => 'ext/.gitignore is missing; system:setup creates it.'];
    }

    $content = (string) file_get_contents($path);
    $missing = [];
    foreach (repair_gitignore_rules() as $label => $lines) {
        foreach ($lines as $line) {
            if (!preg_match('/^\s*' . preg_quote($line, '/') . '\s*$/m', $content)) {
                $missing[$label] = $lines;
                continue 2;
            }
        }
    }

    if (empty($missing)) {
        return ['action' => 'ok', 'message' => 'ext/.gitignore ignores the runtime data.'];
    }
    return [
        'action' => 'write',
        'message' => 'ext/.gitignore does not ignore: ' . implode(', ', array_keys($missing)) . '. A folder that is already tracked also needs git rm -r --cached.',
        'path' => $path,
        'missing' => $missing,
    ];
}

function repair_gitignore_apply(array $state): bool
{
    $content = rtrim((string) file_get_contents($state['path']), "\r\n") . "\n";
    foreach ($state['missing'] as $lines) {
        $content .= implode("\n", $lines) . "\n";
    }
    return file_put_contents($state['path'], $content) !== false;
}

/**
 * Page settings are created on first save. Without "upsert" on .config they
 * cannot be saved for a page that has none yet.
 */
function repair_config_upsert_state(): array
{
    if (!data_exists('.config', '.meta')) {
        return ['action' => 'skip', 'message' => 'The .config resource is missing; system:setup creates it.'];
    }
    $meta = data_read('.config', '.meta');
    if (!empty($meta['upsert'])) {
        return ['action' => 'ok', 'message' => '.config/.meta has upsert.'];
    }
    return ['action' => 'write', 'message' => '.config/.meta has no upsert.', 'meta' => $meta];
}

function repair_config_upsert_apply(array $state): bool
{
    $meta = $state['meta'];
    $meta['upsert'] = true;
    return data_create('.config', '.meta', $meta) !== false;
}

/**
 * Core routes added after a site was set up.
 */
function repair_core_routes_state(): array
{
    if (!data_exists('.routes', '.meta')) {
        return ['action' => 'skip', 'message' => 'The .routes resource is missing; system:setup creates it.'];
    }
    $missing = [];
    foreach ([
        ['route' => 'nb-admin/roles/(id)', 'order' => 200],
        ['route' => 'nb-admin/(resource)/deleted', 'order' => 300],
        ['route' => 'nb-admin/(resource)/(id)/history', 'order' => 300],
    ] as $route) {
        if (!data_exists('.routes', md5($route['route']))) {
            $missing[] = $route;
        }
    }

    if (empty($missing)) {
        return ['action' => 'ok', 'message' => 'Core routes are registered.'];
    }
    return [
        'action' => 'write',
        'message' => 'Core routes not registered: ' . implode(', ', array_column($missing, 'route')) . '.',
        'missing' => $missing,
    ];
}

function repair_core_routes_apply(array $state): bool
{
    $done = true;
    foreach ($state['missing'] as $route) {
        $done = data_create('.routes', md5($route['route']), $route) !== false && $done;
    }
    return $done;
}

/**
 * Login looks a user up by email through the index; "unique" is only added
 * when no two users share an address.
 */
function repair_users_email_state(): array
{
    $index = users_email_index_collect();
    if (empty($index['exists'])) {
        return ['action' => 'skip', 'message' => 'The users resource is missing; system:setup creates it.'];
    }

    $note = empty($index['duplicates']) ? '' : ' Shared by more than one user, so not made unique: ' . implode(', ', array_keys($index['duplicates'])) . '.';
    if (!users_email_index_has_work($index)) {
        return ['action' => 'ok', 'message' => 'Users are indexed by email.' . $note];
    }
    return ['action' => 'write', 'message' => 'Users are not fully indexed by email.' . $note, 'index' => $index];
}

function repair_users_email_apply(array $state): bool
{
    users_email_index_apply($state['index']);
    return !users_email_index_has_work(users_email_index_collect());
}
