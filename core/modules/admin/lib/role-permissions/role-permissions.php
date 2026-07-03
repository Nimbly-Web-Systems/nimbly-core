<?php

load_library('data');
load_library('permissions');
load_library('set');

function role_permissions_sc($params)
{
    $role_id = get_param_value($params, 'role', current($params)) ?? get_variable('role-id');

    if ($role_id === 'new') {
        return role_permissions_render([], []);
    }

    if (empty($role_id) || !data_exists('roles', $role_id)) {
        return;
    }

    $role = data_read('roles', $role_id);
    $stored_features = permission_token_list($role['features'] ?? '');
    $effective_features = permission_expand_features($stored_features);
    $known_features = role_permissions_known_features();
    $custom_features = array_values(array_diff($stored_features, $known_features, ['manage-content']));

    return role_permissions_render($effective_features, $custom_features);
}

function role_permissions_render(array $effective_features, array $custom_features): string
{
    set_variable('_rp.custom_features', htmlspecialchars(implode("\n", $custom_features), ENT_QUOTES, 'UTF-8'));
    set_variable('_rp.section_special', role_permissions_all_toggle($effective_features));
    set_variable('_rp.section_resources', role_permissions_matrix(
        'Resources',
        'What this role can see and change, resource by resource.',
        role_permissions_resource_rows(),
        array_keys(role_permissions_hidden_rows()),
        $effective_features
    ));
    set_variable('_rp.section_system', role_permissions_checks(
        'System capabilities',
        'Access to admin tools and site maintenance, beyond editing records.',
        role_permissions_system_features(),
        $effective_features
    ));

    return run_buffered(dirname(__FILE__) . '/fragment.tpl');
}

function role_permissions_resource_rows(): array
{
    $rows = [];
    foreach (data_resources_list() as $resource => $_info) {
        $rows[$resource] = role_permissions_label($resource);
    }
    ksort($rows);
    return array_merge($rows, role_permissions_hidden_rows());
}

function role_permissions_hidden_rows(): array
{
    return [
        '.content' => 'Content',
        '.config' => 'Config',
        '.files' => 'Files',
    ];
}

function role_permissions_system_features(): array
{
    return [
        'view-admin-dashboard' => ['label' => 'View admin dashboard', 'description' => 'See the admin dashboard overview.'],
        'view-nimblybar' => ['label' => 'View Nimbly bar', 'description' => 'Show the Nimbly toolbar on the live site.'],
        'edit-inline-content' => ['label' => 'Inline editing', 'description' => 'Edit page content directly on the live site.'],
        'view-system-log' => ['label' => 'View system log', 'description' => 'See recorded system events and errors.'],
        'clear-system-log' => ['label' => 'Clear system log', 'description' => 'Clear the recorded system log.'],
        'view-debug' => ['label' => 'View debug info', 'description' => 'See technical debug information.'],
        'clear-cache' => ['label' => 'Clear cache', 'description' => 'Clear cached data to force a refresh.'],
        'pull-core-updates' => ['label' => 'Pull core updates', 'description' => 'Update Nimbly itself to the latest version.'],
        'pull-ext-updates' => ['label' => 'Pull site updates', 'description' => 'Update this site\'s code to the latest version.'],
        'manage-system' => ['label' => 'Manage system', 'description' => 'Full control over system settings and maintenance.'],
    ];
}

function role_permissions_special_features(): array
{
    return [
        '(all)' => 'All permissions',
    ];
}

function role_permissions_known_features(): array
{
    $features = [];
    foreach (role_permissions_resource_rows() as $resource => $_label) {
        $features = array_merge($features, role_permissions_operation_features($resource));
    }
    $features = array_merge($features, array_keys(role_permissions_special_features()), array_keys(role_permissions_system_features()), ['(any)', '(none)']);
    return permission_unique($features);
}

function role_permissions_all_toggle(array $effective_features): string
{
    set_variable_dot('_all', [
        'id' => role_permissions_checkbox_id('(all)'),
        'checkbox' => role_permissions_checkbox('(all)', in_array('(all)', $effective_features, true)),
    ]);
    $html = run_buffered(dirname(__FILE__) . '/all-toggle.tpl');
    clear_variable_dot('_all');
    return $html;
}

