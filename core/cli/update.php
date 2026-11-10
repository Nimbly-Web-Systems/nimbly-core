<?php

/**
 * Nimbly CLI — repository update command
 *
 * Usage: ./nimbly core:update
 *        ./nimbly ext:update
 *
 * Run from your project root. Core updates the root repository on master;
 * ext updates the separate ext/ repository on its current branch.
 * Each branch must have a configured upstream (normally origin/master
 * for Core, and origin/master or origin/live for ext).
 *
 * Updates are fast-forward only. Uncommitted changes, detached HEADs and
 * unfinished Git operations are refused. No changes are stashed, committed,
 * rebased or pushed, and no branches are switched.
 *
 * After an update, run ./nimbly build to rebuild assets. If Core setup
 * files need updating, ./nimbly system:repair reports the required changes.
 */

$is_core = ($argv[1] ?? '') === 'core:update';
$label = $is_core ? 'Core' : 'ext';
$repository = rtrim(BASE_DIR, '/') . ($is_core ? '' : '/ext');

if (count($argv) !== 2) {
    fwrite(STDERR, "Usage: ./nimbly " . ($is_core ? 'core:update' : 'ext:update') . "\n");
    exit(1);
}

function repository_update_git(string $repository, array $arguments): array
{
    $output = [];
    $status = 0;
    exec('git -C ' . escapeshellarg($repository) . ' '
        . implode(' ', array_map('escapeshellarg', $arguments)) . ' 2>&1', $output, $status);
    if ($status !== 0) {
        fwrite(STDERR, implode("\n", $output) . "\n");
        exit(1);
    }
    return $output;
}

// Git otherwise searches parent directories, which could update Core when
// ext exists but has not been initialized as a separate repository.
$top_level = implode("\n", repository_update_git($repository, ['rev-parse', '--show-toplevel']));
if (realpath($top_level) !== realpath($repository)) {
    fwrite(STDERR, "{$label} must be a separate Git repository.\n");
    exit(1);
}

$branch = implode('', repository_update_git($repository, ['branch', '--show-current']));
if ($branch === '' || ($is_core && $branch !== 'master')) {
    fwrite(STDERR, $is_core ? "Core must be on master before updating.\n" : "ext must be on a branch before updating.\n");
    exit(1);
}

foreach (['MERGE_HEAD', 'rebase-merge', 'rebase-apply', 'CHERRY_PICK_HEAD', 'REVERT_HEAD', 'sequencer'] as $marker) {
    $path = implode("\n", repository_update_git($repository, ['rev-parse', '--git-path', $marker]));
    if (file_exists(str_starts_with($path, '/') ? $path : $repository . '/' . $path)) {
        fwrite(STDERR, "Finish the Git operation in {$label} before updating.\n");
        exit(1);
    }
}

if (repository_update_git($repository, ['status', '--porcelain', '--untracked-files=normal']) !== []) {
    fwrite(STDERR, "{$label} has uncommitted changes. Commit or stash them before updating.\n");
    exit(1);
}

$upstream = implode('', repository_update_git($repository, ['rev-parse', '--abbrev-ref', '--symbolic-full-name', '@{upstream}']));
echo "Updating {$label} {$branch} from {$upstream}...\n";
$before = repository_update_git($repository, ['rev-parse', 'HEAD']);
$output = repository_update_git($repository, ['pull', '--ff-only', '--no-rebase', '--no-autostash']);
echo implode("\n", $output) . "\n";
if ($before !== repository_update_git($repository, ['rev-parse', 'HEAD'])) {
    echo "Updated {$label}. Run ./nimbly build to rebuild assets.\n";
    if ($is_core) {
        echo "Run ./nimbly system:repair to check for setup file updates.\n";
    }
}
