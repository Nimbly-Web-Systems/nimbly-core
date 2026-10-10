<?php

/**
 * [#env KEY#] prints a setting. The settings core keeps secret are for PHP
 * only (env()): a template never needs them on a page.
 */
function env_sc($params)
{
    $key = get_param_value($params, 'key', current($params));
    $default = get_param_value($params, 'default', '');
    if (in_array((string)$key, env_secret_keys(), true)) {
        return $default;
    }
    return env($key, $default);
}

function env_secret_keys(): array
{
    return [
        'PEPPER', 'STATS_KEY', 'ADMIN_PASSWORD', 'SMTP_PASSWORD', 'AGENT_REMOTE_PASSWORD',
        'OPENAI_API_KEY', 'RESEND_API_KEY', 'GIT_TOKEN',
    ];
}

function env($key, $default = '')
{
    static $env = null;
    if ($env === null) {
        $env = [];
        $file = $GLOBALS['SYSTEM']['file_base'] . '.env';
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if ($line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $env[trim($k)] = trim($v);
        }
    }
    if (isset($env[$key])) {
        return $env[$key];
    }
    $proc = getenv($key);
    return $proc !== false ? $proc : $default;
}
