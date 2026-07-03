<?php

load_library('data');
load_library('set');

function role_identity_sc($params)
{
    $role_id = get_param_value($params, 'role', current($params)) ?? get_variable('role-id');
    if (empty($role_id) || !data_exists('roles', $role_id)) {
        return;
    }

    $role = data_read('roles', $role_id);

    set_variable('_ri.role_id', $role_id);
    set_variable('_ri.name_json', htmlspecialchars(json_encode($role['name'] ?? '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'));
    set_variable('_ri.description_json', htmlspecialchars(json_encode($role['description'] ?? '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'));

    return run_buffered(dirname(__FILE__) . '/panel.tpl');
}
