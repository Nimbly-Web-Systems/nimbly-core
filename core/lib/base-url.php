<?php 

/**
 * Shortcode: [#base-url#]
 * @doc Returns the base URL path (alias) of the Nimbly installation.
 * @doc Useful for linking to static assets, scripts, or resolving paths under alias hosting.
 * @doc Example output: `/nimbly` or `/`
 *
 * @return string URL base path, trimmed of trailing slashes
 */
function base_url_sc() {
    return rtrim($GLOBALS['SYSTEM']['uri_base'], ' /\\');
}

/**
 * Rewrites internal media URLs (img/download) in stored HTML so they carry
 * the current installation's base path instead of whatever base was active
 * when the HTML was saved. Rich text must remain portable between root and
 * subdirectory installations (e.g. content authored on a staging alias and
 * later viewed on a root-mounted production site).
 *
 * @param string $html
 * @return string
 */
function normalize_media_base_url(string $html): string {
    // Remove any stored installation base from internal media URLs.
    $html = preg_replace(
        '/([" ,])\/[\w-]{2,}(\/(?:img\/[0-9a-z]{20,32}\/|download\/[0-9a-z]{20,32}(?=[" ,<])))/i',
        '$1$2',
        $html
    );

    $base_url = trim($GLOBALS['SYSTEM']['uri_base'] ?? '', ' \\/');

    if (strlen($base_url) > 0) {
        // Add the active installation base to root-relative media URLs.
        $html = preg_replace(
            '/([", ])\/(img\/[0-9a-z]{20,32}\/|download\/[0-9a-z]{20,32}(?=[" ,<]))/i',
            '$1/' . $base_url . '/$2',
            $html
        );
    }

    return $html;
}

/**
 * Puts the installation's base path in front of root-relative links in stored
 * HTML, so a link written as /contact also works where the site runs in a
 * subfolder. A link that already starts with the base path, a link to another
 * host (//host/…) and every other kind of address are left as they are.
 *
 * @param string $html
 * @return string
 */
function normalize_link_base_url(string $html): string {
    $base_url = trim($GLOBALS['SYSTEM']['uri_base'] ?? '', ' \\/');
    if ($base_url === '' || stripos($html, 'href=') === false) {
        return $html;
    }

    return preg_replace_callback(
        '/(<a\b[^>]*?\shref=)(["\'])(\/(?!\/)[^"\'>]*)\2/i',
        function ($match) use ($base_url) {
            $rest = substr($match[3], strlen($base_url) + 1);
            $has_base = strncmp($match[3], '/' . $base_url, strlen($base_url) + 1) === 0
                && ($rest === '' || strpbrk($rest[0], '/?#') !== false);
            return $match[1] . $match[2] . ($has_base ? '' : '/' . $base_url) . $match[3] . $match[2];
        },
        $html
    ) ?? $html;
}
