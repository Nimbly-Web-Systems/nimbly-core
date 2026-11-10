<?php

// Exercise both update commands against local Git remotes, without touching
// the developer's repositories or requiring a running application.

$root = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/nimbly-cli-update-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700);
putenv('GIT_CONFIG_GLOBAL=/dev/null');
putenv('GIT_CONFIG_SYSTEM=/dev/null');
putenv('GIT_TERMINAL_PROMPT=0');
foreach (['NAME' => 'Update Test', 'EMAIL' => 'update@test.invalid'] as $key => $value) {
    putenv('GIT_AUTHOR_' . $key . '=' . $value);
    putenv('GIT_COMMITTER_' . $key . '=' . $value);
}

function cli_update_assert(bool $ok, string $message): void
{
    if (!$ok) { throw new RuntimeException($message); }
}

function cli_update_git(string $directory, string $arguments): string
{
    exec('git -C ' . escapeshellarg($directory) . ' ' . $arguments . ' 2>&1', $output, $code);
    cli_update_assert($code === 0, implode("\n", $output));
    return trim(implode("\n", $output));
}

function cli_update_run(string $site, string $command): array
{
    // Use the actual dispatcher and help, with BASE_DIR supplied by a fixture.
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($site . '/core/cli/nimbly.php')
        . ' ' . escapeshellarg($command) . ' 2>&1', $output, $code);
    return ['code' => $code, 'output' => implode("\n", $output)];
}

function cli_update_remove(string $path): void
{
    if (is_dir($path) && !is_link($path)) {
        foreach (array_diff(scandir($path), ['.', '..']) as $entry) { cli_update_remove($path . '/' . $entry); }
        rmdir($path);
    } else {
        unlink($path);
    }
}

