<?php

// [#http-header type#] sends only the header for that type; cache headers
// are sent when a second parameter asks for them.

$root = dirname(__DIR__, 2) . '/';
$tmp = sys_get_temp_dir() . '/nimbly-http-header-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700);
$router = <<<'ROUTER'
<?php
$root = ROOT_PATH;
$GLOBALS['SYSTEM'] = ['file_base' => $root, 'env_paths' => ['core'], 'modules' => ['root' => '/'], 'uri_base' => '/', 'request_uri' => '', 'variables' => [], 'request_time' => time()];
$_SERVER['PEPPER'] = 'fixture-pepper';
require $root . 'core/lib/find.php';
load_library('run');
$templates = [
    'not-found' => '[#http-header 404#]body',
    'css' => '[#http-header css#]body',
    'css-cached' => '[#http-header css cached#]body',
    'not-found-cached' => '[#http-header 404 cached#]body',
];
run_template($templates[$_GET['t']]);
ROUTER;
file_put_contents($tmp . '/router.php', str_replace('ROOT_PATH', var_export($root, true), $router));
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
$address = stream_socket_get_name($socket, false); fclose($socket);
$process = proc_open([PHP_BINARY, '-S', $address, $tmp . '/router.php'], [0 => ['pipe','r'], 1 => ['file', $tmp . '/server.log','a'], 2 => ['file', $tmp . '/server.log','a']], $pipes);
fclose($pipes[0]);

function http_header_assert($condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}

// Returns the status code, lower-cased response headers and body.
function http_header_fetch(string $address, string $template, string $request_header = ''): array {
    $http = ['timeout' => 5, 'ignore_errors' => true, 'header' => $request_header];
    $body = (string)file_get_contents('http://' . $address . '/?t=' . $template, false, stream_context_create(['http' => $http]));
    $headers = [];
    foreach (array_slice($http_response_header, 1) as $line) {
        [$name, $value] = explode(':', $line, 2);
        $headers[strtolower($name)] = trim($value);
    }
    return ['status' => (int)explode(' ', $http_response_header[0])[1], 'headers' => $headers, 'body' => $body];
}

try {
    $ready = false;
    for ($i = 0; $i < 50; $i++) {
        $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($connection) { fclose($connection); $ready = true; break; }
        usleep(20000);
    }
    http_header_assert($ready, 'fixture server started');

    // One parameter: the type's header and nothing about caching.
    $response = http_header_fetch($address, 'not-found');
    http_header_assert($response['status'] === 404 && $response['body'] === 'body', 'not found status is sent');
    foreach (['expires', 'cache-control', 'last-modified'] as $name) {
        http_header_assert(!isset($response['headers'][$name]), "not found sends no $name header");
    }
    $response = http_header_fetch($address, 'css');
    http_header_assert($response['status'] === 200 && str_starts_with($response['headers']['content-type'], 'text/css'), 'css content type is sent');
    http_header_assert(!isset($response['headers']['expires']) && !isset($response['headers']['cache-control']), 'css sends no cache headers on its own');

    // Two parameters: cache headers as before.
    $response = http_header_fetch($address, 'css-cached');
    http_header_assert($response['status'] === 200 && str_starts_with($response['headers']['content-type'], 'text/css'), 'cached css content type is sent');
    http_header_assert(($response['headers']['cache-control'] ?? '') === 'private', 'cached css sends cache control');
    http_header_assert(strtotime($response['headers']['expires'] ?? '') > time() + 300000000, 'cached css expires far ahead');
    http_header_assert(isset($response['headers']['last-modified']), 'cached css sends last modified');
    $response = http_header_fetch($address, 'css-cached', 'If-Modified-Since: ' . gmdate('D, d M Y H:i:s', time() + 60) . " GMT\r\n");
    http_header_assert($response['status'] === 304 && $response['body'] === '', 'cached css answers not modified');
    $response = http_header_fetch($address, 'not-found-cached');
    http_header_assert($response['status'] === 404 && isset($response['headers']['expires']), 'second parameter works for any type');

    echo "http header tests passed\n";
} finally {
    proc_terminate($process);
    proc_close($process);
    exec('rm -rf ' . escapeshellarg($tmp));
}
