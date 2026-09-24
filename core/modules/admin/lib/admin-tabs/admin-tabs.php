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
    load_library('managed-pages');
    load_library('managed-navigation');

    // Admins keep both tabs while a feature is off, so they can switch it back on.
    $available = managed_pages_feature_enabled() || access_by_feature('manage-system');
    $show_pages = $available && access_by_feature('view-pages');
    $show_navigation = managed_navigation_editable(managed_navigation_feature_enabled(), access_by_feature('manage-system'))
        && access_by_feature('edit-.navigation') && managed_navigation_slots() !== [];
    $show_settings = access_by_feature('edit-.config');

    set_variable('_at.show_pages', $show_pages ? 'true' : 'false');
    set_variable('_at.show_navigation', $show_navigation ? 'true' : 'false');
    set_variable('_at.show_settings', $show_settings ? 'true' : 'false');

    return run_buffered(dirname(__FILE__) . '/tabs.tpl');
}
