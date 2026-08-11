<?php

$fixture = sys_get_temp_dir() . '/nimbly-sitemap-' . bin2hex(random_bytes(5));
mkdir($fixture . '/ext/uri/about', 0755, true);
mkdir($fixture . '/ext/uri/api/v1', 0755, true);
mkdir($fixture . '/ext/uri/article/(slug)', 0755, true);
file_put_contents($fixture . '/ext/uri/index.tpl', 'home');
file_put_contents($fixture . '/ext/uri/about/index.tpl', 'about');
file_put_contents($fixture . '/ext/uri/api/v1/index.tpl', 'api');
file_put_contents($fixture . '/ext/uri/article/(slug)/index.tpl', 'article');

$GLOBALS['SYSTEM'] = ['file_base' => $fixture . '/', 'request_uri' => '', 'uri_base' => '/', 'variables' => []];
function load_library($name) {}
function data_lookup($resource, $uuid, $field, $default = null) { return $default; }
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
sitemap_test_assert(array_keys($routes) === ['', 'about'], 'Static route discovery included a private or dynamic route.');
$xml = sitemap_xml([
    ['loc' => 'https://example.test/?a=1&b=2'],
    ['loc' => 'https://example.test/news/', 'lastmod' => '2026-08-11T00:00:00+00:00'],
]);
sitemap_test_assert(str_contains($xml, '&amp;'), 'XML URL escaping failed.');
sitemap_test_assert(str_contains($xml, '<lastmod>2026-08-11T00:00:00+00:00</lastmod>'), 'lastmod was omitted.');

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
