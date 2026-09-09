<?php

$root = dirname(__DIR__, 2) . '/';
$tmp = sys_get_temp_dir() . '/nimbly-session-http-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700);
mkdir($tmp . '/sessions', 0700);
$router = <<<'ROUTER'
<?php
$root = ROOT_PATH;
$GLOBALS['SYSTEM'] = ['file_base' => $root, 'env_paths' => ['ext','core'], 'modules' => ['root'=>'/'], 'uri_base' => '/app/', 'request_time' => time()];
$_SERVER['PEPPER'] = 'fixture-pepper';
require $root . 'core/lib/find.php';
load_library('session');
$GLOBALS['SYSTEM']['session_path'] = __DIR__ . '/sessions';
$action = $_GET['action'] ?? 'read';
if ($action === 'login') {
    session_sc();
    $_SESSION['username'] = 'fixture@example.test';
    $_SESSION['user_uuid'] = 'fixture';
    $_SESSION['roles'] = ['admin' => true];
    session_login_completed();
} elseif ($action === 'logout') {
    session_sc();
    session_initialize();
} else {
    session_resume();
}
header('Content-Type: application/json');
echo json_encode(['authenticated' => session_authenticated($_SESSION ?? []), 'session' => $_SESSION ?? [], 'id' => session_id()]);
ROUTER;
file_put_contents($tmp . '/router.php', str_replace('ROOT_PATH', var_export($root, true), $router));
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
$address = stream_socket_get_name($socket, false); fclose($socket);
$process = proc_open([PHP_BINARY, '-S', $address, $tmp . '/router.php'], [0 => ['pipe','r'], 1 => ['file', $tmp . '/server.log','a'], 2 => ['file', $tmp . '/server.log','a']], $pipes);
fclose($pipes[0]);
function session_http_assert($condition, $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
function session_http_request(string $address, string $action, string $cookie = ''): array {
    $context = stream_context_create(['http' => ['header' => $cookie ? "Cookie: $cookie\r\n" : '', 'timeout' => 5]]);
    $body = file_get_contents('http://' . $address . '/app/?action=' . $action, false, $context);
    $cookies = array_values(array_filter($http_response_header, fn($line) => str_starts_with(strtolower($line), 'set-cookie:')));
    return [json_decode($body, true, 512, JSON_THROW_ON_ERROR), $cookies];
}
try {
    $ready = false;
    for ($i = 0; $i < 50; $i++) {
        $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($connection) { fclose($connection); $ready = true; break; }
        usleep(20000);
    }
    session_http_assert($ready, 'fixture server started');
    [$anonymous, $cookies] = session_http_request($address, 'read');
    session_http_assert(!$cookies && !$anonymous['session'] && count(glob($tmp . '/sessions/*')) === 0, 'public read creates no session or cookie');
    [$login, $cookies] = session_http_request($address, 'login');
    $last_cookie = end($cookies);
    session_http_assert($login['authenticated'], 'login succeeds');
    session_http_assert(str_contains(strtolower($last_cookie), 'httponly') && str_contains($last_cookie, 'SameSite=Lax') && str_contains($last_cookie, 'path=/app/') && str_contains($last_cookie, 'Max-Age=604800'), 'persistent scoped admin cookie');
    $id = $login['id'];
    $file = $tmp . '/sessions/sess_' . $id;
    $raw = file_get_contents($file);
    $old = time() - 86400 * 90;
    $raw = preg_replace('/created\|i:\d+;/', 'created|i:' . $old . ';', $raw);
    $raw = preg_replace('/rotated\|i:\d+;/', 'rotated|i:' . $old . ';', $raw);
    file_put_contents($file, $raw);
    [$resumed, $cookies] = session_http_request($address, 'read', 'nb_session_id=' . $id);
    session_http_assert($resumed['authenticated'] && $resumed['id'] !== $id && $resumed['session']['created'] === $old, 'old active login survives while ID rotates');
    session_http_assert(!file_exists($file), 'old session ID removed');
    $id = $resumed['id'];
    $file = $tmp . '/sessions/sess_' . $id;
    $raw = preg_replace('/modified\|i:\d+;/', 'modified|i:' . (time() - 604801) . ';', file_get_contents($file));
    file_put_contents($file, $raw);
    [$expired] = session_http_request($address, 'read', 'nb_session_id=' . $id);
    session_http_assert(!$expired['authenticated'], 'idle expiry enforced server-side');
    [$login] = session_http_request($address, 'login');
    [$logout, $cookies] = session_http_request($address, 'logout', 'nb_session_id=' . $login['id']);
    session_http_assert(!$logout['authenticated'] && !file_exists($tmp . '/sessions/sess_' . $login['id']), 'logout revokes login');
    echo "session HTTP tests passed\n";
} finally {
    proc_terminate($process); proc_close($process);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
        if ($entry->isDir()) { rmdir($entry->getPathname()); } else { unlink($entry->getPathname()); }
    }
    rmdir($tmp);
}
