<?php

function upgrade_11_read_env()
{
    $env_file = BASE_DIR . '.env';
    if (!file_exists($env_file)) {
        die("Error: .env not found. Run 'php core/cli/nimbly.php setup' first.\n");
    }

    $env = [];
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        [$key, $val] = array_map('trim', explode('=', $line, 2) + [1 => '']);
        $env[$key] = $val;
    }

    return $env;
}

function upgrade_11_paths_from_env($env)
{
    $base_path = $env['BASE_PATH'] ?? '/';
    if (empty($base_path)) {
        $base_path = '/';
    }
    if ($base_path[0] !== '/') {
        $base_path = '/' . $base_path;
    }
    if (substr($base_path, -1) !== '/') {
        $base_path .= '/';
    }

    return [
        'base_path' => $base_path,
        'rewrite_base_path' => ltrim($base_path, '/'),
    ];
}

function upgrade_11_htaccess_state($pepper, $base_path, $rewrite_base_path)
{
    $htaccess_file = BASE_DIR . '.htaccess';

    if (!file_exists($htaccess_file)) {
        return [
            'action' => 'write',
            'message' => 'Missing .htaccess — will create it for 1.1.',
            'path' => $htaccess_file,
            'content' => upgrade_11_render_htaccess($pepper, $base_path, $rewrite_base_path),
        ];
    }

    $htaccess_content = file_get_contents($htaccess_file);
    $has_mod_php = (bool) preg_match('/^php_(flag|value)\s/m', $htaccess_content);
    $existing_base = null;
    if (preg_match('/^RewriteBase\s+(.+)$/m', $htaccess_content, $m)) {
        $existing_base = trim($m[1]);
    }
    $base_mismatch = $existing_base !== null && $existing_base !== $base_path;

    if ($has_mod_php) {
        return [
            'action' => 'recreate_mod_php',
            'message' => '.htaccess contains mod_php directives — will recreate it for PHP-FPM compatibility.',
            'path' => $htaccess_file,
            'content' => upgrade_11_render_htaccess($pepper, $base_path, $rewrite_base_path),
        ];
    }

    if ($base_mismatch) {
        return [
            'action' => 'warn_base_mismatch',
            'message' => "Warning: .htaccess has RewriteBase '{$existing_base}' but BASE_PATH is '{$base_path}'.",
            'path' => $htaccess_file,
            'content' => null,
        ];
    }

    return [
        'action' => 'noop',
        'message' => '.htaccess already matches the 1.1-safe template assumptions.',
        'path' => $htaccess_file,
        'content' => null,
    ];
}

function upgrade_11_render_htaccess($pepper, $base_path, $rewrite_base_path)
{
    $content = file_get_contents(BASE_DIR . 'core/cli/setup/htaccess.tpl');
    $content = str_replace('%%PEPPER%%', $pepper, $content);
    $content = str_replace('%%REWRITE_BASE%%', $base_path, $content);
    $content = str_replace('%%REWRITE_BASE_PATH%%', $rewrite_base_path, $content);
    return $content;
}

function upgrade_11_apply_htaccess($state)
{
    if (empty($state['content']) || empty($state['path'])) {
        return false;
    }

    file_put_contents($state['path'], $state['content']);
    chmod($state['path'], 0640);
    return true;
}
