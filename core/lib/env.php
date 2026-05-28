<?php

function env_sc($params)
{
    $key = get_param_value($params, 'key', current($params));
    $default = get_param_value($params, 'default', '');
    return env($key, $default);
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
