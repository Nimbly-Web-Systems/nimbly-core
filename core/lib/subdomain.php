<?php

/*
 * A subdomain is served from its own route folder: the routes of
 * docs.example.com live in ext/uri/(subdomain-docs)/. The folder is looked up
 * first; what it does not have (the admin, the API, media) comes from the
 * site's normal routes. The request uri stays what the visitor asked for, so
 * links and canonical addresses need no translation.
 */

const SUBDOMAIN_PREFIX = '(subdomain-';

/**
 * The first label of a host name such as docs.example.com, or '' when the
 * host has no subdomain to speak of (a bare name, an IP address).
 */
function subdomain_label(string $host): string
{
    $host = strtolower(trim($host));
    if ($host === '' || $host[0] === '[') {
        return '';
    }
    $host = explode(':', $host, 2)[0];
    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        return '';
    }
    $labels = explode('.', $host);
    if (count($labels) < 2 || !preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $labels[0])) {
        return '';
    }
    return $labels[0];
}

/**
 * The route folder of the subdomain this request came in on, e.g.
 * (subdomain-docs), or '' when the site has no folder for it.
 */
function subdomain_folder(): string
{
    static $folder = null;
    if ($folder !== null) {
        return $folder;
    }
    $folder = '';
    $label = subdomain_label((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($label !== '') {
        $name = SUBDOMAIN_PREFIX . $label . ')';
        if (find_exists($GLOBALS['SYSTEM']['file_base'] . 'ext/', 'uri/' . $name)) {
            $folder = $name;
        }
    }
    return $folder;
}

/** True for a uri or route that points into a subdomain folder. */
function subdomain_is_folder_path(string $path): bool
{
    return strncmp($path, SUBDOMAIN_PREFIX, strlen(SUBDOMAIN_PREFIX)) === 0;
}

/** The uri inside the given subdomain folder: docs + guide/start → (subdomain-docs)/guide/start. */
function subdomain_uri(string $folder, string $uri): string
{
    $uri = trim($uri, '/');
    return $uri === '' ? $folder : $folder . '/' . $uri;
}

/**
 * A stored route in a subdomain folder only answers on that subdomain; every
 * other route answers everywhere.
 */
function subdomain_route_allowed(string $route, string $folder): bool
{
    if (!subdomain_is_folder_path($route)) {
        return true;
    }
    return $folder !== '' && explode('/', $route, 2)[0] === $folder;
}

/** Path parts of a route without the subdomain folder in front. */
function subdomain_route_parts(array $parts): array
{
    if (isset($parts[0]) && subdomain_is_folder_path((string)$parts[0])) {
        array_shift($parts);
    }
    return $parts;
}
