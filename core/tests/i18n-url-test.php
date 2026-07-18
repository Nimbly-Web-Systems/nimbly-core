<?php

$GLOBALS['SYSTEM'] = ['variables' => [], 'uri_path' => '', 'request_uri' => 'nl/test'];
$test_config = [
    'languages' => ['nl', 'en'],
    'lang_switch_fallback' => 'home',
];
$test_logs = [];

function load_library($name)
{
    return true;
}

function load_libraries($names)
{
    return true;
}

function data_lookup($resource, $uuid, $field, $default = null)
{
    global $test_config;
    return $test_config[$field] ?? $default;
}

function log_system($message)
{
    global $test_logs;
    $test_logs[] = $message;
}

function detect_language_sc()
{
    return 'nl';
}

function dot2rs($key)
{
    return false;
}

function resolve_i18n($value, $language = 'auto')
{
    if ($language === 'auto') {
        $language = detect_language_sc();
    }
    return $value[$language] ?? '';
}

require_once __DIR__ . '/../lib/run.php';
require_once __DIR__ . '/../lib/get.php';
require_once __DIR__ . '/../lib/set.php';
require_once __DIR__ . '/../lib/i18n-url.php';
require_once __DIR__ . '/../lib/language-switch-data.php';

function test_assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function test_route($json)
{
    $directory = sys_get_temp_dir() . '/nimbly-i18n-url-' . bin2hex(random_bytes(6));
    mkdir($directory);
    file_put_contents($directory . '/i18n-url.json', $json);
    $GLOBALS['SYSTEM']['uri_path'] = $directory;
    return $directory;
}

$route = test_route(json_encode([
    'nl' => 'nl/artikel/[#get record.title_slug lang=nl#]/',
    'en' => 'en/article/[#get record.title_slug lang=en#]/',
]));
set_variable('record.title_slug', ['nl' => 'mijn-reis', 'en' => 'my-trip']);
test_assert_same('en/article/my-trip/', i18n_url_sc(['en' => 'en']), 'Native shortcode interpolation failed.');

file_put_contents($route . '/i18n-url.json', json_encode(['en' => 'en/changed']));
test_assert_same('en/article/my-trip/', i18n_url_sc(['en' => 'en']), 'Route JSON was decoded more than once.');

set_variable('i18n-url.en', 'en/special-page/', true);
test_assert_same('en/special-page/', i18n_url_sc(['en' => 'en']), 'Request override did not win.');
clear_variable('i18n-url.en');

clear_variable('record.title_slug');
test_assert_same('', i18n_url_sc(['en' => 'en']), 'Missing translation emitted a malformed URL.');

test_route(json_encode(['nl' => '(home)', 'en' => '(hide)']));
test_assert_same('nl/', i18n_url_sc(['nl' => 'nl']), '(home) did not resolve to the canonical homepage.');
test_assert_same('hidden', i18n_url_resolve('en')['availability'], '(hide) did not stop fallback processing.');

test_route('{}');
foreach (['home' => 'linked', 'hide' => 'hidden', 'disabled' => 'disabled'] as $fallback => $availability) {
    $test_config['lang_switch_fallback'] = $fallback;
    test_assert_same($availability, i18n_url_resolve('en')['availability'], $fallback . ' fallback failed.');
}

$test_config['lang_switch_fallback'] = 'disabled';
$GLOBALS['SYSTEM']['uri_path'] = test_route('{invalid');
test_assert_same('disabled', i18n_url_resolve('en')['availability'], 'Malformed JSON did not continue to fallback.');
test_assert_same(1, count($test_logs), 'Malformed JSON did not log exactly one diagnostic.');

$test_config['lang_switch_fallback'] = 'home';
test_route(json_encode(['nl' => '(home)', 'en' => '(hide)']));
language_switch_data_sc();
$items = get_variable('language_switch_items');
test_assert_same(1, count($items), 'Language switcher did not exclude hidden entries.');
test_assert_same('true', $items[0]['active'], 'Language switcher did not mark the active language.');
test_assert_same('false', $items[0]['separator'], 'First visible language received a separator.');

echo "i18n-url tests passed\n";
