<?php

function load_library($_name) {}

$GLOBALS['SYSTEM']['data_base'] = sys_get_temp_dir() . '/file-access-test-' . getmypid();
$GLOBALS['test_meta'] = [
    'documents' => ['private_files' => true, 'index' => ['file'], 'fields' => ['file' => ['type' => 'file'], 'title' => ['type' => 'text']]],
    'expenses' => ['private_files' => true, 'fields' => ['receipts' => ['type' => 'file', 'multi' => true]]],
    'downloads' => ['fields' => ['file' => ['type' => 'file']]],
];
$GLOBALS['test_records'] = [
    'expenses' => ['e1' => ['receipts' => ['receipt-uuid']]],
    'downloads' => ['d1' => ['file' => 'public-uuid']],
];
foreach (array_keys($GLOBALS['test_meta']) as $resource) {
    @mkdir($GLOBALS['SYSTEM']['data_base'] . '/' . $resource, 0700, true);
    touch($GLOBALS['SYSTEM']['data_base'] . '/' . $resource . '/.meta');
}

function data_path($resource) { return $GLOBALS['SYSTEM']['data_base'] . '/' . $resource; }
function data_meta($resource) { return $GLOBALS['test_meta'][$resource]; }
function data_read($resource) { return $GLOBALS['test_records'][$resource] ?? []; }
function md5_uuid($value) { return md5($value); }
function data_read_index($resource, $index, $value) { return $value === md5('document-uuid') ? ['x1' => []] : []; }
function access_by_feature($feature) { return in_array($feature, $GLOBALS['test_features'], true); }

require_once __DIR__ . '/../lib/file-access.php';

function file_access_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$GLOBALS['test_features'] = [];
file_access_assert(file_access_allowed('public-uuid'), 'a file of a public resource stays public');
file_access_assert(file_access_allowed('unknown-uuid'), 'a file in no resource stays public');
file_access_assert(!file_access_allowed('document-uuid'), 'an indexed private file is refused');
file_access_assert(!file_access_allowed('receipt-uuid'), 'a private file in a multi field is refused');

$GLOBALS['test_features'] = ['view-documents'];
file_access_assert(file_access_allowed('document-uuid'), 'a viewer of the resource gets its file');
file_access_assert(!file_access_allowed('receipt-uuid'), 'viewing one resource does not open another');

array_map('unlink', glob($GLOBALS['SYSTEM']['data_base'] . '/*/.meta'));
array_map('rmdir', glob($GLOBALS['SYSTEM']['data_base'] . '/*'));
rmdir($GLOBALS['SYSTEM']['data_base']);
echo "file-access: ok\n";
