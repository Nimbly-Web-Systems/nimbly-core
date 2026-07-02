<?php

load_library('data');
load_library('permissions');
load_library('form-key');

function role_permissions_sc($params)
{
    $role_id = get_param_value($params, 'role', current($params)) ?? get_variable('role-id');
    if (empty($role_id) || !data_exists('roles', $role_id)) {
        return;
    }

    $role = data_read('roles', $role_id);
    $stored_features = permission_token_list($role['features'] ?? '');
    $effective_features = permission_expand_features($stored_features);
    $known_features = role_permissions_known_features();
    $custom_features = array_values(array_diff($stored_features, $known_features, ['manage-content']));

    echo '<form method="post" class="bg-neutral-50 rounded-2xl p-6 shadow-md">';
    echo form_key_sc(['role_permissions']);
    echo '<div class="flex flex-wrap items-start justify-between gap-4">';
    echo '<div>';
    echo '<h2 class="text-xl font-semibold text-neutral-800">' . htmlspecialchars($role['name'] ?? $role_id) . '</h2>';
    echo '<p class="text-sm text-neutral-600">Stored permissions are saved as concrete operation-resource tokens.</p>';
    echo '</div>';
    echo '<button type="submit" class="btn btn-primary">Save permissions</button>';
    echo '</div>';

    role_permissions_render_checks('Special permissions', role_permissions_special_features(), $effective_features);
    role_permissions_render_matrix('Resources', role_permissions_resource_rows(), $effective_features);
    role_permissions_render_matrix('Core hidden resources', role_permissions_hidden_rows(), $effective_features);
    role_permissions_render_checks('System capabilities', role_permissions_system_features(), $effective_features);

    echo '<div class="mt-8">';
    echo '<label class="block text-sm font-semibold text-neutral-700 mb-2" for="custom_features">Custom permissions</label>';
    echo '<textarea id="custom_features" name="custom_features" rows="4" class="textarea textarea-bordered w-full bg-neutral-50">';
    echo htmlspecialchars(implode("\n", $custom_features));
    echo '</textarea>';
    echo '<p class="mt-2 text-xs text-neutral-500">One token per line or comma-separated. Unknown tokens are preserved.</p>';
    echo '</div>';
    echo '</form>';
}

function role_permissions_resource_rows(): array
{
    $rows = [];
    foreach (data_resources_list() as $resource => $_info) {
        $rows[$resource] = role_permissions_label($resource);
    }
    ksort($rows);
    return $rows;
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
        'view-admin-dashboard' => 'View admin dashboard',
        'view-nimblybar' => 'View Nimbly bar',
        'edit-inline-content' => 'Inline editing',
        'view-system-log' => 'View system log',
        'clear-system-log' => 'Clear system log',
        'view-debug' => 'View debug info',
        'clear-cache' => 'Clear cache',
        'pull-core-updates' => 'Pull core updates',
        'pull-ext-updates' => 'Pull site updates',
        'manage-system' => 'Manage system',
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
    foreach (array_merge(role_permissions_resource_rows(), role_permissions_hidden_rows()) as $resource => $_label) {
        $features = array_merge($features, role_permissions_operation_features($resource));
    }
    $features = array_merge($features, array_keys(role_permissions_special_features()), array_keys(role_permissions_system_features()), ['(any)', '(none)']);
    return permission_unique($features);
}

function role_permissions_operation_features(string $resource): array
{
    $features = [];
    foreach (array_merge(permission_operations(), ['manage']) as $operation) {
        $features[] = $operation . '-' . $resource;
    }
    return $features;
}

function role_permissions_render_matrix(string $title, array $rows, array $effective_features): void
{
    echo '<section class="mt-8">';
    echo '<h3 class="text-lg font-semibold text-neutral-800 mb-3">' . htmlspecialchars($title) . '</h3>';
    echo '<div class="overflow-x-auto rounded-lg border border-neutral-200">';
    echo '<table class="min-w-full bg-neutral-50 text-sm">';
    echo '<thead><tr class="bg-neutral-100 text-left">';
    echo '<th class="p-3 font-semibold">Resource</th>';
    foreach (array_merge(permission_operations(), ['manage']) as $operation) {
        echo '<th class="p-3 font-semibold capitalize">' . htmlspecialchars($operation) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $resource => $label) {
        echo '<tr class="border-t border-neutral-200">';
        echo '<td class="p-3 font-medium text-neutral-800">' . htmlspecialchars($label) . '<div class="text-xs text-neutral-500">' . htmlspecialchars($resource) . '</div></td>';
        foreach (array_merge(permission_operations(), ['manage']) as $operation) {
            $feature = $operation . '-' . $resource;
            echo '<td class="p-3">' . role_permissions_checkbox($feature, in_array($feature, $effective_features, true)) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div></section>';
}

function role_permissions_render_checks(string $title, array $features, array $effective_features): void
{
    echo '<section class="mt-8">';
    echo '<h3 class="text-lg font-semibold text-neutral-800 mb-3">' . htmlspecialchars($title) . '</h3>';
    echo '<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">';
    foreach ($features as $feature => $label) {
        echo '<label class="flex items-center gap-3 rounded border border-neutral-200 bg-neutral-50 p-3">';
        echo role_permissions_checkbox($feature, in_array($feature, $effective_features, true));
        echo '<span><span class="block font-medium text-neutral-800">' . htmlspecialchars($label) . '</span>';
        echo '<span class="block text-xs text-neutral-500">' . htmlspecialchars($feature) . '</span></span>';
        echo '</label>';
    }
    echo '</div></section>';
}

function role_permissions_checkbox(string $feature, bool $checked): string
{
    return '<input type="checkbox" name="features[]" value="' . htmlspecialchars($feature) . '" class="checkbox checkbox-primary"'
        . ($checked ? ' checked' : '') . '>';
}

function role_permissions_label(string $resource): string
{
    return ucwords(str_replace(['-', '_', '.'], [' ', ' ', ''], $resource));
}
