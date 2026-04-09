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
    'reindex'        => ['core/cli/reindex.php',         'Rebuild index entries for a resource'],
];

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
