<?php

/**
 * Brings generated files and system records level with the current core.
 *
 * Usage: php core/cli/nimbly.php system:repair [--yes]
 *
 * Without --yes it only reports what it would change.
 */

if (!defined('BASE_DIR')) {
    define('BASE_DIR', dirname(__DIR__, 2) . '/');
}

require_once BASE_DIR . 'core/cli/cli_bootstrap.inc';
require_once BASE_DIR . 'core/cli/helpers/repair.php';
load_library('data');

$apply = in_array('--yes', $argv, true);
$failed = false;
$pending = 0;
$level = 0;

foreach (repair_checks() as $check) {
    $state = in_array($check, ['htaccess', 'guards', 'gitignore'], true)
        ? ('repair_' . $check . '_state')(BASE_DIR)
        : ('repair_' . $check . '_state')();
    if ($state['action'] === 'ok') {
        $level++;
        continue;
    }
    echo $state['message'] . "\n";
    if ($state['action'] !== 'write') {
        continue;
    }
    if (!$apply) {
        $pending++;
    } else if (('repair_' . $check . '_apply')($state)) {
        echo $check === 'htaccess' ? "Rewritten; the previous file is .htaccess.before-repair.\n" : "Repaired.\n";
        $level++;
    } else {
        echo "Could not repair.\n";
        $failed = true;
    }
}

echo $level . ' of ' . count(repair_checks()) . " checks level.\n";
if ($pending > 0) {
    echo "Nothing changed. Run again with --yes to apply.\n";
}

exit($failed ? 1 : 0);
