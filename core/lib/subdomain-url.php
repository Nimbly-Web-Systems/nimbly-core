<?php

load_library('subdomain');

/**
 * @doc `[#subdomain-url docs#]` returns the address of the site's `docs` subdomain
 * @doc `[#subdomain-url#]` returns the address of the main site, from any subdomain
 * @doc Example: `<a href="[#subdomain-url docs#]/guide">`
 * @doc With `SUBDOMAIN_HOST=example.com` in .env, `[#subdomain-url docs#]` is `https://docs.example.com` on any host
 */
function subdomain_url_sc($params = null)
{
    $name = is_array($params) ? (string)current($params) : (string)$params;
    if (!preg_match('/^[a-z0-9-]*$/', $name)) {
        return '';
    }
    // A site served from a subfolder (a shared staging host) has no host its
    // subdomains hang off; SUBDOMAIN_HOST names the one they live under
    load_library('env');
    $subdomain_host = strtolower(trim((string)env('SUBDOMAIN_HOST', '')));
    if ($name !== '' && $subdomain_host !== '' && preg_match('/^[a-z0-9.-]+$/', $subdomain_host)) {
        return 'https://' . $name . '.' . $subdomain_host;
    }
    $host = subdomain_sibling_host((string)($_SERVER['HTTP_HOST'] ?? ''), subdomain_folder() !== '', $name);
    $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    return ($https ? 'https' : 'http') . '://' . $host . rtrim($GLOBALS['SYSTEM']['uri_base'], ' /\\');
}
