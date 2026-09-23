<?php

$fixture = sys_get_temp_dir() . '/nimbly-managed-pages-' . bin2hex(random_bytes(5));
mkdir($fixture . '/ext/modules/managed-pages', 0755, true);
mkdir($fixture . '/ext/data/.navigation', 0755, true);
file_put_contents($fixture . '/ext/modules/managed-pages/url-areas.json', json_encode([
    'enabled' => ['en', 'nl'],
    'include_site_languages' => true,
    'reserved' => ['en/private'],
]));
file_put_contents($fixture . '/ext/modules/managed-pages/navigation-slots.json', json_encode([
    'main' => ['name' => 'Main', 'depth' => 2],
]));

$GLOBALS['SYSTEM'] = [
    'file_base' => $fixture . '/',
    'request_uri' => 'nl/campaign',
    'variables' => [],
    'data_error' => null,
];
$GLOBALS['test_records'] = [
    '.config' => [
        'site' => ['languages' => ['en', 'nl', 'de']],
        'managed_pages' => [
            'enabled' => true,
            'page_types' => [
                'campaign' => ['name' => 'Campaign page', 'template' => 'page-campaign'],
            ],
        ],
    ],
    'pages' => [
        'page-1' => [
            'type' => 'default',
            'title' => ['en' => 'Campaign', 'nl' => 'Campagne'],
            'path' => ['en' => 'en/campaign', 'nl' => 'nl/campaign'],
            'published' => ['en' => false, 'nl' => true],
            'previous_paths' => ['nl' => ['nl/old-campaign']],
        ],
        'page-2' => [
            'type' => 'default',
            'title' => ['nl' => 'Hidden'],
            'path' => ['nl' => 'nl/hidden'],
            'published' => ['nl' => false],
        ],
    ],
    '.navigation' => [],
];

function load_library($name) {}
function load_libraries($names) {}
function find_uri($path, $file = 'index.tpl') { return $path === 'nl/code-route' ? '/code/' . $file : false; }
function find_template($name) { return str_ends_with($name, '-main') ? false : '/templates/' . $name . '/index.tpl'; }
function run_buffered($path) { return 'rendered:' . $path; }
function run($path) { $GLOBALS['test_rendered_template'] = $path; }
function redirect($path, $status = 302) { $GLOBALS['test_redirect'] = [$path, $status]; }
function access_by_feature($feature) { return $feature === 'edit-pages' && !empty($GLOBALS['test_can_edit_pages']); }
function text_translate($language, $text) { return $text; }
function system_message($message) { $GLOBALS['test_system_message'] = $message; }
function set_variable($name, $value) { $GLOBALS['SYSTEM']['variables'][$name] = $value; }
function set_variable_dot($name, $value) { $GLOBALS['SYSTEM']['variables'][$name] = $value; }
function get_variable($name, $default = null) { return $GLOBALS['SYSTEM']['variables'][$name] ?? $default; }
function data_exists($resource, $uuid = null) {
    if ($uuid === null) return array_key_exists($resource, $GLOBALS['test_records']);
    return isset($GLOBALS['test_records'][$resource][$uuid]);
}
function data_read($resource, $uuid = null) {
    return $uuid === null ? ($GLOBALS['test_records'][$resource] ?? []) : ($GLOBALS['test_records'][$resource][$uuid] ?? null);
}
function data_list($resource) { return array_keys($GLOBALS['test_records'][$resource] ?? []); }
function data_lookup($resource, $uuid, $field, $default = null) {
    return $GLOBALS['test_records'][$resource][$uuid][$field] ?? $default;
}
function data_path($resource) { return $GLOBALS['SYSTEM']['file_base'] . 'ext/data/' . $resource; }
function data_create($resource, $uuid, $record) { $GLOBALS['test_records'][$resource][$uuid] = $record; return true; }
function data_error_set($error, $detail = null) { $GLOBALS['SYSTEM']['data_error'] = $error; $GLOBALS['SYSTEM']['data_error_detail'] = $detail; }

