#!/usr/bin/env php
<?php

/**
 * Nimbly CLI
 *
 * Usage: php core/cli/nimbly.php <command>
 */

if (php_sapi_name() !== 'cli') {
    die("nimbly.php must be run from the command line.\n");
}

define('BASE_DIR', realpath(__DIR__ . '/../..') . '/');

$command = $argv[1] ?? null;
$show_all = $command === 'help';

$commands = [
    'help'              => ['file' => '',                           'desc' => 'Show all commands', 'public' => true],
    'setup'            => ['file' => 'core/cli/setup/setup.php',    'desc' => 'First-time site setup', 'public' => true],
    'create-user'      => ['file' => 'core/cli/create_user.php',    'desc' => 'Create a new user account', 'public' => true],
    'install-module'   => ['file' => 'core/cli/install_module.php', 'desc' => 'Install a module (runs its .install.inc)', 'public' => true],
    'jobs:run'         => ['file' => 'core/cli/jobs.php',           'desc' => 'Run queued background jobs', 'public' => true],
    'jobs:prune'       => ['file' => 'core/cli/jobs_prune.php',     'desc' => 'Delete completed jobs older than N days (--days=30)', 'public' => true],
    'reindex'          => ['file' => 'core/cli/reindex.php',        'desc' => 'Rebuild index entries for a resource', 'public' => true],
    'upgrade-11'       => ['file' => 'core/cli/upgrade_11.php',     'desc' => 'Upgrade project to Nimbly 1.1', 'public' => true],
    'migrate-pk-index' => ['file' => 'core/cli/migrate_10.php',     'desc' => 'Migrate 1.0 pk resources to indexed 1.1 resources', 'public' => false],
    'migrate-lib-flat' => ['file' => 'core/cli/migrate_lib.php',    'desc' => 'Flatten single-file library directories to lib/name.php', 'public' => false],
    'migrate-10'       => ['file' => 'core/cli/migrate_10.php',     'desc' => 'Alias of migrate-pk-index', 'public' => false],
    'migrate-lib'      => ['file' => 'core/cli/migrate_lib.php',    'desc' => 'Alias of migrate-lib-flat', 'public' => false],
];

$ext_commands_file = BASE_DIR . 'ext/cli/commands.php';
if (file_exists($ext_commands_file)) {
    $ext_commands = require $ext_commands_file;
    if (is_array($ext_commands)) {
        $commands = array_merge($commands, $ext_commands);
    }
}

if (!$command || $command === 'help' || !isset($commands[$command])) {
    echo "Usage: php core/cli/nimbly.php <command>\n\n";
    echo "Commands:\n";
    foreach ($commands as $name => $meta) {
        $is_public = $meta['public'] ?? true;
        if (!$is_public) {
            continue;
        }
        printf("  %-18s %s\n", $name, $meta['desc']);
    }
    if ($show_all) {
        echo "\nInternal/Dev Commands:\n";
        foreach ($commands as $name => $meta) {
            $is_public = $meta['public'] ?? true;
            if ($is_public) {
                continue;
            }
            printf("  %-18s %s\n", $name, $meta['desc']);
        }
    }
    echo "\n";
    exit($command && $command !== 'help' ? 1 : 0);
}

require BASE_DIR . $commands[$command]['file'];
