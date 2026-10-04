<?php

// Request values (GET, cookie, POST) must come back from shortcodes as literal
// text, while shortcodes nested in template text keep working.

$root = dirname(__DIR__, 2) . '/';
$tmp = sys_get_temp_dir() . '/nimbly-request-input-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700);
$router = <<<'ROUTER'
<?php
$root = ROOT_PATH;
$GLOBALS['SYSTEM'] = ['file_base' => $root, 'env_paths' => ['core'], 'modules' => ['root' => '/'], 'uri_base' => '/', 'request_uri' => '', 'variables' => ['y' => 'from-variable'], 'request_time' => time()];
$_SERVER['PEPPER'] = 'fixture-pepper';
require $root . 'core/lib/find.php';
load_library('run');
function probe_sc($params) { return 'EXECUTED'; }
function wrap_sc($params) { return '<' . ($params['x'] ?? '') . '>'; }
$templates = [
    'get' => '[#get q#]',
    'nested-get' => '[#wrap x=[#get q#]#]',
    'nested-set' => ['[#set x=[#get y#]#]', '[#get x#]'],
    'nested-template' => '[#wrap x=[#probe#]#]',
    'cookie' => '[#get c#]',
    'sticky' => '[#sticky field#]',
    'nested-sticky' => '[#wrap x=[#sticky field#]#]',
    'form-key' => '[#form-key#]',
];
header('Content-Type: text/plain');
foreach ((array)$templates[$_GET['t']] as $template) {
    run_template($template);
}
ROUTER;
file_put_contents($tmp . '/router.php', str_replace('ROOT_PATH', var_export($root, true), $router));
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
$address = stream_socket_get_name($socket, false); fclose($socket);
$process = proc_open([PHP_BINARY, '-S', $address, $tmp . '/router.php'], [0 => ['pipe','r'], 1 => ['file', $tmp . '/server.log','a'], 2 => ['file', $tmp . '/server.log','a']], $pipes);
fclose($pipes[0]);

function request_input_assert($condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}

function request_input_fetch(string $address, string $template, array $query = [], string $cookie = '', array $post = []): string {
    $http = ['timeout' => 5, 'header' => $cookie ? "Cookie: $cookie\r\n" : ''];
    if ($post) {
        $http['method'] = 'POST';
        $http['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $http['content'] = http_build_query($post);
    }
    $url = 'http://' . $address . '/?' . http_build_query(['t' => $template] + $query);
    return (string)file_get_contents($url, false, stream_context_create(['http' => $http]));
}

$attack = 'ZZ[#probe#]ZZ';
$literal = 'ZZ&#91;#probe#&#93;ZZ';

try {
    $ready = false;
    for ($i = 0; $i < 50; $i++) {
        $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($connection) { fclose($connection); $ready = true; break; }
        usleep(20000);
    }
    request_input_assert($ready, 'fixture server started');

    // Template text is unchanged: nested shortcodes still run.
    request_input_assert(request_input_fetch($address, 'nested-template') === '<EXECUTED>', 'nested template shortcode runs');
    request_input_assert(request_input_fetch($address, 'nested-set') === 'from-variable', 'set with a nested get works');

    // Ordinary request values are unchanged.
    request_input_assert(request_input_fetch($address, 'get', ['q' => 'hello world [1]']) === 'hello world [1]', 'normal query value is unchanged');
    request_input_assert(request_input_fetch($address, 'nested-get', ['q' => 'hello']) === '<hello>', 'normal query value in a nested shortcode');
    request_input_assert(request_input_fetch($address, 'sticky', [], '', ['field' => 'you@example.test']) === 'you@example.test', 'normal posted value is unchanged');

    // A shortcode in a request value comes back as literal text.
    request_input_assert(request_input_fetch($address, 'get', ['q' => $attack]) === $literal, 'query value is literal');
    request_input_assert(request_input_fetch($address, 'nested-get', ['q' => $attack]) === "<$literal>", 'query value is literal inside a nested shortcode');
    request_input_assert(request_input_fetch($address, 'cookie', [], 'c=' . rawurlencode($attack)) === $literal, 'cookie value is literal');
    request_input_assert(request_input_fetch($address, 'cookie', [], 'c=' . rawurlencode('<b>')) === '&#60;b&#62;', 'cookie value is HTML-escaped like a query value');
    request_input_assert(request_input_fetch($address, 'sticky', [], '', ['field' => $attack]) === $literal, 'posted value is literal');
    request_input_assert(request_input_fetch($address, 'nested-sticky', [], '', ['field' => $attack]) === "<$literal>", 'posted value is literal inside a nested shortcode');
    request_input_assert(request_input_fetch($address, 'sticky', ['field' => $attack]) === $literal, 'sticky query fallback is literal');
    $form_key = request_input_fetch($address, 'form-key', [], 'key=' . rawurlencode($attack));
    request_input_assert(!str_contains($form_key, 'EXECUTED') && str_contains($form_key, $literal), 'form key cookie is literal');

    require $root . 'core/lib/request-input.php';
    request_input_assert(request_input_escape(['a' => $attack, 'b' => 3]) === ['a' => $literal, 'b' => 3], 'arrays are escaped per value');

    echo "request input shortcode tests passed\n";
} finally {
    proc_terminate($process);
    proc_close($process);
    exec('rm -rf ' . escapeshellarg($tmp));
}
