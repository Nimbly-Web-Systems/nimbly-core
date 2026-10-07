<?php

// The admin update routes work on the project or its ext repository only,
// and a pull that has nothing to fetch is a success.

$root = dirname(__DIR__, 2) . '/';
$tmp = sys_get_temp_dir() . '/nimbly-admin-update-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700);
putenv('LC_ALL=C');
putenv('GIT_CONFIG_GLOBAL=/dev/null');
putenv('GIT_CONFIG_SYSTEM=/dev/null');

function admin_update_git(string $dir, string $command): string {
    exec('cd ' . escapeshellarg($dir) . ' && git -c user.name=test -c user.email=test@example.com ' . $command . ' 2>&1', $output, $code);
    if ($code !== 0) { throw new RuntimeException("git $command failed: " . implode("\n", $output)); }
    return trim(implode("\n", $output));
}

// One origin, cloned as the project, as its ext folder and as a folder outside the project.
mkdir($tmp . '/seed');
admin_update_git($tmp . '/seed', 'init -q -b master');
file_put_contents($tmp . '/seed/file.txt', "one\n");
admin_update_git($tmp . '/seed', 'add file.txt');
admin_update_git($tmp . '/seed', 'commit -q -m one');
admin_update_git($tmp, 'clone -q --bare seed origin.git');
admin_update_git($tmp . '/seed', 'remote add origin ' . escapeshellarg($tmp . '/origin.git'));
admin_update_git($tmp, 'clone -q origin.git site');
admin_update_git($tmp . '/site', 'clone -q ' . escapeshellarg($tmp . '/origin.git') . ' ext');
admin_update_git($tmp, 'clone -q origin.git outside');

$router = <<<'ROUTER'
<?php
$root = ROOT_PATH;
$GLOBALS['SYSTEM'] = ['file_base' => $root, 'env_paths' => ['core'], 'modules' => ['root' => '/'], 'uri_base' => '/', 'request_uri' => '', 'variables' => [], 'request_time' => time()];
$_SERVER['PEPPER'] = 'fixture-pepper';
require $root . 'core/lib/find.php';
// Libraries are found through file_base, so everything the routes need is loaded before it moves.
foreach (['run', 'get', 'request-input', 'url-key', 'util', 'data', 'app-build'] as $library) {
    load_library($library);
}
require $root . 'core/modules/admin/uri/api/v1/git-status/git-status.inc';
require $root . 'core/modules/admin/uri/api/v1/git-pull/git-pull.inc';
$GLOBALS['SYSTEM']['file_base'] = SITE_PATH;
$GLOBALS['SYSTEM']['data_base'] = SITE_PATH . 'ext/data';
echo $_GET['do'] === 'pull' ? git_pull_sc() : git_status_sc();
ROUTER;
file_put_contents($tmp . '/router.php', str_replace(['ROOT_PATH', 'SITE_PATH'], [var_export($root, true), var_export($tmp . '/site/', true)], $router));
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
$address = stream_socket_get_name($socket, false); fclose($socket);
$process = proc_open([PHP_BINARY, '-S', $address, $tmp . '/router.php'], [0 => ['pipe','r'], 1 => ['file', $tmp . '/server.log','a'], 2 => ['file', $tmp . '/server.log','a']], $pipes);
fclose($pipes[0]);

function admin_update_assert($condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}

function admin_update_fetch(string $address, string $do, string $dir = ''): array {
    $url = 'http://' . $address . '/?do=' . $do . ($dir === '' ? '' : '&dir=' . rawurlencode($dir));
    return (array)json_decode((string)file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 20]])), true);
}

try {
    $ready = false;
    for ($i = 0; $i < 50; $i++) {
        $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($connection) { fclose($connection); $ready = true; break; }
        usleep(20000);
    }
    admin_update_assert($ready, 'fixture server started');

    // Nothing to fetch is not an error.
    $response = admin_update_fetch($address, 'pull');
    admin_update_assert($response['error'] === false, 'pull with nothing new succeeds');
    $response = admin_update_fetch($address, 'pull', 'ext');
    admin_update_assert($response['error'] === false, 'ext pull with nothing new succeeds');

    // A new commit on the origin.
    file_put_contents($tmp . '/seed/file.txt', "two\n");
    admin_update_git($tmp . '/seed', 'commit -q -am two');
    admin_update_git($tmp . '/seed', 'push -q origin master');
    $new = admin_update_git($tmp . '/seed', 'rev-parse HEAD');
    $old = admin_update_git($tmp . '/outside', 'rev-parse HEAD');
    admin_update_assert($new !== $old, 'origin moved ahead');

    $response = admin_update_fetch($address, 'status');
    admin_update_assert($response['error'] === false && $response['status'] === 'behind' && (int)$response['updates'] === 1, 'status reports one update');

    // A folder outside the project is never touched; the request lands on the project itself.
    $response = admin_update_fetch($address, 'pull', '../outside');
    admin_update_assert(admin_update_git($tmp . '/outside', 'rev-parse HEAD') === $old, 'folder outside the project is not pulled');
    admin_update_assert($response['error'] === false && admin_update_git($tmp . '/site', 'rev-parse HEAD') === $new, 'project is pulled');
    $response = admin_update_fetch($address, 'status', '../outside');
    admin_update_assert($response['dir'] === '' && $response['status'] === 'up to date', 'status ignores a folder outside the project');

    $response = admin_update_fetch($address, 'pull', 'ext');
    admin_update_assert($response['error'] === false && admin_update_git($tmp . '/site/ext', 'rev-parse HEAD') === $new, 'ext is pulled');

    // A pull that fails is an error.
    admin_update_git($tmp . '/site/ext', 'remote set-url origin ' . escapeshellarg($tmp . '/gone.git'));
    $response = admin_update_fetch($address, 'pull', 'ext');
    admin_update_assert($response['error'] === true && $response['status'] !== '', 'failed pull is an error with git output');

    echo "admin update tests passed\n";
} catch (Throwable $e) {
    fwrite(STDERR, (string)@file_get_contents($tmp . '/server.log'));
    throw $e;
} finally {
    proc_terminate($process);
    proc_close($process);
    exec('rm -rf ' . escapeshellarg($tmp));
}