function role_permissions_operation_features(string $resource): array
{
    $features = [];
    foreach (array_merge(permission_operations(), ['manage']) as $operation) {
        $features[] = $operation . '-' . $resource;
    }
    return $features;
}

function role_permissions_matrix(string $title, string $description, array $rows, array $core_resources, array $effective_features): string
{
    $operations = permission_operations();

    $operation_headers = '';
    foreach ($operations as $operation) {
        $operation_headers .= '<th scope="col" class="px-3 py-2 text-center font-semibold">' . htmlspecialchars($operation) . '</th>';
    }

    $rows_html = '';
    foreach ($rows as $resource => $label) {
        $manage_feature = 'manage-' . $resource;
        $operation_cells = '';
        foreach ($operations as $operation) {
            $feature = $operation . '-' . $resource;
            $checkbox = role_permissions_checkbox($feature, in_array($feature, $effective_features, true), 'operation');
            $sr_label = '<label for="' . role_permissions_checkbox_id($feature) . '" class="sr-only">' . htmlspecialchars($operation . ' ' . $label, ENT_QUOTES, 'UTF-8') . '</label>';
            $operation_cells .= '<td class="px-3 py-2 text-center">' . $sr_label . $checkbox . '</td>';
        }

        $manage_checkbox = role_permissions_checkbox($manage_feature, in_array($manage_feature, $effective_features, true), 'manage');
        $manage_sr_label = '<label for="' . role_permissions_checkbox_id($manage_feature) . '" class="sr-only">' . htmlspecialchars('Manage ' . $label, ENT_QUOTES, 'UTF-8') . '</label>';

        set_variable_dot('_row', [
            'resource' => $resource,
            'label' => $label,
            'core_badge' => in_array($resource, $core_resources, true) ? '<span class="ml-1.5 rounded bg-neutral-200 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-neutral-600">' . htmlspecialchars('core') . '</span>' : '',
            'manage_checkbox' => $manage_sr_label . $manage_checkbox,
            'operation_cells' => $operation_cells,
        ]);
        $rows_html .= run_buffered(dirname(__FILE__) . '/matrix-row.tpl');
        clear_variable_dot('_row');
    }

    set_variable_dot('_matrix', [
        'title' => $title,
        'description' => $description,
        'operation_headers' => $operation_headers,
        'rows' => $rows_html,
    ]);
    $html = run_buffered(dirname(__FILE__) . '/matrix.tpl');
    clear_variable_dot('_matrix');
    return $html;
}

function role_permissions_checks(string $title, string $description, array $features, array $effective_features): string
{
    $items_html = '';
    foreach ($features as $feature => $info) {
        set_variable_dot('_item', [
            'id' => role_permissions_checkbox_id($feature),
            'label' => $info['label'],
            'description' => $info['description'],
            'checkbox' => role_permissions_checkbox($feature, in_array($feature, $effective_features, true)),
        ]);
        $items_html .= run_buffered(dirname(__FILE__) . '/checks-item.tpl');
        clear_variable_dot('_item');
    }

    set_variable_dot('_checks', [
        'title' => $title,
        'description' => $description,
        'items' => $items_html,
    ]);
    $html = run_buffered(dirname(__FILE__) . '/checks.tpl');
    clear_variable_dot('_checks');
    return $html;
}

function role_permissions_checkbox_id(string $feature): string
{
    return 'perm-' . preg_replace('/[^a-z0-9]+/i', '-', $feature);
}

function role_permissions_checkbox(string $feature, bool $checked, string $role = ''): string
{
    $id = role_permissions_checkbox_id($feature);
    $data_role = $role === '' ? '' : ' data-permission-' . $role;
    return '<input type="checkbox" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" name="features[]" value="' . htmlspecialchars($feature, ENT_QUOTES, 'UTF-8') . '" class="checkbox checkbox-primary checkbox-xs"' . $data_role
        . ($checked ? ' checked' : '') . '>';
}

function role_permissions_label(string $resource): string
{
    return ucwords(str_replace(['-', '_', '.'], [' ', ' ', ''], $resource));
}
