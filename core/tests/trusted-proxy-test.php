<?php

function env($name, $default = '') { return $GLOBALS['proxy_test_env'][$name] ?? $default; }

require __DIR__ . '/../lib/trusted-proxy.php';

function proxy_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

/** The request as PHP sees it after the proxy check. */
function proxy_test_request(string $trusted, array $server): array
{
    $GLOBALS['proxy_test_env'] = ['TRUSTED_PROXIES' => $trusted];
    $_SERVER = $server + ['SERVER_PORT' => '80'];
    trusted_proxy_apply();
    return $_SERVER;
}

$forwarded = [
    'REMOTE_ADDR' => '10.0.0.5',
    'HTTP_X_FORWARDED_PROTO' => 'https',
    'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
];

// No setting: the headers mean nothing.
$server = proxy_test_request('', $forwarded);
proxy_assert($server['REMOTE_ADDR'] === '10.0.0.5', 'without the setting the address stays');
proxy_assert(empty($server['HTTPS']) && $server['SERVER_PORT'] === '80', 'without the setting the scheme and port stay');

// A request that does not come from the proxy.
$server = proxy_test_request('10.0.0.0/8', ['REMOTE_ADDR' => '198.51.100.7'] + $forwarded);
proxy_assert($server['REMOTE_ADDR'] === '198.51.100.7', 'headers from an unknown address are ignored');
proxy_assert(empty($server['HTTPS']), 'an unknown address cannot claim https');

// From the proxy.
$server = proxy_test_request('10.0.0.0/8', $forwarded);
proxy_assert($server['REMOTE_ADDR'] === '203.0.113.9', 'the visitor address comes from the header');
proxy_assert($server['HTTPS'] === 'on' && $server['SERVER_PORT'] === '443', 'https and port 443 come from the header');

$server = proxy_test_request('10.0.0.5', ['HTTPS' => 'on', 'SERVER_PORT' => '8443', 'HTTP_X_FORWARDED_PROTO' => 'http'] + $forwarded);
proxy_assert(empty($server['HTTPS']) && $server['SERVER_PORT'] === '80', 'a forwarded http request is http');

$server = proxy_test_request('10.0.0.5', ['HTTP_X_FORWARDED_PORT' => '8443'] + $forwarded);
proxy_assert($server['SERVER_PORT'] === '8443', 'a forwarded port is used');

$server = proxy_test_request('10.0.0.5', ['REMOTE_ADDR' => '10.0.0.5', 'SERVER_PORT' => '8080']);
proxy_assert($server['REMOTE_ADDR'] === '10.0.0.5' && $server['SERVER_PORT'] === '8080' && empty($server['HTTPS']), 'a proxy that sends no headers changes nothing');

// The client can put anything in front; only what our proxies added counts.
$server = proxy_test_request('10.0.0.0/8', ['HTTP_X_FORWARDED_FOR' => '1.2.3.4, 203.0.113.9, 10.0.0.8'] + $forwarded);
proxy_assert($server['REMOTE_ADDR'] === '203.0.113.9', 'the address is the last one that is not a proxy');

$server = proxy_test_request('10.0.0.0/8', ['HTTP_X_FORWARDED_FOR' => '1.2.3.4, nonsense'] + $forwarded);
proxy_assert($server['REMOTE_ADDR'] === '10.0.0.5', 'a value that is no address is not used');

$server = proxy_test_request('10.0.0.0/8', ['HTTP_X_FORWARDED_FOR' => '10.0.0.9, 10.0.0.8'] + $forwarded);
proxy_assert($server['REMOTE_ADDR'] === '10.0.0.9', 'a chain of proxies only ends at the first of them');

// Ranges.
$server = proxy_test_request('192.168.1.0/28', ['REMOTE_ADDR' => '192.168.1.16'] + $forwarded);
proxy_assert($server['REMOTE_ADDR'] === '192.168.1.16', 'an address just outside the range is not a proxy');
$server = proxy_test_request('192.168.1.0/28', ['REMOTE_ADDR' => '192.168.1.15'] + $forwarded);
proxy_assert($server['REMOTE_ADDR'] === '203.0.113.9', 'an address inside the range is a proxy');

$server = proxy_test_request('10.0.0.1, fd00:1::/32', ['REMOTE_ADDR' => 'fd00:1:2::7', 'HTTP_X_FORWARDED_FOR' => '2001:db8::1'] + $forwarded);
proxy_assert($server['REMOTE_ADDR'] === '2001:db8::1', 'IPv6 proxies and visitors work');
$server = proxy_test_request('fd00:1::/32', ['REMOTE_ADDR' => 'fd00:2::7'] + $forwarded);
proxy_assert($server['REMOTE_ADDR'] === 'fd00:2::7', 'an IPv6 address outside the range is not a proxy');

// A list nobody can match trusts nobody.
foreach (['nonsense', '10.0.0.0/99', '/8', ','] as $broken) {
    $server = proxy_test_request($broken, $forwarded);
    proxy_assert($server['REMOTE_ADDR'] === '10.0.0.5', "a broken list ({$broken}) trusts nobody");
}

echo "trusted proxy test passed\n";
