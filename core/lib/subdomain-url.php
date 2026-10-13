<?php

load_library('subdomain');

/**
 * @doc `[#subdomain-url docs#]` returns the address of the site's `docs` subdomain
 * @doc `[#subdomain-url#]` returns the address of the main site, from any subdomain
 * @doc Example: `<a href="[#subdomain-url docs#]/guide">`
 */
function subdomain_url_sc($params = null)
{
    $name = is_array($params) ? (string)current($params) : (string)$params;
    if (!preg_match('/^[a-z0-9-]*$/', $name)) {
        return '';
    }
    $host = subdomain_sibling_host((string)($_SERVER['HTTP_HOST'] ?? ''), subdomain_folder() !== '', $name);
    $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    return ($https ? 'https' : 'http') . '://' . $host . rtrim($GLOBALS['SYSTEM']['uri_base'], ' /\\');
}
