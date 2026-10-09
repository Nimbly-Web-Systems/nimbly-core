<?php

load_libraries(['data', 'history', 'get', 'system-messages', 'redirect']);

$resource = (string)get_variable('resource-id', '');
$uuid = (string)get_variable('uuid', '');
$exists = data_exists($resource, $uuid);

history_forget($resource, $uuid);
system_message($exists ? 'The history of this record has been cleared.' : 'The record has been deleted permanently.');
redirect('nb-admin/' . $resource . ($exists ? '/' . $uuid . '/history' : '/history'));
