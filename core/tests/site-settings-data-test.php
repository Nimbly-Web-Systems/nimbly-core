<?php

function site_settings_data_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function site_settings_data_remove_fixture(string $path): void
{
    if (!is_dir($path)) return;
    foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $item) {
        $item_path = $path . '/' . $item;
        is_dir($item_path) ? site_settings_data_remove_fixture($item_path) : unlink($item_path);
    }
    rmdir($path);
}

$fixture = sys_get_temp_dir() . '/nimbly-site-settings-' . bin2hex(random_bytes(4));
mkdir($fixture . '/.config', 0755, true);
$GLOBALS['SYSTEM'] = [
    'file_base' => dirname(__DIR__, 2) . '/',
    'env_paths' => ['ext', 'core'],
    'modules' => ['root' => '/'],
    'variables' => [],
    'data_error' => null,
];
file_put_contents($fixture . '/.config/.meta', json_encode([
    'fields' => false,
    'validate_library' => 'site-settings',
    'validate_function' => 'site_settings_validate_config',
]));
file_put_contents($fixture . '/.config/site', json_encode([
    'uuid' => 'site',
    'languages' => ['en', 'nl', 'pt'],
    'name' => ['en' => 'Name', 'nl' => 'Naam', 'de' => 'Bewahrt'],
]));

require_once __DIR__ . '/../lib/find.php';
load_library('data');
$GLOBALS['SYSTEM']['data_base'] = $fixture;

$general = data_update('.config', 'site', ['name' => ['en' => 'New name']]);
site_settings_data_assert(is_array($general), 'General settings update failed.');
site_settings_data_assert($general['name']['nl'] === 'Naam' && $general['name']['de'] === 'Bewahrt', 'General save discarded stored translations.');

$added = data_update('.config', 'site', ['languages' => ['en', 'nl', 'pt', 'de']]);
site_settings_data_assert(is_array($added) && $added['languages'][3] === 'de', 'Supported language append failed through data_update().');

$before_invalid = file_get_contents($fixture . '/.config/site');
$invalid = data_update('.config', 'site', ['languages' => ['nl', 'en', 'pt', 'de']]);
site_settings_data_assert($invalid === false, 'Language reorder was accepted through data_update().');
site_settings_data_assert(file_get_contents($fixture . '/.config/site') === $before_invalid, 'Rejected language update partially changed the record.');

site_settings_data_remove_fixture($fixture);
echo "Site settings data tests passed.\n";
