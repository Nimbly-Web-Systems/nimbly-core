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
    set_variable('page-canonical-url', seo_canonical_url($override ?: null));
    return '';
}

function seo_site_root(): string
{
    load_library('data');

    $configured = trim((string)(getenv('SITE_URL') ?: ''));
    if ($configured === '') {
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
