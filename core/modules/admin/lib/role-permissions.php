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

    echo '<form x-data="role_permissions(\'' . htmlspecialchars($role_id, ENT_QUOTES, 'UTF-8') . '\')" @submit.prevent="submit" class="mx-auto max-w-6xl bg-neutral-50 rounded-md border border-neutral-200 shadow-sm">';
    echo '<div class="sticky top-0 z-20 flex flex-wrap items-center justify-between gap-4 border-b border-neutral-200 bg-neutral-50/95 px-5 py-4 backdrop-blur">';
    echo '<div>';
    echo '<h2 class="text-xl font-semibold leading-tight text-neutral-800">' . htmlspecialchars($role['name'] ?? $role_id) . '</h2>';
    echo '<p class="mt-1 text-sm text-neutral-600">Choose what this role can see and change.</p>';
    echo '</div>';
    echo '<button type="submit" class="btn btn-primary btn-sm" x-bind:disabled="busy">Save permissions</button>';
    echo '</div>';

    echo '<div class="space-y-7 p-5">';
    role_permissions_render_checks('Special permissions', 'Broad access shortcuts. Use sparingly.', role_permissions_special_features(), $effective_features);
    role_permissions_render_matrix('Resources', 'Public data resources shown in the admin.', role_permissions_resource_rows(), $effective_features);
    role_permissions_render_matrix('Core resources', 'Hidden resources used by Nimbly internals.', role_permissions_hidden_rows(), $effective_features);
    role_permissions_render_checks('System capabilities', 'Admin screens and system operations outside resource CRUD.', role_permissions_system_features(), $effective_features);

    echo '<section>';
    echo '<div class="mb-3">';
    echo '<h3 class="text-base font-semibold text-neutral-800">Custom permissions</h3>';
    echo '<p class="mt-1 text-sm text-neutral-500">Unknown tokens are preserved for custom modules.</p>';
    echo '</div>';
    echo '<textarea id="custom_features" name="custom_features" rows="4" class="textarea textarea-bordered w-full bg-white font-mono text-sm">';
    echo htmlspecialchars(implode("\n", $custom_features));
    echo '</textarea>';
    echo '<p class="mt-2 text-xs text-neutral-500">One token per line or comma-separated.</p>';
    echo '</section>';
    echo '</div>';
    echo role_permissions_script();
    echo '</form>';
}

