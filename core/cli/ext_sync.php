<?php

/**
 * Nimbly CLI — ext:sync command
 *
 * Commits any changes in ext/ and pushes to the remote repository.
 * Intended to run via the scheduler on live and staging environments.
 *
 * Exits silently when git is not available or ext/ has no remote configured.
 */

if (php_sapi_name() !== 'cli') {
    die("nimbly.php must be run from the command line.\n");
}

if (!defined('BASE_DIR')) define('BASE_DIR', realpath(__DIR__ . '/../..') . '/');

$ext_dir = BASE_DIR . 'ext';

// Bail silently if ext/ is not a git repo
if (!is_dir($ext_dir . '/.git')) {
    exit(0);
}

// Bail silently if git is not installed
if (!trim(shell_exec('which git 2>/dev/null') ?? '')) {
    exit(0);
}

function git(string $cmd): array {
    global $ext_dir;
    $output = [];
    $code   = 0;
    exec('git -C ' . escapeshellarg($ext_dir) . ' ' . $cmd . ' 2>&1', $output, $code);
    return ['output' => implode("\n", $output), 'code' => $code];
}

// Bail silently if no remote is configured
$remote = git('remote get-url origin');
if ($remote['code'] !== 0 || empty(trim($remote['output']))) {
    exit(0);
}

// Skip if a rebase or merge is in progress
if (
    is_dir($ext_dir . '/.git/rebase-merge') ||
    is_dir($ext_dir . '/.git/rebase-apply') ||
    file_exists($ext_dir . '/.git/MERGE_HEAD')
) {
    echo "ext:sync skipped — rebase or merge in progress\n";
    exit(0);
}

$status = git('status --porcelain');

if (!empty(trim($status['output']))) {
    $branch = trim(git('branch --show-current')['output']);
    $date   = date('Y-m-d H:i');

    $add = git('add -A');
    if ($add['code'] !== 0) {
        echo "ext:sync error: git add failed\n{$add['output']}\n";
        exit(1);
    }

    $commit = git('commit -m "chore(' . ($branch ?: 'auto') . '): auto-sync content ' . $date . '"');
    if ($commit['code'] !== 0) {
        echo "ext:sync error: git commit failed\n{$commit['output']}\n";
        exit(1);
    }
}

$pull = git('pull --rebase --autostash');
if ($pull['code'] !== 0) {
    echo "ext:sync error: git pull --rebase failed\n{$pull['output']}\n";
    exit(1);
}

$push = git('push');
if ($push['code'] !== 0) {
    echo "ext:sync error: git push failed\n{$push['output']}\n";
    exit(1);
}
