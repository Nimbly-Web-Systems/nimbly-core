<?php

$GLOBALS['SYSTEM'] = [
    'uri_base' => '/jereis',
    'variables' => [
        'content.test.body' => '<p><img src="/img/43d37e6341ec5e5b45a7fd95a0e6fdba/1200w"></p>'
            . '<video src="/download/210f279f8d270aae7c98921ec7b15cbf"></video>',
        'content.test.prefixed' => '<img src="/old-base/img/43d37e6341ec5e5b45a7fd95a0e6fdba/1200w">',
    ],
];

function load_library($_name) {}
function get_param_value($params, $key, $default = '') { return $params[$key] ?? $default; }
function get_single_param_value($params, $key, $default = false) { return $params[$key] ?? $default; }

require_once __DIR__ . '/../lib/base-url.php';
require_once __DIR__ . '/../lib/get-html.php';

function render_get_html(array $params): string
{
    ob_start();
    get_html_sc($params);
    return ob_get_clean();
}

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$rendered = render_get_html([0 => 'content.test.body', 'plain' => false]);
assert_contains('/jereis/img/43d37e6341ec5e5b45a7fd95a0e6fdba/1200w', $rendered, 'image URL receives base path');
assert_contains('/jereis/download/210f279f8d270aae7c98921ec7b15cbf', $rendered, 'download URL receives base path');

$prefixed = render_get_html([0 => 'content.test.prefixed', 'plain' => false]);
assert_contains('/jereis/img/43d37e6341ec5e5b45a7fd95a0e6fdba/1200w', $prefixed, 'stored base path is normalized');
if (str_contains($prefixed, '/old-base/')) {
    fwrite(STDERR, "FAIL: stale stored base path remains\n");
    exit(1);
}

echo "Get HTML base-path tests passed.\n";
