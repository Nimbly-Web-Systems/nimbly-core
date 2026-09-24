<?php

load_library('access');
load_library('data');
load_library('get');
load_library('set');

/**
 * Shows the admin tab bar on a resource's own pages when its `.meta` declares
 * `admin_tab`, so the resource stays inside its tab. `panel=true` also renders
 * the optional `admin_panel` ({"template": ..., "feature": ...}) under the tabs.
 */
function admin_resource_tabs_sc($params)
{
    $resource = (string)get_variable('resource-id', '');
    $meta = $resource === '' ? [] : data_meta($resource);
    $tab = (string)($meta['admin_tab'] ?? '');
    if (!preg_match('/^[a-z0-9_-]+$/', $tab)) {
        return '';
    }
    set_variable('admin_tab', $tab);
    load_library('admin-tabs');
    $show_panel = (string)get_param_value($params, 'panel', '') === 'true';
    $html = '';
    if ($show_panel) {
        // The overview has a subtitle like the other tabs, so the bar sits in the same place.
        set_variable('_art.subtitle', (string)($meta['admin_subtitle'] ?? ''));
        $html .= run_buffered(dirname(__FILE__) . '/subtitle.tpl');
    }
    $html .= admin_tabs_sc([]);

    $panel = is_array($meta['admin_panel'] ?? null) ? $meta['admin_panel'] : [];
    $template = (string)($panel['template'] ?? '');
    if ($show_panel && preg_match('/^[a-z0-9_-]+$/', $template)
        && (empty($panel['feature']) || access_by_feature((string)$panel['feature']))) {
        $html .= '[#' . $template . '#]';
    }
    return $html;
}
