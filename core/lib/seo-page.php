<?php

/** Prepare language and canonical metadata for the shared HTML shell. */
function seo_page_sc($params = null)
{
    load_library('get');

    $language = get_variable('language') ?: 'en';
    if (!headers_sent()) {
        header('Content-Language: ' . $language);
    }

    $override = get_variable('page-canonical-url');
    if (!$override && seo_page_from_main_site()) {
        // a main-site page reached on a subdomain belongs to the main site
        $override = seo_main_site_root() . '/' . ltrim((string)($GLOBALS['SYSTEM']['request_uri'] ?? ''), '/');
    }
    set_variable('page-canonical-url', seo_canonical_url($override ?: null));
    return '';
}

/** True on a subdomain when the page comes from the site's normal routes, not from the subdomain folder. */
function seo_page_from_main_site(): bool
{
    load_library('subdomain');
    return subdomain_folder() !== '' && !subdomain_is_folder_path((string)($GLOBALS['SYSTEM']['uri'] ?? ''));
}

/** The main site's address: SITE_URL, or the host without the subdomain. */
function seo_main_site_root(): string
{
    load_library('env');
    load_library('subdomain');
    $configured = trim((string)env('SITE_URL', ''));
    if ($configured !== '') {
        return rtrim(preg_match('#^https?://#i', $configured) ? $configured : 'https://' . ltrim($configured, '/'), '/');
    }
    $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    $host = subdomain_sibling_host((string)($_SERVER['HTTP_HOST'] ?? ''), true, '');
    $base = '/' . trim((string)($GLOBALS['SYSTEM']['uri_base'] ?? '/'), '/');
    return rtrim(($https ? 'https' : 'http') . '://' . $host . ($base === '/' ? '' : $base), '/');
}

function seo_site_root(): string
{
    load_library('data');
    load_library('env');
    load_library('subdomain');

    // a subdomain with its own route folder is its own address
    $configured = subdomain_folder() !== '' ? null : trim((string)env('SITE_URL', ''));
    if ($configured === null) {
        $configured = '';
    } else if ($configured === '') {
        $configured = trim((string)data_lookup('.config', 'site', 'site_url', ''));
    }
    if ($configured !== '') {
        if (!preg_match('#^https?://#i', $configured)) {
            $configured = 'https://' . ltrim($configured, '/');
        }
        return rtrim($configured, '/');
    }

    $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $base = '/' . trim((string)($GLOBALS['SYSTEM']['uri_base'] ?? '/'), '/');
    return rtrim($scheme . '://' . $host . ($base === '/' ? '' : $base), '/');
}

function seo_canonical_url(?string $url = null): string
{
    $root = seo_site_root();
    $path = $url ?? (string)($GLOBALS['SYSTEM']['request_uri'] ?? '');
    $path = preg_split('/[?#]/', trim($path), 2)[0];

    if (preg_match('#^https?://#i', $path)) {
        $parts = parse_url($path);
        $path = $parts['path'] ?? '';
        $candidate_root = isset($parts['scheme'], $parts['host'])
            ? strtolower($parts['scheme']) . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '')
            : $root;
        $root = rtrim($candidate_root, '/');
    }

    $path = trim(preg_replace('#/+#', '/', $path), '/');
    return $root . ($path === '' ? '/' : '/' . $path . '/');
}
