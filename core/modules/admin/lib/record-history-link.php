<?php

/**
 * On a resource overview: link to its deleted records. Nothing for a resource
 * without history or a user who may not edit it.
 */
function record_history_link_sc($params)
{
    load_libraries(['access', 'data', 'history', 'get']);
    $resource = (string)get_variable('resource-id', '');
    if ($resource === '' || !history_enabled($resource) || !access_by_feature('edit-' . $resource)) {
        return '';
    }
    return run_buffered(dirname(__FILE__) . '/record-history/overview-link.tpl');
}
