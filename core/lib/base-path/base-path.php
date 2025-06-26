<?php

/**
 * Shortcode: [#base-path#]
 * @doc Returns the full filesystem path to the Nimbly installation root.
 * @doc Useful for server-side logic involving file storage, custom includes, or debugging paths.
 * @doc Example output: `/var/www/html/nimbly`
 *
 * @return string Absolute filesystem path ending in slash (e.g., `/var/www/html/nimbly/`)
 */
function base_path_sc() {
    return $GLOBALS['SYSTEM']['file_base'];
}
