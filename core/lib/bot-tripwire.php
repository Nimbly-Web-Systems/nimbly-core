<?php

/**
 * The bot tripwire: one address per site that robots.txt forbids and that
 * every page links to out of sight. A bot that follows the rules never asks
 * for it; one that does gets status 418, which a server can ban on without
 * knowing the address.
 */

/** This site's tripwire path, or '' when the site has no PEPPER. Never changes for a site. */
function bot_tripwire_path(): string
{
    $pepper = (string)($_SERVER['PEPPER'] ?? '');
    if ($pepper === '') {
        return '';
    }
    return 'nb-' . substr(hash('sha256', 'bot-tripwire|' . $pepper), 0, 12);
}

/** A path no Nimbly site ever serves and only scanners ask for: Windows Live Writer's manifest, anywhere. */
function bot_tripwire_forbidden(string $uri): bool
{
    return strtolower(basename(rtrim($uri, '/'))) === 'wlwmanifest.xml';
}

/** Answers a request for the tripwire path or a forbidden path; false for any other path. */
function bot_tripwire_answer(string $uri): bool
{
    $path = bot_tripwire_path();
    $tripwire = $path !== '' && trim($uri, '/') === $path;
    if (!$tripwire && !bot_tripwire_forbidden($uri)) {
        return false;
    }
    if (!headers_sent()) {
        http_response_code(418);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo $tripwire ? "Hi, bot. robots.txt asks you not to come here.\n" : "Hi, bot. Nothing here for you.\n";
    return true;
}
