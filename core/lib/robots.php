<?php

function robots_sc($params = null): string
{
    load_library('seo-page');
    load_library('bot-tripwire');
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=300');
    }

    $tripwire = bot_tripwire_path();

    return "User-agent: *\n"
        . "Disallow: /nb-admin/\n"
        . "Disallow: /api/\n"
        . "Disallow: /login/\n"
        . "Disallow: /logout/\n"
        . "Disallow: /forgot-password/\n"
        . ($tripwire === '' ? '' : 'Disallow: /' . $tripwire . "/\n")
        . 'Sitemap: ' . seo_site_root() . "/sitemap.xml\n";
}
