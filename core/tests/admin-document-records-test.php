<?php

function load_library($_name) {}

function resource_title_field(string $resource): ?string
{
    return $GLOBALS['test_title_field'];
}

require_once __DIR__ . '/../modules/admin/lib/get-document-records.php';

function admin_document_records_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

// the explorer finds its fields in the .meta
$GLOBALS['test_title_field'] = 'title';
$fields = document_explorer_fields('documents', [
    'fields' => [
        'title' => ['type' => 'text'],
        'tags' => ['type' => 'select', 'admin_filter' => true, 'multiple' => true],
        'category' => ['type' => 'select', 'admin_filter' => true],
        'file' => ['type' => 'file'],
        'scan' => ['type' => 'file'],
        'cover' => ['type' => 'image'],
        'position' => ['type' => 'number'],
    ],
    'sort' => ['field' => 'position'],
]);
admin_document_records_assert($fields === ['file' => 'file', 'cover' => 'cover', 'category' => 'category', 'position' => 'position', 'title' => 'title'], 'fields are detected');

// a translated title, a text sort field and no select leave those parts out
$GLOBALS['test_title_field'] = 'name';
$fields = document_explorer_fields('documents', [
    'fields' => [
        'name' => ['type' => 'text', 'i18n' => true],
        'file' => ['type' => 'file'],
        'date' => ['type' => 'date'],
    ],
    'sort' => ['field' => 'date'],
]);
admin_document_records_assert($fields === ['file' => 'file', 'cover' => null, 'category' => null, 'position' => null, 'title' => null], 'optional fields stay empty');

echo "admin-document-records: ok\n";
