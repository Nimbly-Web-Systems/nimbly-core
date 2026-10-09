<?php

/**
 * Opens the history page: /nb-admin/history?resource=<resource> lists the
 * deleted records, with &record=<uuid> the changes of that record. Only for a
 * resource with history and a user who may edit it.
 */
function record_history_access_sc($params)
{
    load_libraries(['access', 'data', 'history', 'set', 'redirect']);
    $resource = (string)($_GET['resource'] ?? '');
    $uuid = (string)($_GET['record'] ?? '');
    if (preg_match('/^\.?[A-Za-z0-9_-]+$/', $resource) !== 1 || !data_path_valid($resource, $uuid) || !data_exists($resource) || !history_enabled($resource)) {
        redirect('errors/404');
    }
    if (!access_by_feature('edit-' . $resource)) {
        access_denied();
    }
    if ($uuid !== '' && ($uuid[0] === '.' || history_list($resource, $uuid) === [])) {
        redirect('errors/404');
    }
    set_variable('resource-id', $resource);
    set_variable('history-record', $uuid);
}
