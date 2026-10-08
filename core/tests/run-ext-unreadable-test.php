<?php

// A project folder the web server may not read is a broken install, not a
// site without a home page.

require dirname(__DIR__) . '/lib/run.php';

function run_ext_unreadable_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$base = sys_get_temp_dir() . '/nimbly-run-ext-test-' . bin2hex(random_bytes(4)) . '/';
register_shutdown_function(function () use ($base) {
    exec('chmod -R u+rwx ' . escapeshellarg($base) . ' 2>/dev/null; rm -rf ' . escapeshellarg($base));
});

mkdir($base, 0755, true);
run_ext_unreadable_assert(run_ext_unreadable($base) === false, 'a core without a project is fine');
mkdir($base . 'ext', 0755);
run_ext_unreadable_assert(run_ext_unreadable($base) === false, 'a project without routes is fine');
mkdir($base . 'ext/uri', 0755);
run_ext_unreadable_assert(run_ext_unreadable($base) === false, 'a readable project is fine');

if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
    echo "run ext unreadable tests passed (permission cases skipped as root)\n";
    exit(0);
}

chmod($base . 'ext/uri', 0000);
clearstatcache();
run_ext_unreadable_assert(run_ext_unreadable($base) === true, 'routes that cannot be read are reported');
chmod($base . 'ext/uri', 0755);
chmod($base . 'ext', 0000);
clearstatcache();
run_ext_unreadable_assert(run_ext_unreadable($base) === true, 'a project folder that cannot be entered is reported');

echo "run ext unreadable tests passed\n";
