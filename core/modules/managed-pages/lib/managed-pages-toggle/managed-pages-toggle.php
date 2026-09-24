<?php

load_library('managed-pages');
load_library('set');

/** Admin switch for the custom pages feature, shown above the Pages overview. */
function managed_pages_toggle_sc($params)
{
    set_variable('_mp.enabled_json', managed_pages_feature_enabled() ? 'true' : 'false');
    return run_buffered(dirname(__FILE__) . '/toggle.tpl');
}
