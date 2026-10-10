<?php

// [#include file#] prints a file inside the project and nothing for a path
// that resolves outside it or does not exist.

$root = dirname(__DIR__, 2) . '/';
$GLOBALS['SYSTEM'] = ['file_base' => $root, 'env_paths' => ['core'], 'modules' => ['root' => '/'], 'uri_base' => '/', 'request_uri' => '', 'variables' => [], 'request_time' => time()];
require $root . 'core/lib/find.php';
load_library('run');

function include_test_assert($condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}

function include_test_render(string $path): string {
    ob_start();
    run_template('[#include file=' . $path . '#]');
    return (string)ob_get_clean();
}

$inside = $root . 'core/tests/fixtures/include-test.txt';
$outside = sys_get_temp_dir() . '/nimbly-include-' . bin2hex(random_bytes(6)) . '.txt';
file_put_contents($outside, 'outside');

try {
    include_test_assert(trim(include_test_render($inside)) === 'inside', 'a file in the project is included');
    include_test_assert(trim(include_test_render('[#base-path#]/core/tests/fixtures/include-test.txt')) === 'inside', 'base-path with a double slash is included');
    include_test_assert(include_test_render($outside) === '', 'a file outside the project is not included');
    include_test_assert(include_test_render($root . 'core/' . str_repeat('../', 30) . ltrim($outside, '/')) === '', 'a path that climbs out of the project is not included');
    include_test_assert(include_test_render($root . 'core/tests/fixtures/missing.txt') === '', 'a missing file prints nothing');
    include_test_assert(include_test_render($root . 'core/tests/fixtures') === '', 'a directory prints nothing');

    // site data is never included, a template next to it is
    $project = sys_get_temp_dir() . '/nimbly-include-project-' . bin2hex(random_bytes(6));
    mkdir($project . '/ext/data/.files', 0750, true);
    mkdir($project . '/ext/tpl', 0750, true);
    file_put_contents($project . '/ext/data/.files/upload', 'uploaded');
    file_put_contents($project . '/ext/tpl/part.txt', 'template');
    $GLOBALS['SYSTEM']['file_base'] = $project . '/';
    include_test_assert(include_test_render($project . '/ext/data/.files/upload') === '', 'a file in the site data is not included');
    include_test_assert(include_test_render($project . '/ext/tpl/../data/.files/upload') === '', 'a roundabout path into the site data is not included');
    include_test_assert(trim(include_test_render($project . '/ext/tpl/part.txt')) === 'template', 'a template file of the project is included');
    $GLOBALS['SYSTEM']['file_base'] = $root;

    // the settings core keeps secret are not printed, other settings are
    putenv('SMTP_PASSWORD=secret-password');
    putenv('NIMBLY_TEST_MAP_KEY=public-key');
    ob_start();
    run_template('[#env SMTP_PASSWORD#]|[#env SMTP_PASSWORD default=none#]|[#env NIMBLY_TEST_MAP_KEY#]');
    include_test_assert((string)ob_get_clean() === '|none|public-key', 'a secret setting was printed, or a public one was not');
    include_test_assert(env('SMTP_PASSWORD') === 'secret-password', 'PHP no longer reads a secret setting');

    echo "include-test: ok\n";
} finally {
    unlink($outside);
    if (isset($project)) {
        @unlink($project . '/ext/data/.files/upload');
        @unlink($project . '/ext/tpl/part.txt');
        @rmdir($project . '/ext/data/.files'); @rmdir($project . '/ext/data'); @rmdir($project . '/ext/tpl'); @rmdir($project . '/ext'); @rmdir($project);
    }
}
