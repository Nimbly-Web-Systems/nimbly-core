<?php

/**
 * Link to the history of a resource: an icon on its overview, or with a
 * resource name (`[#record-history-link pages#]`) a text link for the dashboard.
 * Nothing for a resource without history or a user who may not edit it.
 */
function record_history_link_sc($params)
{
    load_libraries(['access', 'data', 'history', 'get', 'set']);
    $named = trim((string)(is_array($params) ? reset($params) : $params));
    $resource = $named !== '' ? $named : (string)get_variable('resource-id', '');
    if ($resource === '' || !data_exists($resource) || !history_enabled($resource) || !access_by_feature('edit-' . $resource)) {
        return '';
    }
    if ($named === '') {
        return run_buffered(dirname(__FILE__) . '/record-history/overview-link.tpl');
    }
    set_variable('_rh.resource', htmlspecialchars(record_history_slug($resource), ENT_QUOTES, 'UTF-8'));
    return run_buffered(dirname(__FILE__) . '/record-history/dashboard-link.tpl');
}

/** The content an editor changes outside the resource screens, each with its history: name => link. */
function record_history_content_links()
{
    load_libraries(['access', 'data', 'history']);
    $links = [];
    foreach (['.content' => 'Content', '.config' => 'Page settings', '.navigation' => 'Navigation'] as $resource => $name) {
        if (data_exists($resource) && history_enabled($resource) && access_by_feature('edit-' . $resource)) {
            $links[$name] = 'nb-admin/' . record_history_slug($resource) . '/history';
        }
    }
    return $links;
}

/** A hidden resource is named without its dot in an address: the server refuses dot paths. */
function record_history_slug($resource)
{
    return ltrim((string)$resource, '.');
}

/**
 * Opens a history route for the resource named in the address and sets what
 * its pages link to. Returns the resource, or '' when it has no history.
 */
function record_history_route($slug)
{
    load_libraries(['data', 'history', 'set']);
    $slug = (string)$slug;
    if ($slug === '' || $slug[0] === '.') {
        return '';
    }
    $resource = data_exists($slug) ? $slug : '.' . $slug;
    if (!data_exists($resource) || !history_enabled($resource)) {
        return '';
    }
    $hidden = $resource[0] === '.';
    set_variable('resource-id', $resource);
    set_variable('history-slug', $slug);
    // A hidden resource has no overview and no record screen of its own
    set_variable('history-home', $hidden ? 'nb-admin' : 'nb-admin/' . $slug);
    set_variable('history-hidden', $hidden ? '1' : '');
    return $resource;
}

/** Where a restore ends: the record's screen, or for a hidden resource its history. */
function record_history_record_url($resource, $uuid)
{
    $base = 'nb-admin/' . record_history_slug($resource) . '/' . $uuid;
    return (string)$resource !== '' && $resource[0] === '.' ? $base . '/history' : $base;
}
