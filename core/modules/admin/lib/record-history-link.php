<?php

/**
 * Link to the history of the record on this page, or on a resource overview to
 * its deleted records. Nothing for a resource without history or a user who
 * may not edit it.
 */
function record_history_link_sc($params)
{
    load_libraries(['access', 'data', 'history', 'get', 'set']);
    $resource = (string)get_variable('resource-id', '');
    if ($resource === '' || !history_enabled($resource) || !access_by_feature('edit-' . $resource)) {
        return '';
    }
    $uuid = (string)get_variable('uuid', '');
    set_variable('_rh.link', 'nb-admin/history?resource=' . rawurlencode($resource) . ($uuid === '' ? '' : '&amp;record=' . rawurlencode($uuid)));
    return run_buffered(dirname(__FILE__) . '/record-history/' . ($uuid === '' ? 'overview-link.tpl' : 'history-link.tpl'));
}
