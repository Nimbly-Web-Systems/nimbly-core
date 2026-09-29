<?php

/**
 * The page actions pill, where a page has page context: not in the admin
 * (/nb-admin), and not on pages that set page-actions=off.
 */
function page_actions_cond_sc($params = null) {
    $path = trim((string)parse_url((string)($GLOBALS['SYSTEM']['request_uri'] ?? ''), PHP_URL_PATH), '/');
    if (explode('/', $path)[0] === 'nb-admin' || get_variable('page-actions') === 'off') {
        return;
    }
    run_single_sc('page-actions-for-editors');
}
