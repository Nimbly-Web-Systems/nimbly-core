<?php

load_library('data');
load_library('permissions');
load_library('role-permissions');

$role_id = get_variable('role-id');
$features = $_POST['features'] ?? [];
$custom_features = permission_token_list($_POST['custom_features'] ?? '');
$features = permission_expand_features(array_merge((array)$features, $custom_features));
$features = role_permissions_compact_features($features);

data_update('roles', $role_id, ['features' => implode(',', $features)]);
