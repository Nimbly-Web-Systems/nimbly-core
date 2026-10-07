<?php

// The health route answers 200 "ok" when the data folder can be written
// and 503 when it cannot; it is never cached and starts no session.

$root = dirname(__DIR__, 2) . '/';
$tmp = sys_get_temp_dir() . '/nimbly-health-' . bin2hex(random_bytes(6));
mkdir($tmp . '/data', 0700, true);
$router = <<<'ROUTER'
<?php
$root = ROOT_PATH;
$data = ['ok' => TMP_PATH . '/data', 'missing' => TMP_PATH . '/missing'];
$GLOBALS['SYSTEM'] = ['file_base' => $root, 'data_base' => $data[$_GET['d']], 'env_paths' => ['core'], 'modules' => ['root' => '/'], 'uri_base' => '/', 'request_uri' => '', 'variables' => [], 'request_time' => time()];
$_SERVER['PEPPER'] = 'fixture-pepper';
require $root . 'core/lib/find.php';
load_library('run');
run($root . 'core/uri/health/index.tpl');
ROUTER;
file_put_contents($tmp . '/router.php', str_replace(['ROOT_PATH', 'TMP_PATH'], [var_export($root, true), var_export($tmp, true)], $router));
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
$address = stream_socket_get_name($socket, false); fclose($socket);
$process = proc_open([PHP_BINARY, '-S', $address, $tmp . '/router.php'], [0 => ['pipe','r'], 1 => ['file', $tmp . '/server.log','a'], 2 => ['file', $tmp . '/server.log','a']], $pipes);
fclose($pipes[0]);

function health_assert($condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}

// Returns the status code, lower-cased response headers and body.
function health_fetch(string $address, string $data): array {
    $http = ['timeout' => 5, 'ignore_errors' => true];
    $body = (string)file_get_contents('http://' . $address . '/?d=' . $data, false, stream_context_create(['http' => $http]));
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
    health_assert($ready, 'fixture server started');

    $response = health_fetch($address, 'ok');
    health_assert($response['status'] === 200 && trim($response['body']) === 'ok', 'writable data folder answers 200 ok');
    health_assert(str_starts_with($response['headers']['content-type'] ?? '', 'text/plain'), 'answer is plain text');
    health_assert(($response['headers']['cache-control'] ?? '') === 'no-store', 'answer is not cached');
    health_assert(!isset($response['headers']['set-cookie']), 'no session is started');

    $response = health_fetch($address, 'missing');
    health_assert($response['status'] === 503 && trim($response['body']) === 'unavailable', 'missing data folder answers 503');
    health_assert(($response['headers']['cache-control'] ?? '') === 'no-store', 'failure is not cached');

    echo "health tests passed\n";
} finally {
    proc_terminate($process);
    proc_close($process);
    exec('rm -rf ' . escapeshellarg($tmp));
}