require_once __DIR__ . '/../modules/managed-pages/lib/managed-pages.php';
require_once __DIR__ . '/../modules/managed-pages/lib/managed-navigation.php';
require_once __DIR__ . '/../modules/managed-pages/lib/managed-page-preview.php';

function managed_pages_test_assert($condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

managed_pages_test_assert(managed_pages_types()['default']['template'] === 'managed-page-default', 'Core default page type is unavailable.');
managed_pages_test_assert(managed_pages_types()['campaign']['template'] === 'page-campaign', 'Application page type was not merged.');
managed_pages_test_assert(managed_pages_type_options() === ['default' => 'Default page', 'campaign' => 'Campaign page'], 'Page type options do not match declarations.');
managed_pages_test_assert(managed_pages_path_in_area('de/new-page'), 'Site language URL prefix was not enabled by the declaration opt-in.');
file_put_contents($fixture . '/ext/modules/managed-pages/url-areas.json', json_encode([
    'enabled' => ['en', 'nl'], 'reserved' => ['en/private'],
]));
managed_pages_test_assert(!managed_pages_path_in_area('de/new-page'), 'Fixed URL declaration unexpectedly inherited site languages.');
file_put_contents($fixture . '/ext/modules/managed-pages/url-areas.json', json_encode([
    'enabled' => ['en', 'nl'], 'include_site_languages' => true, 'reserved' => ['en/private'],
]));
managed_pages_test_assert(managed_pages_normalize_path('/nl/campaign/') === 'nl/campaign', 'Canonical path normalization failed.');
managed_pages_test_assert(managed_pages_normalize_path('nl//campaign') === null, 'Ambiguous path was accepted.');
managed_pages_test_assert(!managed_pages_path_in_area('en/private/report'), 'Reserved subtree was accepted.');
managed_pages_test_assert(managed_pages_find('nl/campaign')['uuid'] === 'page-1', 'Published localized page was not found.');
managed_pages_test_assert(managed_pages_find('nl/old-campaign')['alias'] === true, 'Historical alias was not found.');
managed_pages_test_assert(managed_pages_find('en/campaign') === null, 'Unpublished translation was public.');
managed_pages_test_assert(managed_pages_url('page-1', 'en') === null, 'Unpublished URL lookup succeeded.');

$GLOBALS['test_records']['.config']['managed_pages']['enabled'] = false;
managed_pages_test_assert(!managed_pages_feature_enabled(), 'Disabled custom pages were reported as enabled.');
managed_pages_test_assert(managed_pages_default_creation_type() === null, 'Disabled custom pages still allowed page creation.');
managed_pages_test_assert(managed_pages_run('nl/campaign') === false, 'Disabled custom pages still resolved through the router fallback.');
$GLOBALS['test_records']['.config']['managed_pages']['enabled'] = true;

$german_page = [
    'type' => 'default',
    'title' => ['de' => 'Neu'],
    'path' => ['de' => 'de/neu'],
    'published' => ['de' => true],
];
managed_pages_test_assert(managed_pages_validate_record('pages', 'page-de', $german_page) === true, 'New site language page was rejected.');
$GLOBALS['test_records']['pages']['page-de'] = $german_page;
managed_pages_test_assert(managed_pages_find('de/neu')['uuid'] === 'page-de', 'Published site language page did not resolve.');
$german_navigation = [[
    'id' => 'de-page', 'label' => 'Neu', 'target' => ['kind' => 'page', 'value' => 'page-de'], 'children' => [],
]];
managed_pages_test_assert(managed_navigation_save('main', 'de', $german_navigation, '')['ok'] === true, 'New site language navigation was rejected.');
managed_pages_test_assert(managed_navigation_load('main', 'de')[0]['url'] === 'de/neu', 'New site language navigation target did not resolve.');

$candidate = [
    'type' => 'default',
    'path' => ['nl' => 'nl/code-route'],
    'published' => ['nl' => false],
];
managed_pages_test_assert(managed_pages_validate_record('pages', 'new-page', $candidate) === false, 'Code-route collision was accepted.');

$GLOBALS['test_records']['.config']['managed_pages']['enabled_page_types'] = ['campaign'];
managed_pages_test_assert(managed_pages_type_options() === ['campaign' => 'Campaign page'], 'Creation choices ignore enabled page types.');
managed_pages_test_assert(managed_pages_default_creation_type() === 'campaign', 'First enabled page type was not selected as the creation default.');
$disabled_type = ['type' => 'default', 'path' => ['nl' => 'nl/new'], 'published' => ['nl' => false]];
managed_pages_test_assert(managed_pages_validate_record('pages', 'new-disabled', $disabled_type) === false, 'Disabled type was accepted for a new page.');
$existing_page = $GLOBALS['test_records']['pages']['page-1'];
managed_pages_test_assert(managed_pages_validate_record('pages', 'page-1', $existing_page) === true, 'Existing page with a disabled type could not be edited.');
$GLOBALS['test_records']['.config']['managed_pages']['enabled_page_types'] = [];
managed_pages_test_assert(managed_pages_default_creation_type() === null, 'Empty availability policy did not disable page creation.');
unset($GLOBALS['test_records']['.config']['managed_pages']['enabled_page_types']);

$tree = [[
    'id' => 'page',
    'label' => 'Campaign',
    'target' => ['kind' => 'page', 'value' => 'page-1'],
    'children' => [],
], [
    'id' => 'hidden',
    'label' => 'Hidden',
    'target' => ['kind' => 'page', 'value' => 'page-2'],
    'children' => [],
]];
$saved = managed_navigation_save('main', 'nl', $tree, '');
managed_pages_test_assert($saved['ok'] === true, 'Initial navigation save failed.');
managed_pages_test_assert(managed_navigation_save('main', 'nl', [], '')['error'] === 'stale', 'Stale navigation save was accepted.');
$nested = [[
    'id' => 'parent', 'label' => 'Parent', 'target' => ['kind' => 'group', 'value' => ''],
    'children' => [[
        'id' => 'child', 'label' => 'Child', 'target' => ['kind' => 'internal_url', 'value' => 'nl/child'], 'children' => [],
    ]],
]];
managed_pages_test_assert(managed_navigation_validate_items($nested, 2) !== null, 'Allowed navigation depth was rejected.');
managed_pages_test_assert(managed_navigation_validate_items($nested, 1) === null, 'Excess navigation depth was accepted.');
$public_tree = managed_navigation_load('main', 'nl');
managed_pages_test_assert(count($public_tree) === 1 && $public_tree[0]['url'] === 'nl/campaign', 'Unavailable navigation target was not filtered.');
managed_pages_test_assert($public_tree[0]['current'] === 'true', 'Current navigation state was not exposed to templates.');

$GLOBALS['test_can_edit_pages'] = false;
managed_pages_test_assert(managed_pages_run('nl/hidden') === false, 'An anonymous visitor could resolve an unpublished page.');
$GLOBALS['test_can_edit_pages'] = true;
managed_pages_test_assert(managed_pages_run('nl/hidden') === true, 'An editor could not preview an unpublished page.');
managed_pages_test_assert(
    ($GLOBALS['test_system_message'] ?? '') === "You're previewing an unpublished page. Only editors can see this.",
    'The unpublished-page preview message was not created.'
);
$GLOBALS['SYSTEM']['variables']['_bf_uuid'] = 'page-2';
managed_pages_test_assert(managed_page_preview_sc() === '[#managed-page-preview-panel#]', 'The page preview action was unavailable.');
managed_pages_test_assert(
    ($GLOBALS['SYSTEM']['variables']['managed_page_preview_languages'][0]['preview_url'] ?? '') === '/nl/hidden',
    'The page preview action URL was incorrect.'
);

$remove = function ($path) use (&$remove) {
    if (is_dir($path)) {
        foreach (array_diff(scandir($path), ['.', '..']) as $item) $remove($path . '/' . $item);
        rmdir($path);
    } elseif (file_exists($path)) {
        unlink($path);
    }
};
$remove($fixture);
echo "Managed pages tests passed\n";