try {
    mkdir($tmp . '/seed');
    cli_update_git($tmp . '/seed', 'init -q -b master');
    file_put_contents($tmp . '/seed/.gitignore', "/ext/\n/core/\n");
    file_put_contents($tmp . '/seed/page.txt', "one\n");
    cli_update_git($tmp . '/seed', 'add .');
    cli_update_git($tmp . '/seed', 'commit -qm initial');
    cli_update_git($tmp, 'clone -q --bare seed origin.git');
    cli_update_git($tmp . '/seed', 'remote add origin ' . escapeshellarg($tmp . '/origin.git'));
    cli_update_git($tmp, 'clone -q origin.git site');
    $site = $tmp . '/site';
    $ext = $site . '/ext';
    cli_update_git($site, 'clone -q ' . escapeshellarg($tmp . '/origin.git') . ' ext');
    mkdir($site . '/core/cli/helpers', 0700, true);
    foreach (['nimbly.php', 'update.php', 'helpers/output.php'] as $file) {
        copy($root . '/core/cli/' . $file, $site . '/core/cli/' . $file);
    }

    // Each no-op succeeds. A remote update targets only the requested repo.
    foreach (['core:update', 'ext:update'] as $command) {
        cli_update_assert(cli_update_run($site, $command)['code'] === 0, "$command no-op succeeds");
    }
    $old = cli_update_git($site, 'rev-parse HEAD');
    file_put_contents($tmp . '/seed/page.txt', "two\n");
    cli_update_git($tmp . '/seed', 'commit -qam update');
    cli_update_git($tmp . '/seed', 'push -q origin master');
    $new = cli_update_git($tmp . '/seed', 'rev-parse HEAD');
    $run = cli_update_run($site, 'core:update');
    cli_update_assert($run['code'] === 0 && str_contains($run['output'], './nimbly build'), 'Core updates and suggests a build');
    cli_update_assert(cli_update_git($site, 'rev-parse HEAD') === $new, 'Core reaches remote commit');
    cli_update_assert(cli_update_git($ext, 'rev-parse HEAD') === $old, 'Core update leaves ext alone');

    // ext follows its upstream even when its local branch has another name.
    cli_update_git($ext, 'checkout -qb designer');
    cli_update_git($ext, 'branch --set-upstream-to=origin/master');
    cli_update_assert(cli_update_run($site, 'ext:update')['code'] === 0, 'ext updates its tracked branch');
    cli_update_assert(cli_update_git($ext, 'rev-parse HEAD') === $new, 'ext reaches remote commit');
    cli_update_assert(cli_update_git($ext, 'branch --show-current') === 'designer', 'ext stays on its branch');

    foreach (['core:update' => $site, 'ext:update' => $ext] as $command => $repository) {
        // Dirty and untracked files survive refused updates.
        foreach (['page.txt', 'untracked.txt'] as $file) {
            $original = is_file($repository . '/' . $file) ? file_get_contents($repository . '/' . $file) : null;
            file_put_contents($repository . '/' . $file, "local work\n");
            $run = cli_update_run($site, $command);
            cli_update_assert($run['code'] !== 0 && str_contains($run['output'], 'uncommitted changes'), "$command refuses local files");
            cli_update_assert(file_get_contents($repository . '/' . $file) === "local work\n", 'local work preserved');
            if ($original === null) { unlink($repository . '/' . $file); }
            else { file_put_contents($repository . '/' . $file, $original); }
        }
        cli_update_git($repository, 'branch --unset-upstream');
        cli_update_assert(cli_update_run($site, $command)['code'] !== 0, "$command requires an upstream");
        cli_update_git($repository, 'branch --set-upstream-to=origin/master');
        cli_update_git($repository, 'checkout -q --detach');
        cli_update_assert(cli_update_run($site, $command)['code'] !== 0, "$command refuses detached HEAD");
        cli_update_git($repository, 'checkout -q ' . ($repository === $site ? 'master' : 'designer'));
        file_put_contents($repository . '/.git/MERGE_HEAD', $new . "\n");
        $run = cli_update_run($site, $command);
        cli_update_assert($run['code'] !== 0 && str_contains($run['output'], 'Finish the Git operation'), "$command refuses unfinished merges");
        unlink($repository . '/.git/MERGE_HEAD');
    }

    cli_update_git($site, 'checkout -qb feature');
    cli_update_assert(cli_update_run($site, 'core:update')['code'] !== 0, 'Core requires master');
    cli_update_git($site, 'checkout -q master');

    // Divergence must not produce an automatic merge, rebase or autostash,
    // even if the developer configured pull.rebase and rebase.autoStash.
    file_put_contents($tmp . '/seed/remote.txt', "remote\n");
    cli_update_git($tmp . '/seed', 'add remote.txt');
    cli_update_git($tmp . '/seed', 'commit -qm remote');
    cli_update_git($tmp . '/seed', 'push -q origin master');
    foreach (['core:update' => $site, 'ext:update' => $ext] as $command => $repository) {
        file_put_contents($repository . '/local.txt', "local\n");
        cli_update_git($repository, 'add local.txt');
        cli_update_git($repository, 'commit -qm local');
        cli_update_git($repository, 'config pull.rebase true');
        cli_update_git($repository, 'config rebase.autoStash true');
        $head = cli_update_git($repository, 'rev-parse HEAD');
        cli_update_assert(cli_update_run($site, $command)['code'] !== 0, "$command refuses divergence");
        cli_update_assert(cli_update_git($repository, 'rev-parse HEAD') === $head, 'divergent local history preserved');
        cli_update_assert(cli_update_git($repository, 'status --porcelain') === '', 'divergence leaves a clean worktree');
    }

    rename($ext, $site . '/ext-backup');
    mkdir($ext);
    $head = cli_update_git($site, 'rev-parse HEAD');
    $run = cli_update_run($site, 'ext:update');
    cli_update_assert($run['code'] !== 0 && str_contains($run['output'], 'separate Git repository'), 'uninitialized ext is refused');
    cli_update_assert(cli_update_git($site, 'rev-parse HEAD') === $head, 'uninitialized ext never updates Core');
    echo "cli update tests passed\n";
} finally {
    cli_update_remove($tmp);
}
