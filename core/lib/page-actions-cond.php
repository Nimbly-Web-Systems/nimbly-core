<?php

/**
 * The page actions pill, where a page has page context: not in the admin
 * (/nb-admin), and not on pages that set page-actions=off.
 */
function page_actions_cond_sc($params = null) {
    $first = trim((string)($GLOBALS['SYSTEM']['uri_parts'][0] ?? ''), '/');
    if ($first === 'nb-admin' || get_variable('page-actions') === 'off') {
        return;
    }
    run_single_sc('page-actions-for-editors');
}
