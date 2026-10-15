<?php

require_once __DIR__ . '/../lib/subdomain.php';

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\n  expected: " . var_export($expected, true)
            . "\n  actual:   " . var_export($actual, true) . "\n");
        exit(1);
    }
}

// host → subdomain label
assert_same('docs', subdomain_label('docs.example.com'), 'reads the first label');
assert_same('docs', subdomain_label('Docs.Example.com:8081'), 'ignores case and port');
assert_same('docs', subdomain_label('docs.localhost'), 'works on a local name');
assert_same('', subdomain_label('localhost'), 'a bare name has no subdomain');
assert_same('', subdomain_label('127.0.0.1:8081'), 'an IPv4 address has no subdomain');
assert_same('', subdomain_label('[::1]:8081'), 'an IPv6 address has no subdomain');
assert_same('', subdomain_label(''), 'no host, no subdomain');
assert_same('', subdomain_label('../etc.example.com'), 'refuses a label that is not a host label');
assert_same('', subdomain_label('a)(b.example.com'), 'refuses brackets in a label');

// folder paths
assert_same(true, subdomain_is_folder_path('(subdomain-docs)/guide'), 'recognises a subdomain folder path');
assert_same(false, subdomain_is_folder_path('guide/(slug)'), 'a normal route is not a subdomain folder path');
assert_same('(subdomain-docs)', subdomain_uri('(subdomain-docs)', ''), 'the home page is the folder itself');
assert_same('(subdomain-docs)/guide/start', subdomain_uri('(subdomain-docs)', 'guide/start'), 'puts the folder in front');

// stored routes
assert_same(true, subdomain_route_allowed('article/(slug)', ''), 'a normal route answers on the main site');
assert_same(true, subdomain_route_allowed('article/(slug)', '(subdomain-docs)'), 'a normal route answers on a subdomain');
assert_same(true, subdomain_route_allowed('(subdomain-docs)/article/(slug)', '(subdomain-docs)'), 'a subdomain route answers on its subdomain');
assert_same(false, subdomain_route_allowed('(subdomain-docs)/article/(slug)', ''), 'a subdomain route does not answer on the main site');
assert_same(false, subdomain_route_allowed('(subdomain-docs)/article/(slug)', '(subdomain-wiki)'), 'a subdomain route does not answer on another subdomain');
assert_same(['article', '(slug)'], subdomain_route_parts(['(subdomain-docs)', 'article', '(slug)']), 'matching leaves the folder out');
assert_same(['article', '(slug)'], subdomain_route_parts(['article', '(slug)']), 'a normal route keeps its parts');

// addresses of the main site and its subdomains
assert_same('example.com', subdomain_sibling_host('docs.example.com', true, ''), 'a subdomain finds the main site');
assert_same('wiki.example.com', subdomain_sibling_host('docs.example.com', true, 'wiki'), 'a subdomain finds another subdomain');
assert_same('docs.example.com', subdomain_sibling_host('example.com', false, 'docs'), 'the main site finds a subdomain');
assert_same('example.com', subdomain_sibling_host('example.com', false, ''), 'the main site finds itself');
assert_same('docs.localhost:8081', subdomain_sibling_host('localhost:8081', false, 'docs'), 'a port is kept');
assert_same('localhost:8081', subdomain_sibling_host('docs.localhost:8081', true, ''), 'a port is kept on the way back');

// the login cookie is shared with subdomains only where the site has subdomain folders
assert_same('', subdomain_cookie_domain('example.com', false, false), 'a site without subdomain folders keeps its cookie on the host');
assert_same('example.com', subdomain_cookie_domain('example.com:8081', true, false), 'the main site shares its cookie');
assert_same('example.com', subdomain_cookie_domain('docs.example.com', true, true), 'a subdomain uses the main site\'s cookie');
assert_same('', subdomain_cookie_domain('localhost', true, false), 'a single-label host keeps its cookie');
assert_same('', subdomain_cookie_domain('docs.localhost', true, true), 'a subdomain of a single-label host keeps its cookie');
assert_same('', subdomain_cookie_domain('127.0.0.1', true, false), 'an IP address keeps its cookie');

// SUBDOMAIN_HOST: a site in a subfolder links to its subdomains on the named host
if (!function_exists('load_library')) {
    function load_library($name) {}
}
if (!function_exists('find_exists')) {
    function find_exists($base, $path) { return false; }
}
if (!function_exists('env')) {
    function env($key, $default = '') { return $GLOBALS['test_env'][$key] ?? $default; }
}
require_once __DIR__ . '/../lib/subdomain-url.php';
$_SERVER['HTTP_HOST'] = 'staging.example.com';
$GLOBALS['SYSTEM']['uri_base'] = '/site/';
$GLOBALS['SYSTEM']['file_base'] = sys_get_temp_dir() . '/';
$GLOBALS['test_env'] = ['SUBDOMAIN_HOST' => 'example.com'];
assert_same('https://docs.example.com', subdomain_url_sc(['docs']), 'a named subdomain uses SUBDOMAIN_HOST');
$GLOBALS['test_env'] = [];
assert_same('http://docs.staging.example.com/site', subdomain_url_sc(['docs']), 'without SUBDOMAIN_HOST the current host is used');

echo "subdomain tests passed\n";
