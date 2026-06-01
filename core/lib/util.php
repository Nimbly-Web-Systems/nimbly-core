<?php

function rrmdir($dir)
{
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

function _rmdirr($dir)
{
    foreach (glob($dir . '/*') as $file) {
        if (is_dir($file)) {
            _rmdirr($file);
        } else {
            unlink($file);
        }
    }
    @rmdir($dir);
}

function rmfiles($dir)
{
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

function dir_size($dir)
{
    $result = 0;
    foreach (glob(rtrim($dir, '/') . '/*', GLOB_NOSORT) as $f) {
        $result += is_file($f) ? filesize($f) : dir_size($f);
    }
    return $result;
}

function dot2rs($rs)
{
    if (empty($rs)) {
        return false;
    }
    $set = explode('.', $rs);
    if (count($set) === 1) {
        $resource = '.content';
        load_library('url-key');
        $uuid = url_key_sc();
        $field = $set[0];
    } else if (count($set) === 4 && empty($set[0])) {
        $resource = '.' . $set[1];
        $uuid = $set[2];
        $field = $set[3];
    } else if (count($set) !== 3) {
        return false;
    } else {
        $resource = $set[0];
        $uuid = $set[1];
        $field = $set[2];
    }
    return [$resource, $uuid, $field];
}


function max_upload_size()
{
    static $max_size = -1;

    if ($max_size < 0) {
        $post_max_size = _parse_size(ini_get('post_max_size'));
        if ($post_max_size > 0) {
            $max_size = $post_max_size;
        }

        $upload_max = _parse_size(ini_get('upload_max_filesize'));
        if ($upload_max > 0 && $upload_max < $max_size) {
            $max_size = $upload_max;
        }
    }
    return $max_size;
}

function _parse_size($size)
{
    $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
    $size = preg_replace('/[^0-9\.]/', '', $size);
    return $unit ? round($size * pow(1024, stripos('bkmgtpezy', $unit[0]))) : round($size);
}

function plain_text(string $html): string
{
    return preg_replace("/\n\s+/", "\n", rtrim(html_entity_decode(strip_tags($html))));
}

function generate_uuid(): string
{
    return gmp_strval(gmp_init(bin2hex(random_bytes(10)), 16), 36);
}

function generate_salt(): string
{
    // bcrypt only accepts [./A-Za-z0-9]; '+' must not map to '_'
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '0.'), '=');
}

function is_md5($val): bool
{
    return strlen($val) === 32 && ctype_xdigit($val);
}

function md5_uuid(string $uuid): string
{
    return is_md5($uuid) ? $uuid : md5($uuid);
}

function make_slug($value): string
{
    $result = is_array($value) ? implode(' ', $value) : (string)$value;
    $result = mb_strtolower($result, 'UTF-8');
    $result = preg_replace('/[^\p{L}\p{Nd}]+/u', '-', $result);
    return trim($result, '-');
}

function resolve_i18n($val, $lang)
{
    if (!is_array($val)) {
        return $val;
    } else if (isset($val[$lang])) {
        return $val[$lang];
    } else if ($lang === 'auto') {
        load_library('detect-language');
        $detected = detect_language_sc();
        if (!empty($val[$detected])) {
            return $val[$detected];
        }
        return current(array_filter($val)) ?: '';
    } else {
        return '';
    }
}
