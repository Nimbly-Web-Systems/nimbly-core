<?php

$fixture = sys_get_temp_dir() . '/nimbly-sitemap-' . bin2hex(random_bytes(5));
mkdir($fixture . '/ext/uri/about', 0755, true);
mkdir($fixture . '/ext/uri/api/v1', 0755, true);
mkdir($fixture . '/ext/uri/article/(slug)', 0755, true);
mkdir($fixture . '/ext/uri/private', 0755, true);
mkdir($fixture . '/ext/uri/watchdog', 0755, true);
file_put_contents($fixture . '/ext/uri/index.tpl', 'home');
file_put_contents($fixture . '/ext/uri/about/index.tpl', '[#html#]');
file_put_contents($fixture . '/ext/uri/api/v1/index.tpl', 'api');
file_put_contents($fixture . '/ext/uri/article/(slug)/index.tpl', 'article');
file_put_contents($fixture . '/ext/uri/private/index.tpl', '[#access feature=view-private#][#html#]');
file_put_contents($fixture . '/ext/uri/watchdog/index.tpl', '[#watchdog#]');

$GLOBALS['SYSTEM'] = ['file_base' => $fixture . '/', 'request_uri' => '', 'uri_base' => '/', 'variables' => []];
function load_library($name) {}
function data_lookup($resource, $uuid, $field, $default = null) { return $default; }
require_once __DIR__ . '/../lib/env.php';
require_once __DIR__ . '/../lib/seo-page.php';
require_once __DIR__ . '/../lib/sitemap.php';

function sitemap_test_assert($condition, $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$routes = sitemap_static_route_files();
sitemap_test_assert(array_keys($routes) === ['', 'about'], 'Static route discovery included a private, non-HTML, or dynamic route.');
$xml = sitemap_xml([
    ['loc' => 'https://example.test/?a=1&b=2'],
    ['loc' => 'https://example.test/news/', 'lastmod' => '2026-08-11T00:00:00+00:00'],
]);
sitemap_test_assert(str_contains($xml, '&amp;'), 'XML URL escaping failed.');
sitemap_test_assert(str_contains($xml, '<lastmod>2026-08-11T00:00:00+00:00</lastmod>'), 'lastmod was omitted.');

$localized_records = [[
    'path' => ['en' => 'en/published', 'nl' => 'nl/unpublished'],
    'published' => ['en' => true, 'nl' => false],
]];
function data_read($resource) { return $GLOBALS['localized_records'] ?? []; }
$GLOBALS['localized_records'] = $localized_records;
$localized_entries = sitemap_resource_entries('pages', [
    'localized_path' => 'path',
    'localized_published' => 'published',
], ['_languages' => ['en', 'nl']]);
sitemap_test_assert(count($localized_entries) === 1, 'Localized sitemap publication was not enforced per language.');
sitemap_test_assert(str_ends_with($localized_entries[0]['loc'], '/en/published/'), 'Localized sitemap path was incorrect.');

$remove = function ($path) use (&$remove) {
    if (is_dir($path)) {
        foreach (array_diff(scandir($path), ['.', '..']) as $item) {
            $remove($path . '/' . $item);
        }
        rmdir($path);
    } else {
        unlink($path);
    }
};
$remove($fixture);
echo "Sitemap tests passed\n";
