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

// Links in content: a root-relative address gets the base path, once.
$links = [
    '<a href="/contact">' => '<a href="/jereis/contact">',
    '<a class="x" href="/en/article/deep-space?a=1#top" target="_blank">' => '<a class="x" href="/jereis/en/article/deep-space?a=1#top" target="_blank">',
    '<a href="/">' => '<a href="/jereis/">',
    "<a href='/contact'>" => "<a href='/jereis/contact'>",
    '<a href="/jereis/contact">' => '<a href="/jereis/contact">',
    '<a href="/jereis">' => '<a href="/jereis">',
    '<a href="/jereis?x=1">' => '<a href="/jereis?x=1">',
    '<a href="/jereissen/contact">' => '<a href="/jereis/jereissen/contact">',
    '<a href="//example.com/contact">' => '<a href="//example.com/contact">',
    '<a href="https://example.com/contact">' => '<a href="https://example.com/contact">',
    '<a href="contact">' => '<a href="contact">',
    '<a href="#top">' => '<a href="#top">',
    '<a href="mailto:a@example.com">' => '<a href="mailto:a@example.com">',
    '<a href="/img/43d37e6341ec5e5b45a7fd95a0e6fdba/1200w">' => '<a href="/jereis/img/43d37e6341ec5e5b45a7fd95a0e6fdba/1200w">',
    '<p>write href="/contact" in the text</p>' => '<p>write href="/contact" in the text</p>',
];
foreach ($links as $stored => $expected) {
    $GLOBALS['SYSTEM']['variables']['content.test.link'] = $stored . 'x</a>';
    assert_contains($expected . 'x', render_get_html([0 => 'content.test.link', 'plain' => false]), "link {$stored} becomes {$expected}");
}

// At the root of a host nothing changes.
$GLOBALS['SYSTEM']['uri_base'] = '/';
$GLOBALS['SYSTEM']['variables']['content.test.link'] = '<a href="/contact">x</a>';
assert_contains('<a href="/contact">x</a>', render_get_html([0 => 'content.test.link', 'plain' => false]), 'a root site leaves links alone');

echo "Get HTML base-path tests passed.\n";
