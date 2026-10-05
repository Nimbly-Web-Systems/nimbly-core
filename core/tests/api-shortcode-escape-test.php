<?php

// Record values saved through the API must not keep a live shortcode tag,
// while a one-way encrypted field (password) stays exactly as typed.

$GLOBALS['SYSTEM'] = ['variables' => []];
$_SERVER['PEPPER'] = 'fixture-pepper';
$GLOBALS['fixture_meta'] = [
    'encrypt' => 'password',
    'encrypt2way' => 'secret',
    'fields' => ['body' => ['type' => 'html'], 'title' => ['type' => 'text']],
];
$GLOBALS['fixture_input'] = [];

function load_library($library)
{
    $file = dirname(__DIR__) . '/lib/' . $library . '.php';
    if (in_array($library, ['request-input', 'html-sanitize', 'encrypt', 'util'], true)) {
        require_once $file;
    }
}

function data_meta($resource)
{
    return $GLOBALS['fixture_meta'];
}

function json_input($create_uuid = true)
{
    return $GLOBALS['fixture_input'];
}

if (!function_exists('getallheaders')) {
    function getallheaders()
    {
        return [];
    }
}

require_once dirname(__DIR__) . '/modules/api/lib/api.php';

function api_shortcode_escape_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function api_shortcode_escape_has_tag($value)
{
    $text = json_encode($value);
    return strpos($text, '[#') !== false || strpos($text, '#]') !== false;
}

$password = 'pw[#env APP_ENV#]';
$GLOBALS['fixture_input'] = [
    'uuid' => 'record-1',
    'title' => 'A [#env APP_ENV#] title',
    'plain' => 'no tags here & <b>bold</b>',
    'count' => 3,
    'name' => ['en' => 'x [#include secret#]', 'nl' => 'gewoon'],
    'password' => $password,
];
$data = api_json_input('fixture');

api_shortcode_escape_assert($data['title'] === 'A &#91;#env APP_ENV#&#93; title', 'a plain field kept a live shortcode');
api_shortcode_escape_assert($data['plain'] === 'no tags here & <b>bold</b>', 'a value without tags was changed');
api_shortcode_escape_assert($data['count'] === 3, 'a non-string value was changed');
api_shortcode_escape_assert($data['name']['en'] === 'x &#91;#include secret#&#93;', 'a translated value kept a live shortcode');
api_shortcode_escape_assert($data['name']['nl'] === 'gewoon', 'a translated value without tags was changed');
api_shortcode_escape_assert(
    $data['password'] === encrypt($password, $data['salt']),
    'the password was not hashed as typed, so login would fail'
);
// each encryption loop writes its own salt, so the two-way field gets its own record
$GLOBALS['fixture_input'] = ['uuid' => 'record-2', 'secret' => 'key [#env PEPPER#]'];
$data_2way = api_json_input('fixture');
api_shortcode_escape_assert(
    decrypt_2way($data_2way['secret'], $data_2way['salt']) === 'key &#91;#env PEPPER#&#93;',
    'a two-way encrypted field kept a live shortcode'
);

$again = api_escape_shortcodes($GLOBALS['fixture_meta'], ['title' => $data['title']]);
api_shortcode_escape_assert($again['title'] === $data['title'], 'saving an escaped value again changed it');

// bulk update: a uuid-keyed map of records
$bulk = api_escape_shortcodes($GLOBALS['fixture_meta'], ['record-1' => ['title' => '[#env A#]'], 'record-2' => ['title' => 'ok']]);
api_shortcode_escape_assert(!api_shortcode_escape_has_tag($bulk), 'a bulk update kept a live shortcode');
api_shortcode_escape_assert($bulk['record-2']['title'] === 'ok', 'a bulk value without tags was changed');

// the html sanitizer rewrites a field it has to clean, which decodes entities
if (class_exists('DOMDocument')) {
    $GLOBALS['fixture_input'] = ['uuid' => 'record-1', 'body' => '<p onclick="x()">a [#env APP_ENV#] b</p>'];
    $clean = api_sanitize_html_fields('fixture', api_json_input('fixture'));
    api_shortcode_escape_assert(strpos($clean['body'], 'onclick') === false, 'the sanitizer did not run');
    api_shortcode_escape_assert(!api_shortcode_escape_has_tag($clean['body']), 'the sanitized html field got its shortcode back');
    api_shortcode_escape_assert(strpos($clean['body'], '&#91;#env APP_ENV#&#93;') !== false, 'the sanitized html field lost its text');
} else {
    echo "skipped: html sanitizer case needs the dom extension\n";
}

echo "api shortcode escape test passed\n";
