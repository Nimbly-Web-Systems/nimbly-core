<?php

load_libraries(['data', 'history', 'get', 'system-messages', 'redirect', 'record-history-link']);

$resource = (string)get_variable('resource-id', '');
$uuid = (string)get_variable('uuid', '');

if (history_restore($resource, $uuid, (string)filter_input(INPUT_POST, 'change')) === false) {
    $GLOBALS['SYSTEM']['validation_errors']['_global'][] = '[#text This version could not be restored.#]';
    return;
}
system_message('The record has been restored.');
redirect(record_history_record_url($resource, $uuid));
