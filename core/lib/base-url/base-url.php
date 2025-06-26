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
