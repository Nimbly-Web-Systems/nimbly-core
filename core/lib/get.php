<?php

/**
 * Unified variable getter. Resolves in this order:
 *   1. Flat key lookup (session → system; cookie and URL only when `from` asks for them)
 *   2. If key has dots: progressive prefix lookup in system variables
 *      (longest flat prefix found → traverse remainder into nested array)
 *   3. If still not found and key is resource.uuid.field format: data_lookup fallback
 *   4. If result is an i18n array (has a configured language key): auto-resolve
 *
 * @doc * [get varname]                   returns value of variable named `varname`
 * @doc * [get varname default=fallback]  returns `fallback` if variable is empty
 * @doc * [get resource.uuid.field]       looks up data record field (data_lookup fallback)
 * @doc * [get i18n-var]                  auto-resolves i18n arrays to current language
 * @doc * [get i18n-var lang=nl]          resolves to specific language
 * @doc * [get varname from=url]          also reads the URL parameter `varname` when the page has no such variable
 * @doc * [get varname from=cookie]       same for a cookie; `from=url,cookie` (or `url|cookie`) tries both in that order, `from=(any)` cookie then URL
 * @doc * [get varname echo]              echoes the value
 * @doc * [get varname json]              echoes json-encoded value
 */
function get_sc($params, $default = null, $from = null)
{
    if (is_array($params)) {
        $key = current($params);
    } else {
        $key = $params;
    }
    $key = preg_replace('/[^a-zA-Z0-9._-]/', '_', $key);

    if ($default === null && is_array($params) && count($params) >= 2) {
        $d = get_param_value($params, 'default');
        if ($d === null) {
            $d = next($params);
            $k = key($params);
            if ($k === $d) {
                $default = $d;
            }
        } else {
            $default = $d;
        }
    }

    $sources = get_request_sources($from ?? (is_array($params) ? get_param_value($params, 'from') : null));

    if (strpos($key, '.') === false) {
        $result = get_flat_lookup($key, $sources);
        if ($result === null) {
            // Single-segment fallback: try dot2rs (e.g. [#get img001#] → current page .content field)
            [$result, $found] = get_dot_resolve($key, $sources);
            if (!$found) {
                $result = $default;
            }
        }
    } else {
        [$result, $found] = get_dot_resolve($key, $sources);
        if (!$found) {
            $result = $default;
        }
    }

    if (is_array($result)) {
        $result = get_i18n_resolve($result, get_param_value($params, 'lang', 'auto'));
    }

    $echo = get_param_value($params, 'echo', false);
    $json = get_param_value($params, 'json', false);

    if (empty($result)) {
        $result = get_param_value($params, 'empty', $result);
        if ($echo || $json) {
            echo $result ?? '';
            return;
        }
    }

    if ($echo) {
        echo $result;
        return;
    }

    if ($json) {
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        return;
    }

    return $result;
}

/**
 * Request sources named by a `from` value: `url`, `cookie`, both (`url,cookie`
 * or `url|cookie`, tried in the order given) or `(any)` (cookie, then url).
 */
function get_request_sources($from)
{
    if (!is_string($from) || $from === '') {
        return [];
    }
    if ($from === '(any)') {
        return ['cookie', 'url'];
    }
    return array_values(array_intersect(array_map('trim', preg_split('/[,|]/', $from)), ['url', 'cookie']));
}

/**
 * Flat variable lookup: session → system, then the request sources the caller
 * asked for (see get_request_sources). Returns null when not found.
 */
function get_flat_lookup($key, $sources = [])
{
    if (isset($_SESSION['variables'][$key])) {
        return $_SESSION['variables'][$key];
    }
    if (isset($GLOBALS['SYSTEM']['variables'][$key])) {
        return $GLOBALS['SYSTEM']['variables'][$key];
    }
    if (empty($sources)) {
        return null;
    }
    load_library('request-input');
    foreach ($sources as $source) {
        if ($source === 'cookie' && isset($_COOKIE[$key])) {
            $cookie = $_COOKIE[$key];
            if (is_string($cookie)) {
                $cookie = filter_var($cookie, FILTER_SANITIZE_SPECIAL_CHARS);
            }
            return request_input_escape($cookie);
        }
        if ($source === 'url') {
            $req_get = filter_input(INPUT_GET, $key, FILTER_SANITIZE_SPECIAL_CHARS);
            if (isset($req_get)) {
                return request_input_escape($req_get);
            }
        }
    }
    return null;
}

/**
 * Resolves a dot-notation key. Returns [$value, $found].
 * Uses progressive prefix: tries longest flat key first, then shorter prefixes
 * with array traversal for the remainder. Falls back to data_lookup for 3-segment keys.
 */
function get_dot_resolve($key, $sources = [])
{
    $parts = explode('.', $key);
    $n = count($parts);
    $vars = $GLOBALS['SYSTEM']['variables'] ?? [];

    for ($len = $n; $len >= 1; $len--) {
        $prefix = implode('.', array_slice($parts, 0, $len));

        if ($len === $n) {
            // Exact match: same lookup as a flat key
            $val = get_flat_lookup($prefix, $sources);
            if ($val !== null) {
                return [$val, true];
            }
        } else {
            // Intermediate prefix: system variables only
            if (!isset($vars[$prefix])) {
                continue;
            }
            if (!is_array($vars[$prefix])) {
                continue;
            }
            $remaining = array_slice($parts, $len);
            $traversed = get_array_traverse($vars[$prefix], $remaining);
            if ($traversed !== null) {
                return [$traversed, true];
            }
        }
    }

    // Fallback: data_lookup for resource.uuid.field (3-segment paths)
    load_library('util');
    $rs = dot2rs($key);
    if ($rs !== false) {
        load_library('data');
        static $sentinel = null;
        if ($sentinel === null) {
            $sentinel = new stdClass();
        }
        $v = data_lookup($rs[0], $rs[1], $rs[2], $sentinel);
        if ($v !== $sentinel) {
            return [$v, true];
        }
    }

    return [null, false];
}

/**
 * Traverses a nested array using an array of key segments.
 * Returns null if any key is missing.
 */
function get_array_traverse(array $array, array $keys)
{
    $a = $array;
    foreach ($keys as $k) {
        $k = preg_replace('/[^a-zA-Z0-9_-]/', '_', $k);
        if (!is_array($a) || !array_key_exists($k, $a)) {
            return null;
        }
        $a = $a[$k];
    }
    return $a;
}

/**
 * Resolves an i18n array to a string if it contains a configured language key.
 * Returns the array unchanged if it is not an i18n structure.
 */
function get_i18n_resolve(array $val, $lang = 'auto')
{
    load_library('data');
    $langs = data_lookup('.config', 'site', 'languages', ['en']);
    foreach ($langs as $l) {
        if (array_key_exists($l, $val)) {
            load_library('util');
            return resolve_i18n($val, $lang);
        }
    }
    return $val;
}

function get_variable($key, $default = null, $from = null)
{
    return get_sc($key, $default, $from);
}
