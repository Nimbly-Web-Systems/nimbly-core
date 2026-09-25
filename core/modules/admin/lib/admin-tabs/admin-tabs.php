<?php

load_library('access');
load_library('set');

/**
 * Top-level admin tab bar. Which tabs show is decided here from the user's
 * features; the markup lives in tabs.tpl and the admin-tab-* templates.
 * Pages set `admin_tab` (overview|pages|navigation|settings) to mark the active tab.
 */
function admin_tabs_sc($params)
{
    load_library('data');
    load_library('managed-pages');
    load_library('managed-navigation');

    // A feature's tab shows only while it is switched on in Settings, for everyone.
    // Applications without a pages resource have no Pages overview to link to.
    $show_pages = managed_pages_feature_enabled() && data_exists('pages') && access_by_feature('view-pages');
    $show_navigation = managed_navigation_feature_enabled()
        && access_by_feature('edit-.navigation') && managed_navigation_slots() !== [];
    $show_settings = access_by_feature('edit-.config');

    set_variable('_at.show_pages', $show_pages ? 'true' : 'false');
    set_variable('_at.show_navigation', $show_navigation ? 'true' : 'false');
    set_variable('_at.show_settings', $show_settings ? 'true' : 'false');

    return run_buffered(dirname(__FILE__) . '/tabs.tpl');
}
