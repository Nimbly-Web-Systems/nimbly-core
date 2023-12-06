<?php

function rrmdir($dir) {
    if (is_dir($dir) !== true) {
        return unlink($dir);
    }
    $objects = scandir($dir);
    foreach ($objects as $object) {
        if ($object === '.' || $object === "..") {
            continue;
        }
        if (rrmdir($dir . '/' . $object) !== true) {
            return false;
        }
    }
    reset($objects);
    return rmdir($dir);
}

function rmfiles($dir) {
    if (is_dir($dir) !== true) {
        return unlink($dir);
    }
    $objects = scandir($dir);
    foreach ($objects as $object) {
        if ($object[0] === '.') {
            continue;
        }
        $path = $dir . '/' . $object;
        if (is_dir($path)) {
            continue;
        }
        if (unlink($path) !== true) {
            return false;
        }
    }
    return true;
}

function dir_size($dir) {
    $result = 0;
    foreach (glob(rtrim($dir, '/').'/*', GLOB_NOSORT) as $f) {
        $result += is_file($f) ? filesize($f) : dir_size($f);
    }
    return $result;
}
