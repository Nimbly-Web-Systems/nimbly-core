<?php

load_library('data');
load_library('set');
load_library('access');

/**
 * The API access switch in a user's action panel, for who may edit users.
 * A user requests API tokens only while it is on.
 */
function record_api_access_panel(string $resource, string $uuid): string
{
    if ($resource !== 'users' || $uuid === '' || !access_by_feature('edit-users')) {
        return '';
    }
    $user = data_read('users', $uuid);
    if (!is_array($user)) {
        return '';
    }
    set_variable('_api.uuid', $uuid);
    set_variable('_api.on', !empty($user['api']['access']) ? 'true' : 'false');
    $panel = run_buffered(dirname(__FILE__) . '/panel.tpl');
    clear_variable_dot('_api');
    return trim((string)$panel);
}
