<?php

load_library('managed-navigation');
load_library('access');
load_library('set');

/** Admin switch for the navigation editor, shown above the editor to system managers only. */
function managed_navigation_toggle_sc($params)
{
    if (!access_by_feature('manage-system')) {
        return '';
    }
    set_variable('_mn.enabled_json', managed_navigation_feature_enabled() ? 'true' : 'false');
    return run_buffered(dirname(__FILE__) . '/toggle.tpl');
}
