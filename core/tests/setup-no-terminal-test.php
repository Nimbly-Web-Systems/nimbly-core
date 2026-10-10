<?php

// system:setup without a terminal and without answers (a container, a scheduler) must not ask.

function setup_no_terminal_assert($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function setup_no_terminal_run(string $site, array $env): array {
    $env += ['PATH' => getenv('PATH'), 'APP_ENV' => 'dev'];
    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, 'core/cli/nimbly.php', 'system:setup'],
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $site,
        $env
    );
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    return ['code' => proc_close($process), 'out' => $out, 'err' => $err];
}

function setup_no_terminal_users(string $site): array {
    return array_filter(glob($site . '/ext/data/users/*') ?: [], fn($file) => basename($file) !== '.meta');
}

$root = dirname(__DIR__, 2);
$site = sys_get_temp_dir() . '/nimbly-setup-no-terminal-' . bin2hex(random_bytes(6));
mkdir($site, 0755, true);
exec('cd ' . escapeshellarg($root) . ' && tar -c --exclude=core/tests --exclude=core/static core index.php | tar -x -C ' . escapeshellarg($site), $ignored, $copied);
setup_no_terminal_assert($copied === 0, 'core is copied into a throwaway site');

$run = setup_no_terminal_run($site, []);
setup_no_terminal_assert($run['code'] === 1, 'setup stops without the admin settings: ' . $run['err']);
setup_no_terminal_assert(str_contains($run['err'], 'Set ADMIN_EMAIL and ADMIN_PASSWORD.'), 'both missing settings are named: ' . $run['err']);
setup_no_terminal_assert(substr_count(trim($run['err']), "\n") === 0, 'the error is one line: ' . $run['err']);
setup_no_terminal_assert(!str_contains($run['out'], 'Admin email'), 'no question is printed');
setup_no_terminal_assert(!str_contains($run['out'] . $run['err'], 'stty'), 'the terminal is not touched');
setup_no_terminal_assert(setup_no_terminal_users($site) === [], 'no user is created');

$run = setup_no_terminal_run($site, ['ADMIN_EMAIL' => 'admin@example.test']);
setup_no_terminal_assert($run['code'] === 1, 'setup stops without a password');
setup_no_terminal_assert(str_contains($run['err'], 'Set ADMIN_PASSWORD.') && !str_contains($run['err'], 'ADMIN_EMAIL'), 'only the missing setting is named: ' . $run['err']);

$run = setup_no_terminal_run($site, ['ADMIN_EMAIL' => 'admin@example.test', 'ADMIN_PASSWORD' => 'long-enough-password']);
setup_no_terminal_assert($run['code'] === 0, 'setup finishes with the admin settings: ' . $run['err'] . $run['out']);
setup_no_terminal_assert(str_contains($run['out'], 'Site name: My Nimbly Site [default]'), 'the site name falls back to its default');
setup_no_terminal_assert(count(setup_no_terminal_users($site)) === 1, 'the admin user is created');

$run = setup_no_terminal_run($site, []);
setup_no_terminal_assert($run['code'] === 0, 'a second run asks nothing: ' . $run['err']);

exec('rm -rf ' . escapeshellarg($site));
echo "PASS: setup-no-terminal-test\n";
