<?php

/**
 * Checks used by system:repair. Each one compares a generated file with what
 * the current core would generate and reports the difference.
 */

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

    $content = (string) file_get_contents($base_dir . 'core/cli/setup/htaccess.tpl');
    $content = str_replace('%%PEPPER%%', $pepper[1], $content);
    $content = str_replace('%%REWRITE_BASE%%', $base_path, $content);
    $content = str_replace('%%REWRITE_BASE_PATH%%', ltrim($base_path, '/'), $content);

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
