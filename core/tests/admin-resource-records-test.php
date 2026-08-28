<?php

function load_library($_name) {}

function fmt_sc($params)
{
    return (string)($params['val'] ?? '');
}

require_once __DIR__ . '/../modules/admin/lib/get-resource-records.php';

function admin_resource_records_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$fields = [
    'delivery_status' => [
        'type' => 'select',
        'options' => [
            'sendable' => 'OK',
            'blocked' => 'Blocked',
        ],
    ],
];

$record = _prep_record(['delivery_status' => 'sendable'], $fields);
admin_resource_records_assert($record['delivery_status'] === 'OK', 'select option value uses its display label');

$unknown = _prep_record(['delivery_status' => 'unknown'], $fields);
admin_resource_records_assert($unknown['delivery_status'] === 'unknown', 'unknown select values remain visible');

echo "Admin resource records tests passed.\n";
