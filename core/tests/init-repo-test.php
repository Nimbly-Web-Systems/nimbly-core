<?php

// Exercise init's repository selection without installing dependencies or starting Docker.

function init_repo_assert(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function init_repo_process(array $command, string $cwd, array $env): array
{
    $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $env);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['code' => proc_close($process), 'out' => $out, 'err' => $err];
}

$root = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/nimbly-init-repo-' . bin2hex(random_bytes(6));
mkdir($temp . '/bin', 0755, true);
register_shutdown_function(static function () use ($temp): void {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($temp);
});

$stub = '#!' . PHP_BINARY . "\n" . <<<'PHP'
<?php
if (in_array('--version', $argv, true)) {
    exit(0);
}
file_put_contents(getenv('INIT_TEST_LOG'), json_encode([
    'command' => basename($argv[0]),
    'args' => array_slice($argv, 1),
    'repo' => getenv('EXT_REPO') ?: '',
]) . "\n", FILE_APPEND);
PHP;
foreach (['php', 'npm', 'docker'] as $command) {
    file_put_contents($temp . '/bin/' . $command, $stub);
    chmod($temp . '/bin/' . $command, 0755);
}
$env = ['PATH' => $temp . '/bin:' . getenv('PATH'), 'NO_COLOR' => '1', 'GIT_CONFIG_NOSYSTEM' => '1', 'GIT_CONFIG_GLOBAL' => '/dev/null'];
$repo = $temp . '/application source';
mkdir($repo);
file_put_contents($repo . '/readme.md', "Application fixture\n");
foreach ([['git', 'init', '-q'], ['git', 'add', 'readme.md'], ['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.test', '-c', 'commit.gpgsign=false', 'commit', '-qm', 'fixture']] as $command) {
    $run = init_repo_process($command, $repo, $env);
    init_repo_assert($run['code'] === 0, 'create local repository: ' . $run['err']);
}

function init_repo_run(array $args, ?string $env_repo = null, ?string $existing = null): array
{
    global $root, $temp, $env;
    $site = $temp . '/site-' . bin2hex(random_bytes(4));
    mkdir($site . '/core/cli', 0755, true);
    copy($root . '/nimbly', $site . '/nimbly');
    chmod($site . '/nimbly', 0755);
    copy($root . '/core/cli/nimbly-runner.mjs', $site . '/core/cli/nimbly-runner.mjs');
    if ($existing !== null) {
        mkdir($site . '/ext', 0755, true);
        file_put_contents($site . '/ext/keep.txt', "Keep this file\n");
        if ($existing === 'repository') {
            $run = init_repo_process(['git', 'init', '-q', 'ext'], $site, $env);
            init_repo_assert($run['code'] === 0, 'create existing ext repository');
        }
    }
    $run_env = $env + ['INIT_TEST_LOG' => $site . '/calls.jsonl'];
    if ($env_repo !== null) {
        $run_env['EXT_REPO'] = $env_repo;
    }
    $run = init_repo_process(array_merge(['./nimbly'], $args), $site, $run_env);
    $run['site'] = $site;
    $run['calls'] = array_map(fn($line) => json_decode($line, true), file_exists($site . '/calls.jsonl') ? file($site . '/calls.jsonl', FILE_IGNORE_NEW_LINES) : []);
    return $run;
}

function init_repo_setup(array $run, string $repo, string $command = 'php'): void
{
    init_repo_assert($run['code'] === 0, 'init finishes: ' . $run['err']);
    init_repo_assert(array_column($run['calls'], 'command') === ['npm', $command, 'npm'], 'dependencies, setup and build run in order');
    init_repo_assert(array_column($run['calls'], 'repo') === [$repo, $repo, $repo], 'the selected repository reaches every setup step');
    init_repo_assert($run['calls'][0]['args'][0] === 'ci' && $run['calls'][2]['args'] === ['run', '--silent', 'build'], 'init installs dependencies and builds');
}

$run = init_repo_run(['init', $repo], $temp . '/unused-repository');
init_repo_setup($run, $repo);
init_repo_assert(is_dir($run['site'] . '/ext/.git'), 'the positional repository is cloned');
init_repo_assert(file_get_contents($run['site'] . '/ext/readme.md') === "Application fixture\n", 'cloned application files are preserved');
init_repo_assert($run['calls'][1]['args'] === ['core/cli/nimbly.php', 'system:setup'], 'host PHP runs system:setup');

$run = init_repo_run(['init'], $repo);
init_repo_setup($run, $repo);
init_repo_assert(is_dir($run['site'] . '/ext/.git'), 'EXT_REPO still works without an argument');

$run = init_repo_run(['--docker', 'init', $repo]);
init_repo_setup($run, $repo, 'docker');
init_repo_assert(in_array('EXT_REPO=' . $repo, $run['calls'][1]['args'], true), 'Docker receives the selected repository explicitly');
init_repo_assert(array_slice($run['calls'][1]['args'], -4) === ['nimbly', 'php', 'core/cli/nimbly.php', 'system:setup'], 'Docker runs system:setup');

foreach (['files', 'repository'] as $existing) {
    $run = init_repo_run(['init', $temp . '/must-not-be-cloned'], null, $existing);
    init_repo_setup($run, $temp . '/must-not-be-cloned');
    init_repo_assert(file_get_contents($run['site'] . '/ext/keep.txt') === "Keep this file\n", 'existing ext files are kept');
    init_repo_assert(!file_exists($run['site'] . '/ext/readme.md'), 'existing ext is not replaced');
}
$run = init_repo_run(['init'], null, 'repository');
init_repo_setup($run, '');

$run = init_repo_run(['init', $temp . '/missing-repository']);
init_repo_assert($run['code'] !== 0 && $run['calls'] === [], 'clone failure stops before dependencies or setup');

foreach ([['init', $repo, 'extra'], ['init', '--unknown'], ['init', '   ']] as $args) {
    $run = init_repo_run($args);
    init_repo_assert($run['code'] === 1 && str_contains($run['err'], 'Usage:'), 'invalid arguments report usage');
    init_repo_assert($run['calls'] === [] && !is_dir($run['site'] . '/ext'), 'invalid arguments have no setup side effects');
}
foreach ([['init', '--help'], ['init', $repo, '--help'], ['--docker', 'init', '--help']] as $args) {
    $run = init_repo_run($args);
    init_repo_assert($run['code'] === 0 && str_contains($run['out'], './nimbly init [repository]'), 'help describes the optional repository');
    init_repo_assert($run['calls'] === [] && !is_dir($run['site'] . '/ext'), 'help does not clone or run setup');
}

echo "PASS: init-repo-test\n";
