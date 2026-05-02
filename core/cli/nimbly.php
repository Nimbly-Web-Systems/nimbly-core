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

$commands = [
    'setup'          => ['core/cli/setup/setup.php',     'First-time site setup'],
    'create-user'    => ['core/cli/create_user.php',     'Create a new user account'],
    'install-module' => ['core/cli/install_module.php',  'Install a module (runs its .install.inc)'],
    'jobs:run'       => ['core/cli/jobs.php',            'Run queued background jobs'],
    'migrate-lib'    => ['core/cli/migrate_lib.php',     'Migrate single-file libraries to lib/name.php'],
    'reindex'        => ['core/cli/reindex.php',         'Rebuild index entries for a resource'],
    'migrate-10'     => ['core/cli/migrate_10.php',      'Migrate resources from core 1.0 to 1.1 (pk → index)'],
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
    foreach ($commands as $name => [$file, $desc]) {
        printf("  %-16s %s\n", $name, $desc);
    }
    echo "\n";
    exit($command && $command !== 'help' ? 1 : 0);
}

require BASE_DIR . $commands[$command][0];
