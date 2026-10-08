<?php

// The bot tripwire: one fixed address per site, listed in robots.txt, linked
// out of sight on a page, and answered with 418; any other address is not.

$root = dirname(__DIR__, 2) . '/';
$tmp = sys_get_temp_dir() . '/nimbly-tripwire-' . bin2hex(random_bytes(6));
mkdir($tmp . '/data', 0700, true);
file_put_contents($tmp . '/link.tpl', "<body>[#bot-tripwire-link#]</body>\n");
$router = <<<'ROUTER'
<?php
$root = ROOT_PATH;
$GLOBALS['SYSTEM'] = ['file_base' => $root, 'data_base' => TMP_PATH . '/data', 'env_paths' => ['core'], 'modules' => ['root' => '/'], 'uri_base' => '/', 'request_uri' => '', 'variables' => [], 'request_time' => time()];
if (($_GET['pepper'] ?? '') !== '') {
    $_SERVER['PEPPER'] = $_GET['pepper'];
}
require $root . 'core/lib/find.php';
load_library('run');
if ($_GET['t'] === 'robots') {
    run($root . 'core/uri/robots.txt/index.tpl');
} elseif ($_GET['t'] === 'link') {
    run(TMP_PATH . '/link.tpl');
} else {
    load_library('bot-tripwire');
    if (!bot_tripwire_answer($_GET['u'])) {
        http_response_code(404);
        echo 'not found';
    }
}
ROUTER;
file_put_contents($tmp . '/router.php', str_replace(['ROOT_PATH', 'TMP_PATH'], [var_export($root, true), var_export($tmp, true)], $router));
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
$address = stream_socket_get_name($socket, false); fclose($socket);
$process = proc_open([PHP_BINARY, '-S', $address, $tmp . '/router.php'], [0 => ['pipe','r'], 1 => ['file', $tmp . '/server.log','a'], 2 => ['file', $tmp . '/server.log','a']], $pipes);
fclose($pipes[0]);

function tripwire_assert($condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}

// Returns the status code, lower-cased response headers and body.
function tripwire_fetch(string $address, array $query): array {
    $http = ['timeout' => 5, 'ignore_errors' => true];
    $body = (string)file_get_contents('http://' . $address . '/?' . http_build_query($query), false, stream_context_create(['http' => $http]));
    $headers = [];
    foreach (array_slice($http_response_header, 1) as $line) {
        [$name, $value] = explode(':', $line, 2);
        $headers[strtolower($name)] = trim($value);
    }
    return ['status' => (int)explode(' ', $http_response_header[0])[1], 'headers' => $headers, 'body' => $body];
}

try {
    require $root . 'core/lib/bot-tripwire.php';
    $_SERVER['PEPPER'] = 'fixture-pepper';
    $path = bot_tripwire_path();
    tripwire_assert(preg_match('/^nb-[0-9a-f]{12}$/', $path) === 1, 'path is nb- and twelve hex characters');
    tripwire_assert($path === bot_tripwire_path(), 'a site keeps its path');
    tripwire_assert(strpos($path, 'fixture') === false, 'path does not show the secret');
    $_SERVER['PEPPER'] = 'another-pepper';
    tripwire_assert($path !== bot_tripwire_path(), 'another site has another path');
    unset($_SERVER['PEPPER']);
    tripwire_assert(bot_tripwire_path() === '', 'no path without a secret');

    $ready = false;
    for ($i = 0; $i < 50; $i++) {
        $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($connection) { fclose($connection); $ready = true; break; }
        usleep(20000);
    }
    tripwire_assert($ready, 'fixture server started');
    $site = ['pepper' => 'fixture-pepper'];

    $response = tripwire_fetch($address, $site + ['t' => 'robots']);
    tripwire_assert($response['status'] === 200, 'robots.txt answers');
    tripwire_assert(strpos($response['body'], "Disallow: /{$path}/\n") !== false, 'robots.txt forbids the path');
    tripwire_assert(strpos($response['body'], "Disallow: /nb-admin/\n") !== false, 'robots.txt keeps its other lines');
    $response = tripwire_fetch($address, ['t' => 'robots']);
    tripwire_assert(strpos($response['body'], 'Disallow: /nb-') === strrpos($response['body'], 'Disallow: /nb-'), 'no tripwire line without a secret');

    $response = tripwire_fetch($address, $site + ['t' => 'link']);
    tripwire_assert(strpos($response['body'], '<a href="/' . $path . '/" rel="nofollow" tabindex="-1" aria-hidden="true" hidden></a>') !== false, 'page links to the path out of sight');
    $response = tripwire_fetch($address, ['t' => 'link']);
    tripwire_assert(trim($response['body']) === '<body></body>', 'no link without a secret');

    foreach ([$path, $path . '/', '/' . $path] as $uri) {
        $response = tripwire_fetch($address, $site + ['t' => 'trap', 'u' => $uri]);
        tripwire_assert($response['status'] === 418, 'the path answers 418: ' . $uri);
        tripwire_assert(($response['headers']['cache-control'] ?? '') === 'no-store', 'the answer is not cached');
        tripwire_assert(!isset($response['headers']['set-cookie']), 'no session is started');
    }
    foreach (['nb-admin', 'nb-000000000000', $path . '/more', 'about'] as $uri) {
        $response = tripwire_fetch($address, $site + ['t' => 'trap', 'u' => $uri]);
        tripwire_assert($response['status'] === 404, 'another path is not the tripwire: ' . $uri);
    }
    $response = tripwire_fetch($address, ['t' => 'trap', 'u' => 'nb-']);
    tripwire_assert($response['status'] === 404, 'nothing answers 418 without a secret');

    echo "bot tripwire tests passed\n";
} finally {
    proc_terminate($process);
    proc_close($process);
    exec('rm -rf ' . escapeshellarg($tmp));
}