function role_permissions_api_sc(): void
{
    $role_id = trim((string)($_POST['role'] ?? ''));
    if ($role_id === '' || !data_exists('roles', $role_id)) {
        json_result(['message' => 'ROLE_NOT_FOUND'], 404);
    }

    $features = $_POST['features'] ?? [];
    if (!is_array($features)) {
        $features = permission_token_list($features);
    }

    $custom_features = permission_token_list($_POST['custom_features'] ?? '');
    $features = permission_expand_features(array_merge($features, $custom_features));
    $features = role_permissions_compact_features($features);

    if (!data_update('roles', $role_id, ['features' => implode(',', $features)])) {
        json_result(['message' => 'ROLE_UPDATE_FAILED'], 500);
    }

    json_result([
        'message' => 'ROLE_PERMISSIONS_SAVED',
        'features' => $features,
        'count' => count($features),
    ]);
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

function role_permissions_render_matrix(string $title, string $description, array $rows, array $effective_features): void
{
    $operations = permission_operations();
    echo '<section>';
    echo '<div class="mb-3">';
    echo '<h3 class="text-base font-semibold text-neutral-800">' . htmlspecialchars($title) . '</h3>';
    echo '<p class="mt-1 text-sm text-neutral-500">' . htmlspecialchars($description) . '</p>';
    echo '</div>';
    echo '<div class="overflow-x-auto rounded-md border border-neutral-200 bg-white">';
    echo '<table class="min-w-full text-sm">';
    echo '<thead><tr class="border-b border-neutral-200 bg-neutral-100/80 text-left text-xs uppercase tracking-wide text-neutral-500">';
    echo '<th class="min-w-52 px-3 py-2 font-semibold">Resource</th>';
    echo '<th class="px-3 py-2 text-center font-semibold">Manage</th>';
    foreach ($operations as $operation) {
        echo '<th class="px-3 py-2 text-center font-semibold">' . htmlspecialchars($operation) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $resource => $label) {
        $manage_feature = 'manage-' . $resource;
        echo '<tr class="border-b border-neutral-100 last:border-b-0" data-permission-row="' . htmlspecialchars($resource, ENT_QUOTES, 'UTF-8') . '">';
        echo '<td class="px-3 py-2.5 font-medium text-neutral-800">' . htmlspecialchars($label) . '<div class="text-xs font-normal text-neutral-500">' . htmlspecialchars($resource) . '</div></td>';
        echo '<td class="px-3 py-2 text-center">' . role_permissions_checkbox($manage_feature, in_array($manage_feature, $effective_features, true), 'manage') . '</td>';
        foreach ($operations as $operation) {
            $feature = $operation . '-' . $resource;
            echo '<td class="px-3 py-2 text-center">' . role_permissions_checkbox($feature, in_array($feature, $effective_features, true), 'operation') . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div></section>';
}

function role_permissions_render_checks(string $title, string $description, array $features, array $effective_features): void
{
    echo '<section>';
    echo '<div class="mb-3">';
    echo '<h3 class="text-base font-semibold text-neutral-800">' . htmlspecialchars($title) . '</h3>';
    echo '<p class="mt-1 text-sm text-neutral-500">' . htmlspecialchars($description) . '</p>';
    echo '</div>';
    echo '<div class="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-3">';
    foreach ($features as $feature => $label) {
        echo '<label class="flex items-center gap-3 rounded-md border border-neutral-200 bg-white px-3 py-2.5">';
        echo role_permissions_checkbox($feature, in_array($feature, $effective_features, true));
        echo '<span class="min-w-0"><span class="block font-medium text-neutral-800">' . htmlspecialchars($label) . '</span>';
        echo '<span class="block text-xs text-neutral-500">' . htmlspecialchars($feature) . '</span></span>';
        echo '</label>';
    }
    echo '</div></section>';
}

function role_permissions_checkbox(string $feature, bool $checked, string $role = ''): string
{
    $data_role = $role === '' ? '' : ' data-permission-' . $role;
    return '<input type="checkbox" name="features[]" value="' . htmlspecialchars($feature) . '" class="checkbox checkbox-primary checkbox-sm"' . $data_role
        . ($checked ? ' checked' : '') . '>';
}

function role_permissions_label(string $resource): string
{
    return ucwords(str_replace(['-', '_', '.'], [' ', ' ', ''], $resource));
}

function role_permissions_compact_features(array $features): array
{
    $feature_set = array_fill_keys(permission_unique($features), true);
    foreach (array_merge(role_permissions_resource_rows(), role_permissions_hidden_rows()) as $resource => $_label) {
        $manage_feature = 'manage-' . $resource;
        $operation_features = [];
        foreach (permission_operations() as $operation) {
            $operation_features[] = $operation . '-' . $resource;
        }

        $has_all_operations = true;
        foreach ($operation_features as $feature) {
            if (empty($feature_set[$feature])) {
                $has_all_operations = false;
                break;
            }
        }

        if (!empty($feature_set[$manage_feature]) || $has_all_operations) {
            foreach ($operation_features as $feature) {
                unset($feature_set[$feature]);
            }
            $feature_set[$manage_feature] = true;
        }
    }
    return array_keys($feature_set);
}

function role_permissions_script(): string
{
    return '<script>
document.addEventListener("alpine:init", () => {
    Alpine.data("role_permissions", (role_id) => ({
        role_id,
        busy: false,
        submit() {
            this.busy = true;
            const form = this.$el;
            const features = [...form.querySelectorAll("input[name=\'features[]\']:checked")].map((input) => input.value);
            const custom = form.querySelector("[name=custom_features]")?.value || "";
            nb.api.post(nb.base_url + "/api/v1/role-permissions", {
                role: this.role_id,
                features,
                custom_features: custom,
            }).then((data) => {
                this.busy = false;
                if (data.success) {
                    nb.notify("Permissions saved");
                } else {
                    nb.notify(data.message || "Could not save permissions");
                }
            }).catch((err) => {
                this.busy = false;
                nb.notify(err.message || "Could not save permissions");
            });
        },
    }));
});
document.currentScript.closest("form").querySelectorAll("[data-permission-row]").forEach((row) => {
    const manage = row.querySelector("[data-permission-manage]");
    const operations = [...row.querySelectorAll("[data-permission-operation]")];
    if (!manage || operations.length === 0) return;
    const sync_manage = () => manage.checked = operations.every((input) => input.checked);
    manage.addEventListener("change", () => operations.forEach((input) => input.checked = manage.checked));
    operations.forEach((input) => input.addEventListener("change", sync_manage));
    sync_manage();
});
</script>';
}
