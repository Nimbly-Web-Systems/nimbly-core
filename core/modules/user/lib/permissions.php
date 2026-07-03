<?php

load_library('data');

function permission_operations(): array
{
    return ['view', 'create', 'edit', 'delete', 'import', 'export'];
}

function permission_editor_static_features(): array
{
    return [
        'view-admin-dashboard',
        'view-nimblybar',
        'edit-inline-content',
        'view-.content',
        'create-.content',
        'edit-.content',
        'delete-.content',
        'view-.config',
        'create-.config',
        'edit-.config',
        'view-.files',
        'create-.files',
        'edit-.files',
        'delete-.files',
    ];
}

function permission_resource_features(string $resource): array
{
    $result = [];
    foreach (permission_operations() as $operation) {
        $result[] = $operation . '-' . $resource;
    }
    return $result;
}

function permission_editor_features(): array
{
    $result = permission_editor_static_features();
    foreach (data_resources_list() as $resource => $_info) {
        if (in_array($resource, ['users', 'roles'], true)) {
            continue;
        }
        if ($resource === '' || $resource[0] === '.') {
            continue;
        }
        $result = array_merge($result, permission_resource_features($resource));
    }
    return permission_unique($result);
}

function permission_any_features(): array
{
    $result = permission_editor_features();
    $result = array_merge($result, [
        'view-users',
        'create-users',
        'edit-users',
        'delete-users',
        'import-users',
        'export-users',
        'view-roles',
        'create-roles',
        'edit-roles',
        'delete-roles',
        'import-roles',
        'export-roles',
        'view-system-log',
        'clear-system-log',
        'view-debug',
        'clear-cache',
        'pull-core-updates',
        'pull-ext-updates',
        'manage-system',
    ]);
    return permission_unique($result);
}

function permission_unique(array $features): array
{
    $result = [];
    foreach ($features as $feature) {
        $feature = trim((string)$feature);
        if ($feature === '') {
            continue;
        }
        $result[$feature] = true;
    }
    return array_keys($result);
}

function permission_token_list($features): array
{
    if (is_array($features)) {
        return permission_unique($features);
    }
    return permission_unique(explode(',', (string)$features));
}

function permission_normalize_hidden_alias(string $resource): string
{
    $hidden_aliases = [
        'content' => '.content',
        'config' => '.config',
        'files' => '.files',
        '.files_meta' => '.files',
        'files_unused' => '.files',
    ];
    return $hidden_aliases[$resource] ?? $resource;
}

function permission_normalize_token(string $feature): array
{
    $feature = trim($feature);
    if ($feature === '') {
        return [];
    }
    if ($feature === '(all)' || $feature === '(none)') {
        return [$feature];
    }
    if ($feature === '(any)') {
        return permission_any_features();
    }
    if ($feature === 'manage-content') {
        return permission_editor_features();
    }
    if (preg_match('/^get_(.+)$/', $feature, $matches)) {
        return ['view-' . permission_normalize_hidden_alias($matches[1])];
    }
    if (preg_match('/^add-(.+)$/', $feature, $matches)) {
        return ['create-' . permission_normalize_hidden_alias($matches[1])];
    }
    if (preg_match('/^\(any\)_(.+)$/', $feature, $matches)) {
        return ['manage-' . permission_normalize_hidden_alias($matches[1])];
    }
    if (preg_match('/^(view|create|edit|delete|import|export|manage)-(.+)$/', $feature, $matches)) {
        return [$matches[1] . '-' . permission_normalize_hidden_alias($matches[2])];
    }
    return [$feature];
}

function permission_expand_features($features): array
{
    $result = [];
    foreach (permission_token_list($features) as $feature) {
        $result = array_merge($result, permission_normalize_token($feature));
    }
    return permission_unique($result);
}

function permission_feature_grants(string $stored_feature, string $requested_feature): bool
{
    if ($stored_feature === $requested_feature) {
        return true;
    }
    if (strpos($requested_feature, '-') === false) {
        return false;
    }
    [$operation, $resource] = explode('-', $requested_feature, 2);
    return $operation !== 'manage' && $stored_feature === 'manage-' . $resource;
}

function permission_session_has(string $feature): bool
{
    if (!isset($_SESSION['features'])) {
        return false;
    }
    if (!empty($_SESSION['features']['(all)'])) {
        return true;
    }
    foreach (permission_expand_features($feature) as $requested_feature) {
        if (!empty($_SESSION['features'][$requested_feature])) {
            return true;
        }
        foreach ($_SESSION['features'] as $stored_feature => $enabled) {
            if ($enabled === true && permission_feature_grants($stored_feature, $requested_feature)) {
                return true;
            }
        }
    }
    return false;
}
