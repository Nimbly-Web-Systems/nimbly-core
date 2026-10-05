<?php

/**
 * @doc `[include file]` includes a file if it exists inside the project
 */
function include_sc($params) {
    $file = realpath((string)current($params));
    $base = realpath($GLOBALS['SYSTEM']['file_base']);
    if ($file === false || $base === false || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) {
        return false;
    }
    include $file;
}
