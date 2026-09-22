<?php

$GLOBALS['test_site_config'] = ['languages' => ['en', 'nl', 'pt']];
$GLOBALS['SYSTEM'] = ['data_error' => null];

function load_library($name) {}
function data_exists($resource, $uuid = null) { return $resource === '.config' && $uuid === 'site'; }
function data_read($resource, $uuid = null) { return $uuid === 'site' ? $GLOBALS['test_site_config'] : []; }
function data_error_set($error, $detail = null) { $GLOBALS['SYSTEM']['data_error'] = $error; $GLOBALS['SYSTEM']['data_error_detail'] = $detail; }

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

$invalid_policy = ['page_types' => ['campaign' => ['template' => 'page-campaign']], 'enabled_page_types' => ['missing']];
site_settings_validation_assert(!site_settings_validate_config('.config', 'managed_pages', $invalid_policy), 'Unknown page type policy was accepted.');

$unrelated = ['anything' => true];
site_settings_validation_assert(site_settings_validate_config('.config', 'other', $unrelated), 'Unrelated configuration was rejected.');

echo "Site settings validation tests passed.\n";
