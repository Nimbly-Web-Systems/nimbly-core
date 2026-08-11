<?php

$GLOBALS['SYSTEM'] = [
    'request_uri' => 'news/item',
    'uri_base' => '/alias/',
    'variables' => [],
];
$_SERVER['HTTP_HOST'] = 'example.test';
$_SERVER['SERVER_NAME'] = 'example.test';
$_SERVER['HTTPS'] = 'on';

function load_library($name) {}
function data_lookup($resource, $uuid, $field, $default = null) { return $default; }

require_once __DIR__ . '/../lib/seo-page.php';

function seo_test_same($expected, $actual, $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: {$expected}\nActual: {$actual}\n");
        exit(1);
    }
}

putenv('SITE_URL');
seo_test_same('https://example.test/alias', seo_site_root(), 'Request-origin site root was incorrect.');
seo_test_same('https://example.test/alias/news/item/', seo_canonical_url(), 'Nested canonical URL was incorrect.');
seo_test_same('https://example.test/alias/news/item/', seo_canonical_url('/news/item/?preview=1'), 'Query or trailing slash was not normalized.');
putenv('SITE_URL=https://public.example/base/');
seo_test_same('https://public.example/base', seo_site_root(), 'SITE_URL did not take precedence.');
seo_test_same('https://public.example/base/', seo_canonical_url(''), 'Canonical root was incorrect.');
seo_test_same('https://other.example/story/', seo_canonical_url('https://other.example/story?x=1'), 'Absolute override was incorrect.');

echo "SEO tests passed\n";
