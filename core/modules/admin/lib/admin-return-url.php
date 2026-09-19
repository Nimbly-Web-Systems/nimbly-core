<?php

/**
 * Optional `?return=/some/path` support for the generic admin add/edit
 * routes. Lets a project's own page link into `/nb-admin/(resource)/(add|id)`
 * for editing and get the operator back to that calling page afterwards —
 * the add/edit route templates fall back to the classic
 * `[#base-url#]/nb-admin/(resource)` overview when no `return` is given, so
 * this is fully backward compatible.
 *
 * Only a same-site absolute path is accepted (must start with a single `/`,
 * never `//` or `/\`) to avoid turning this into an open redirect.
 *
 * @return string Safe return path, or '' when absent/invalid.
 */
function admin_return_url(): string
{
    $return_url = (string)($_GET['return'] ?? '');
    if ($return_url === '' || $return_url[0] !== '/') {
        return '';
    }
    if (str_starts_with($return_url, '//') || str_starts_with($return_url, '/\\')) {
        return '';
    }
    return $return_url;
}
