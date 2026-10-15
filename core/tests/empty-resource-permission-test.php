<?php

// Emptying a whole resource (Delete all files) needs the delete-all right, which only admins have.
function load_library($library) {}
require dirname(__DIR__) . '/modules/user/lib/permissions.php';

$editor = ['delete-.files' => true, 'manage-.files' => true, 'delete-events' => true];
$checks = [
    'editor cannot empty files' => !permission_features_have($editor, 'api_delete_resource_.files'),
    'editor cannot empty events' => !permission_features_have($editor, 'api_delete_resource_events'),
    'admin can empty files' => permission_features_have(['(all)' => true], 'api_delete_resource_.files'),
    'route requires the delete-all right' => str_contains(file_get_contents(__DIR__ . '/../modules/admin/uri/api/v1/empty-resource/index.tpl'), '[#access feature=api_delete_resource_[#resource#]#]'),
    'button requires the delete-all right' => str_contains(file_get_contents(__DIR__ . '/../modules/admin/uri/nb-admin/media/main.tpl'), 'features="api_delete_resource_.files"'),
];
$failed = array_keys(array_filter($checks, fn($ok) => !$ok));
if ($failed) {
    fwrite(STDERR, "FAIL: " . implode(', ', $failed) . "\n");
    exit(1);
}
echo "empty-resource permission tests passed\n";
