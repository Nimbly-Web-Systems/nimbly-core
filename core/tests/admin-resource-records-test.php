<?php

function load_library($_name) {}

function fmt_sc($params)
{
    return (string)($params['val'] ?? '');
}

function t($value)
{
    return $value;
}

function permission_session_has(string $feature): bool
{
    return !empty($GLOBALS['test_features'][$feature]);
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

$meta = [
    'fields' => [
        'status' => [
            'name' => 'Status',
            'type' => 'select',
            'options' => ['active' => 'Active', 'closed' => 'Closed'],
            'admin_filter' => ['default' => 'active'],
        ],
        'hidden_category' => [
            'name' => 'Category',
            'type' => 'select',
            'resource' => 'categories',
            'admin_col' => false,
            'admin_filter' => true,
        ],
        'published' => [
            'name' => 'Published',
            'type' => 'boolean',
            'admin_filter' => true,
        ],
        'permitted' => [
            'name' => 'Permitted',
            'type' => 'boolean',
            'admin_filter' => ['feature' => 'view-private-filter'],
        ],
        'denied' => [
            'name' => 'Denied',
            'type' => 'boolean',
            'admin_filter' => ['feature' => 'view-denied-filter'],
        ],
        'ignored' => [
            'name' => 'Ignored',
            'type' => 'text',
            'admin_filter' => true,
        ],
    ],
];

$GLOBALS['test_features'] = ['view-private-filter' => true];
$filter_fields = _prep_filter_fields($meta);
admin_resource_records_assert(isset($filter_fields['hidden_category']), 'hidden admin columns remain filterable');
admin_resource_records_assert(isset($filter_fields['permitted']), 'permitted filters remain visible');
admin_resource_records_assert(!isset($filter_fields['denied']), 'filters without their required feature remain hidden');
admin_resource_records_assert(!isset($filter_fields['ignored']), 'unsupported field types do not become filters');

$filters = _prep_filters($filter_fields, [
    'categories' => ['cat-a' => 'Category A', 'cat-b' => 'Category B'],
]);
admin_resource_records_assert($filters['status']['default'] === 'active', 'select defaults retain raw stored values');
admin_resource_records_assert($filters['status']['options']['active'] === 'Active', 'fixed select filters expose configured labels');
admin_resource_records_assert($filters['hidden_category']['options']['cat-a'] === 'Category A', 'resource filters expose related record labels');
admin_resource_records_assert($filters['published']['options'] === ['1' => 'Yes', '0' => 'No'], 'boolean filters expose yes and no options');

$raw_values = _prep_filter_values([
    'status' => 'active',
    'hidden_category' => ['cat-a', 'cat-b'],
    'published' => false,
    'denied' => true,
], $filter_fields);
admin_resource_records_assert($raw_values['status'] === 'active', 'filter records retain raw select values');
admin_resource_records_assert($raw_values['hidden_category'] === ['cat-a', 'cat-b'], 'multi-value selects retain every raw value');
admin_resource_records_assert($raw_values['published'] === '0', 'boolean false normalizes to its stored filter value');
admin_resource_records_assert(!isset($raw_values['denied']), 'denied filter values are not exposed in records');

echo "Admin resource records tests passed.\n";
