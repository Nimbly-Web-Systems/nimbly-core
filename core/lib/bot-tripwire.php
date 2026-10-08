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

/** Answers a request for the tripwire path; false for any other path. */
function bot_tripwire_answer(string $uri): bool
{
    $path = bot_tripwire_path();
    if ($path === '' || trim($uri, '/') !== $path) {
        return false;
    }
    if (!headers_sent()) {
        http_response_code(418);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo "Hi, bot. robots.txt asks you not to come here.\n";
    return true;
}
