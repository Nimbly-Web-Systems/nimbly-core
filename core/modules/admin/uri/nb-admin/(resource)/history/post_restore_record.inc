<?php

load_libraries(['data', 'history', 'get', 'system-messages', 'redirect']);

// The button names the record and the change: <uuid>/<change>
$resource = (string)get_variable('resource-id', '');
[$uuid, $change] = explode('/', (string)filter_input(INPUT_POST, 'change'), 2) + [1 => ''];

if ($change === '' || history_restore($resource, $uuid, $change) === false) {
    $GLOBALS['SYSTEM']['validation_errors']['_global'][] = '[#text This version could not be restored.#]';
    return;
}
system_message('The record has been restored.');
redirect('nb-admin/' . $resource . '/' . $uuid);
