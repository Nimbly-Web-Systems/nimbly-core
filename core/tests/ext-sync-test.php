<?php

// ext:sync commits local content, takes over what the remote has and pushes
// the result; a conflict fails without leaving a rebase behind.

$root = dirname(__DIR__, 2) . '/';
$tmp = sys_get_temp_dir() . '/nimbly-ext-sync-' . bin2hex(random_bytes(6));

foreach (['NAME' => 'Sync Test', 'EMAIL' => 'sync@test.invalid'] as $key => $value) {
    putenv('GIT_AUTHOR_' . $key . '=' . $value);
    putenv('GIT_COMMITTER_' . $key . '=' . $value);
}

function ext_sync_test_assert($condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}

function ext_sync_test_git(string $dir, string $cmd): string {
    $output = [];
    $code = 0;
    exec('git -C ' . escapeshellarg($dir) . ' ' . $cmd . ' 2>&1', $output, $code);
    if ($code !== 0) { throw new RuntimeException("git $cmd failed: " . implode("\n", $output)); }
    return trim(implode("\n", $output));
}

function ext_sync_test_run(string $site): array {
    global $root;
    $output = [];
    $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('define("BASE_DIR", $argv[1]); require $argv[2];')
        . ' ' . escapeshellarg($site . '/') . ' ' . escapeshellarg($root . 'core/cli/ext_sync.php') . ' 2>&1', $output, $code);
    return ['code' => $code, 'output' => implode("\n", $output)];
}

function ext_sync_test_remove(string $path): void {
    if (is_dir($path) && !is_link($path)) {
        foreach (array_diff(scandir($path), ['.', '..']) as $entry) { ext_sync_test_remove($path . '/' . $entry); }
        rmdir($path);
    } elseif (file_exists($path) || is_link($path)) {
        unlink($path);
    }
}

try {
    $remote = $tmp . '/remote.git';
    $site = $tmp . '/site';
    $ext = $site . '/ext';
    $other = $tmp . '/other';
    mkdir($remote, 0755, true);
    mkdir($site, 0755, true);
    ext_sync_test_git($remote, 'init --bare --initial-branch=live');
    ext_sync_test_git($tmp, 'clone ' . escapeshellarg($remote) . ' ' . escapeshellarg($ext));
    ext_sync_test_git($ext, 'checkout -b live');
    file_put_contents($ext . '/page.txt', "one\n");
    ext_sync_test_git($ext, 'add -A');
    ext_sync_test_git($ext, 'commit -m first');
    ext_sync_test_git($ext, 'push -u origin live');
    ext_sync_test_git($tmp, 'clone --branch live ' . escapeshellarg($remote) . ' ' . escapeshellarg($other));

    // 1. nothing to do
    $run = ext_sync_test_run($site);
    ext_sync_test_assert($run['code'] === 0, 'a sync with nothing to do succeeds: ' . $run['output']);

    // 2. a deploy pushed a commit and an editor changed content on the server
    file_put_contents($other . '/deploy.txt', "deployed\n");
    ext_sync_test_git($other, 'add -A');
    ext_sync_test_git($other, 'commit -m deploy');
    ext_sync_test_git($other, 'push origin live');
    file_put_contents($ext . '/content.txt', "edited\n");
    $run = ext_sync_test_run($site);
    ext_sync_test_assert($run['code'] === 0, 'both sides changed: sync succeeds: ' . $run['output']);
    ext_sync_test_assert(is_file($ext . '/deploy.txt') && is_file($ext . '/content.txt'), 'the checkout has the deploy and the content');
    ext_sync_test_assert(ext_sync_test_git($ext, 'status --porcelain') === '', 'the checkout is clean after a sync');
    $head = ext_sync_test_git($ext, 'rev-parse HEAD');
    ext_sync_test_assert(ext_sync_test_git($remote, 'rev-parse live') === $head, 'the result is pushed');
    ext_sync_test_assert(ext_sync_test_git($ext, 'rev-parse refs/remotes/origin/live') === $head, 'the tracking branch follows');
    ext_sync_test_assert(str_contains(ext_sync_test_git($ext, 'log -1 --format=%s'), 'auto-sync content'), 'content is committed as an auto-sync');

    // 3. a conflict fails and leaves no rebase in progress
    ext_sync_test_git($other, 'pull origin live');
    file_put_contents($other . '/page.txt', "remote\n");
    ext_sync_test_git($other, 'commit -am remote-edit');
    ext_sync_test_git($other, 'push origin live');
    file_put_contents($ext . '/page.txt', "local\n");
    $run = ext_sync_test_run($site);
    ext_sync_test_assert($run['code'] === 1 && str_contains($run['output'], 'ext:sync error: git rebase failed'), 'a conflict is reported: ' . $run['output']);
    ext_sync_test_assert(!is_dir($ext . '/.git/rebase-merge') && !is_dir($ext . '/.git/rebase-apply'), 'no rebase is left in progress');
    ext_sync_test_assert(trim(file_get_contents($ext . '/page.txt')) === 'local', 'the local content is kept');
    ext_sync_test_assert(ext_sync_test_git($ext, 'branch --show-current') === 'live', 'the checkout stays on its branch');

    echo "Ext sync tests passed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    ext_sync_test_remove($tmp);
    exit(1);
}

ext_sync_test_remove($tmp);
