<?php

/**
 * Brings generated files level with the current core.
 *
 * Usage: php core/cli/nimbly.php system:repair [--yes]
 *
 * Without --yes it only reports what it would change.
 */

if (!defined('BASE_DIR')) {
    define('BASE_DIR', dirname(__DIR__, 2) . '/');
}

require_once BASE_DIR . 'core/cli/helpers/repair.php';

$apply = in_array('--yes', $argv, true);
$failed = false;
$pending = 0;

$htaccess = repair_htaccess_state(BASE_DIR);
echo $htaccess['message'] . "\n";
if ($htaccess['action'] === 'write') {
    if (!$apply) {
        $pending++;
    } else if (repair_htaccess_apply($htaccess)) {
        echo "Rewritten; the previous file is .htaccess.before-repair.\n";
    } else {
        echo "Could not write .htaccess.\n";
        $failed = true;
    }
}

if ($pending > 0) {
    echo "Nothing changed. Run again with --yes to apply.\n";
}

exit($failed ? 1 : 0);
