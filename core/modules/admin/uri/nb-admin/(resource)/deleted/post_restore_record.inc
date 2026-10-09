<?php

load_libraries(['data', 'history', 'get', 'system-messages', 'redirect']);

// A deleted record comes back as it was when it was deleted
$resource = (string)get_variable('resource-id', '');
$uuid = (string)filter_input(INPUT_POST, 'record');
$change = (string)(history_deleted($resource)[$uuid]['id'] ?? '');

if ($change === '' || history_restore($resource, $uuid, $change) === false) {
    $GLOBALS['SYSTEM']['validation_errors']['_global'][] = '[#text This record could not be restored.#]';
    return;
}
system_message('The record has been restored.');
redirect('nb-admin/' . $resource . '/' . $uuid);
