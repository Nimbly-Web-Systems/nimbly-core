<?php

load_libraries(['data', 'history', 'get', 'system-messages', 'redirect', 'record-history-link']);

$resource = (string)get_variable('resource-id', '');
$uuid = (string)filter_input(INPUT_POST, 'record');

if (!empty($_POST['all'])) {
    system_message(history_forget_deleted($resource) . ' record(s) deleted permanently.');
} elseif (isset(history_deleted($resource)[$uuid]) && history_forget($resource, $uuid) > 0) {
    // Only a record that is really deleted: one that exists keeps its history here
    system_message('The record has been deleted permanently.');
}
redirect('nb-admin/' . record_history_slug($resource) . '/history');
