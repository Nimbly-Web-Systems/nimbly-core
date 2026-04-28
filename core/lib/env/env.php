<?php

function env($key, $default = '')
{
    static $env = null;
    if ($env === null) {
        $env = [];
        $file = $GLOBALS['SYSTEM']['file_base'] . '.env';
        if (!file_exists($file)) {
            return $default;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if ($line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $env[trim($k)] = trim($v);
        }
    }
    return $env[$key] ?? $default;
}
