<?php

$GLOBALS['SYSTEM'] = [
    'uri_base' => '/jereis',
];

function load_library($_name) {}
function t(string $key): string
{
    return $key;
}

require_once __DIR__ . '/../lib/base-url.php';
require_once __DIR__ . '/../modules/admin/lib/view-resource-record.php';

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function assert_not_contains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$stored = '<p><img src="/old-base/img/43d37e6341ec5e5b45a7fd95a0e6fdba/1200w"></p>';
$rendered = view_resource_record_value('html', $stored);

assert_contains('/jereis/img/43d37e6341ec5e5b45a7fd95a0e6fdba/1200w', $rendered, 'admin record view normalizes stale stored base path');
assert_not_contains('/old-base/', $rendered, 'admin record view drops stale stored base path');

$localized = view_resource_record_localized_value('text', ['nl' => 'Titel', 'en' => ''], ['nl', 'en']);
assert_contains("x-show=\"lang=='nl'\"", $localized, 'localized values follow the active record-view language');
assert_contains('Titel', $localized, 'localized values render the selected translation content');
assert_contains("x-show=\"lang=='en'\"", $localized, 'localized values render every configured language panel');
assert_contains('Empty', $localized, 'blank localized values use the translated empty state');
assert_true(
    view_resource_record_field_is_i18n('title_slug', ['type' => 'slug', 'source' => 'title'], ['title', 'title_slug']),
    'derived i18n slug fields use the localized value renderer'
);

$map = view_resource_record_row('settings', ['name' => 'Settings', 'type' => 'text'], ['nl' => 'Waarde', 'en' => 'Value'], ['nl', 'en']);
assert_contains('<div class="mb-1 font-mono', $map, 'non-i18n maps keep their key/value rendering');
assert_contains('Waarde', $map, 'non-i18n map values remain visible');
assert_not_contains('x-show=', $map, 'non-i18n maps do not become translation panels');

$group = view_resource_record_row('invoice_lines', [
    'name' => 'Invoice lines',
    'type' => 'group',
    'fields' => [
        'description' => ['name' => 'Description', 'type' => 'textarea'],
        'rate' => ['name' => 'Rate', 'type' => 'number'],
        'quantity' => ['name' => 'Quantity', 'type' => 'number'],
    ],
], [[
    'description' => 'Functioneel onderhoud',
    'rate' => 350,
    'quantity' => 1,
]]);
assert_contains('Functioneel onderhoud', $group, 'group fields render their structured values');
assert_contains('Description', $group, 'group fields use child labels from resource metadata');
assert_contains('Rate', $group, 'group fields render every configured child field');
assert_not_contains('<pre', $group, 'group fields do not fall back to raw JSON');

$tabs = view_resource_record_translation_tabs(['nl', 'en'], 'nl');
assert_contains('role="tablist"', $tabs, 'multilingual record views expose translation tabs');
assert_contains('mb-10 flex flex-wrap items-center justify-between gap-3', $tabs, 'record-view tabs match edit-form spacing');
assert_contains('$store.form_language.current=lang', $tabs, 'record-view tabs synchronize the shared language store');
assert_not_contains('role="tablist"', view_resource_record_translation_tabs(['nl'], 'nl'), 'monolingual record views stay unchanged');

echo "View resource record HTML tests passed.\n";
