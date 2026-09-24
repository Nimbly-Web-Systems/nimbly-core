<?php

$GLOBALS['test_site_config'] = ['languages' => ['en', 'nl', 'pt']];
$GLOBALS['SYSTEM'] = ['data_error' => null];

function load_library($name) {}
function data_exists($resource, $uuid = null) { return $resource === '.config' && $uuid === 'site'; }
function data_read($resource, $uuid = null) { return $uuid === 'site' ? $GLOBALS['test_site_config'] : []; }
function data_error_set($error, $detail = null) { $GLOBALS['SYSTEM']['data_error'] = $error; $GLOBALS['SYSTEM']['data_error_detail'] = $detail; }

require_once __DIR__ . '/../modules/managed-pages/lib/managed-pages.php';
require_once __DIR__ . '/../modules/admin/lib/site-settings/site-settings.php';

function site_settings_validation_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$append = ['languages' => ['en', 'nl', 'pt', 'de']];
site_settings_validation_assert(site_settings_validate_config('.config', 'site', $append), 'Supported append was rejected.');

$remove = ['languages' => ['en', 'nl']];
site_settings_validation_assert(!site_settings_validate_config('.config', 'site', $remove), 'Language removal was accepted.');

$reorder = ['languages' => ['nl', 'en', 'pt']];
site_settings_validation_assert(!site_settings_validate_config('.config', 'site', $reorder), 'Language reorder was accepted.');

$unsupported = ['languages' => ['en', 'nl', 'pt', 'xx']];
site_settings_validation_assert(!site_settings_validate_config('.config', 'site', $unsupported), 'Unsupported language was accepted.');

$duplicate = ['languages' => ['en', 'nl', 'pt', 'pt']];
site_settings_validation_assert(!site_settings_validate_config('.config', 'site', $duplicate), 'Duplicate language was accepted.');

$valid_policy = ['page_types' => ['campaign' => ['template' => 'page-campaign']], 'enabled_page_types' => ['campaign']];
site_settings_validation_assert(site_settings_validate_config('.config', 'managed_pages', $valid_policy), 'Valid page type policy was rejected.');

$valid_enabled = ['enabled' => true];
site_settings_validation_assert(site_settings_validate_config('.config', 'managed_pages', $valid_enabled), 'Boolean custom pages setting was rejected.');

$invalid_enabled = ['enabled' => 'true'];
site_settings_validation_assert(!site_settings_validate_config('.config', 'managed_pages', $invalid_enabled), 'Non-boolean custom pages setting was accepted.');

$invalid_policy = ['page_types' => ['campaign' => ['template' => 'page-campaign']], 'enabled_page_types' => ['missing']];
site_settings_validation_assert(!site_settings_validate_config('.config', 'managed_pages', $invalid_policy), 'Unknown page type policy was accepted.');

$unrelated = ['anything' => true];
site_settings_validation_assert(site_settings_validate_config('.config', 'other', $unrelated), 'Unrelated configuration was rejected.');

$old = ['enabled' => true, 'enabled_page_types' => ['default'], 'page_types' => [], '_modified' => 1];
site_settings_validation_assert(site_settings_managed_pages_changed_keys($old, $old + ['_modified' => 2]) === [], 'Metadata changes were treated as configuration changes.');
site_settings_validation_assert(site_settings_managed_pages_changed_keys($old, ['enabled' => false] + $old) === ['enabled'], 'Switching custom pages off was not reported as a restricted change.');
site_settings_validation_assert(site_settings_managed_pages_changed_keys($old, $old + ['navigation_slots' => ['main' => ['name' => 'Main', 'depth' => 2]]]) === ['navigation_slots'], 'Adding a menu was not reported as a restricted change.');
site_settings_validation_assert(site_settings_managed_pages_changed_keys([], ['enabled_page_types' => ['default']]) === ['enabled_page_types'], 'A first write of restricted keys was not reported.');

$valid_areas = ['url_areas' => ['enabled' => ['en'], 'reserved' => ['api'], 'include_site_languages' => true, 'allow_unprefixed' => false]];
site_settings_validation_assert(site_settings_validate_config('.config', 'managed_pages', $valid_areas), 'Valid URL areas were rejected.');
foreach ([
    'url_areas as a string' => ['url_areas' => 'en'],
    'reserved entries that are not strings' => ['url_areas' => ['reserved' => [1]]],
    'a non-boolean flag' => ['url_areas' => ['allow_unprefixed' => 'yes']],
] as $label => $bad) {
    site_settings_validation_assert(!site_settings_validate_config('.config', 'managed_pages', $bad), "Invalid URL areas accepted: {$label}.");
}
$valid_slots = ['navigation_slots' => ['main' => ['name' => 'Main navigation', 'depth' => 2], 'footer' => ['name' => 'Footer', 'depth' => 1]]];
site_settings_validation_assert(site_settings_validate_config('.config', 'managed_pages', $valid_slots), 'Valid menus were rejected.');
foreach ([
    'an id with spaces' => ['navigation_slots' => ['Main Menu' => ['name' => 'Main', 'depth' => 1]]],
    'a missing name' => ['navigation_slots' => ['main' => ['depth' => 1]]],
    'a depth of zero' => ['navigation_slots' => ['main' => ['name' => 'Main', 'depth' => 0]]],
    'a depth that is not a number' => ['navigation_slots' => ['main' => ['name' => 'Main', 'depth' => 'deep']]],
    'a menu list that is not an object' => ['navigation_slots' => 'main'],
] as $label => $bad) {
    site_settings_validation_assert(!site_settings_validate_config('.config', 'managed_pages', $bad), "Invalid menu accepted: {$label}.");
}

site_settings_validation_assert(site_settings_managed_pages_changed_keys(['navigation_enabled' => false], ['navigation_enabled' => true]) === ['navigation_enabled'], 'Switching navigation editing on was not reported as a restricted change.');
$navigation_on = ['navigation_enabled' => true];
site_settings_validation_assert(site_settings_validate_config('.config', 'managed_pages', $navigation_on), 'Boolean navigation setting was rejected.');
$navigation_bad = ['navigation_enabled' => 'yes'];
site_settings_validation_assert(!site_settings_validate_config('.config', 'managed_pages', $navigation_bad), 'Non-boolean navigation setting was accepted.');
echo "Site settings validation tests passed.\n";
