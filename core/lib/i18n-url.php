<?php

/**
 * Resolves the current route's equivalent URL for a configured language.
 *
 * @doc `[#i18n-url en#]` returns an installation-relative translated URL.
 */
function i18n_url_sc($params)
{
    $language = is_array($params) ? current($params) : $params;
    $result = i18n_url_resolve($language);
    return $result['availability'] === 'linked' ? $result['url'] : '';
}

function i18n_url_resolve($language)
{
    $language = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$language);
    if ($language === '') {
        return i18n_url_result('hidden');
    }

    load_library('get');
    $override = get_variable('i18n-url.' . $language, null);
    if ($override !== null) {
        return i18n_url_explicit_result($override, $language);
    }

    $mapping = i18n_url_route_mapping();
    if (array_key_exists($language, $mapping)) {
        return i18n_url_explicit_result($mapping[$language], $language);
    }

    return i18n_url_fallback_result($language);
}

function i18n_url_route_mapping()
{
    static $cache = [];
    $uri_path = $GLOBALS['SYSTEM']['uri_path'] ?? '';
    if ($uri_path === '') {
        return [];
    }

    $path = rtrim($uri_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'i18n-url.json';
    $absolute_path = realpath($path);
    if ($absolute_path === false) {
        return [];
    }
    if (array_key_exists($absolute_path, $cache)) {
        return $cache[$absolute_path];
    }

    $json = @file_get_contents($absolute_path);
    $mapping = json_decode((string)$json, true);
    if (!is_array($mapping) || json_last_error() !== JSON_ERROR_NONE) {
        load_library('log');
        log_system('Invalid i18n-url.json at ' . $absolute_path . ': ' . json_last_error_msg());
        $mapping = [];
    }
    $cache[$absolute_path] = $mapping;
    return $mapping;
}

function i18n_url_explicit_result($value, $language)
{
    if (!is_scalar($value)) {
        return i18n_url_result('hidden');
    }
    $value = trim((string)$value);
    if ($value === '(hide)') {
        return i18n_url_result('hidden');
    }
    if ($value === '(home)') {
        return i18n_url_result('linked', i18n_url_home($language));
    }

    $rendered = trim(run_template_buffered($value));
    $relative_url = ltrim($rendered, '/');
    if ($relative_url === '' || strpos($relative_url, NB_TAG_OPEN) !== false
        || i18n_url_has_empty_segment($relative_url)) {
        return i18n_url_result('hidden');
    }
    return i18n_url_result('linked', $relative_url);
}

function i18n_url_has_empty_segment($url)
{
    $path = parse_url($url, PHP_URL_PATH);
    return is_string($path) && strpos($path, '//') !== false;
}

function i18n_url_fallback_result($language)
{
    load_library('data');
    $fallback = data_lookup('.config', 'site', 'lang_switch_fallback', 'home');
    if ($fallback === 'hide') {
        return i18n_url_result('hidden');
    }
    if ($fallback === 'disabled') {
        return i18n_url_result('disabled');
    }
    return i18n_url_result('linked', i18n_url_home($language));
}

function i18n_url_home($language)
{
    return trim((string)$language, '/') . '/';
}

function i18n_url_result($availability, $url = '')
{
    return [
        'availability' => $availability,
        'url' => $url,
    ];
}
