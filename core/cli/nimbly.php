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
require_once BASE_DIR . 'core/cli/helpers/output.php';

$command = $argv[1] ?? null;

$commands = [
    'help'              => ['file' => '',                           'desc' => 'Show command help', 'public' => true],
    'site:setup'       => ['file' => 'core/cli/setup/setup.php',    'desc' => 'Create or repair local site files, resources, and first user', 'public' => true],
    'user:create'      => ['file' => 'core/cli/create_user.php',    'desc' => 'Create a new user account', 'public' => true],
    'module:install'   => ['file' => 'core/cli/install_module.php', 'desc' => 'Install a module (runs its .install.inc)', 'public' => true],
    'jobs:run'         => ['file' => 'core/cli/jobs.php',           'desc' => 'Run queued background jobs', 'public' => false],
    'jobs:prune'       => ['file' => 'core/cli/jobs_prune.php',     'desc' => 'Delete completed jobs older than N days (--days=30)', 'public' => false],
    'schedule:run'     => ['file' => 'core/cli/schedule.php',       'desc' => 'Run due scheduled commands', 'public' => false],
    'schedule:publish' => ['file' => 'core/cli/schedule_publish.php', 'desc' => 'Copy core schedule defaults to ext/cli/schedule.inc', 'public' => false],
    'routes:add'      => ['file' => 'core/cli/routes_add.php',   'desc' => 'Scan route.inc files and create missing dynamic route records', 'public' => true],
    'index:rebuild'    => ['file' => 'core/cli/reindex.php',        'desc' => 'Rebuild index entries for a resource', 'public' => true],
    'system:upgrade-11' => ['file' => 'core/cli/upgrade_11.php',    'desc' => 'Upgrade project to Nimbly 1.1.0', 'public' => true],
    'docker:init'       => ['file' => 'core/cli/docker_init.php',   'desc' => 'Generate Dockerfile and CI workflow in ext/ for Docker image builds', 'public' => true],
    'ext:sync'          => ['file' => 'core/cli/ext_sync.php',      'desc' => 'Commit and push ext/ changes to the remote repository', 'public' => false],
    'create-user'      => ['file' => 'core/cli/create_user.php',    'desc' => 'Alias of user:create', 'public' => false],
    'install-module'   => ['file' => 'core/cli/install_module.php', 'desc' => 'Alias of module:install', 'public' => false],
    'reindex'          => ['file' => 'core/cli/reindex.php',        'desc' => 'Alias of index:rebuild', 'public' => false],
    'upgrade-11'       => ['file' => 'core/cli/upgrade_11.php',     'desc' => 'Alias of system:upgrade-11', 'public' => false],
    'setup'            => ['file' => 'core/cli/setup/setup.php',    'desc' => 'Alias of site:setup', 'public' => false],
    'migrate-pk-index' => ['file' => 'core/cli/migrate_10.php',     'desc' => 'Migrate 1.0.0 pk resources to indexed 1.1.0 resources', 'public' => false],
    'migrate-lib-flat' => ['file' => 'core/cli/migrate_lib.php',    'desc' => 'Flatten single-file library directories to lib/name.php', 'public' => false],
    'migrate-10'       => ['file' => 'core/cli/migrate_10.php',     'desc' => 'Alias of migrate-pk-index', 'public' => false],
    'migrate-lib'      => ['file' => 'core/cli/migrate_lib.php',    'desc' => 'Alias of migrate-lib-flat', 'public' => false],
];

$main_commands = ['init', 'build', 'watch', 'up'];

$ext_commands_file = BASE_DIR . 'ext/cli/commands.php';
if (file_exists($ext_commands_file)) {
    $ext_commands = require $ext_commands_file;
    if (is_array($ext_commands)) {
        $commands = array_merge($commands, $ext_commands);
    }
}

if (!$command || $command === 'help' || !isset($commands[$command])) {
    if ($command && $command !== 'help') {
        echo "Unknown command: {$command}\n\n";
    }
    cli_section('Commands');
    foreach ($commands as $name => $meta) {
        $is_public = $meta['public'] ?? true;
        if (!$is_public || in_array($name, $main_commands, true)) {
            continue;
        }
        printf("  %-18s %s\n", $name, $meta['desc']);
    }
    echo "\n";
    exit($command && $command !== 'help' ? 1 : 0);
}

require BASE_DIR . $commands[$command]['file'];
