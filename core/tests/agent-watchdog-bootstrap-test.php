<?php

if (defined('BASE_DIR')) {
    fwrite(STDERR, "FAIL: test must start without BASE_DIR\n");
    exit(1);
}

require_once dirname(__DIR__) . '/modules/agent/lib/agent-watchdog.php';

if (!defined('BASE_DIR') || BASE_DIR !== dirname(__DIR__, 2) . '/') {
    fwrite(STDERR, "FAIL: watchdog did not establish the application base directory\n");
    exit(1);
}

if (!function_exists('agent_watchdog_sc') || !function_exists('agent_watchdog_status')) {
    fwrite(STDERR, "FAIL: watchdog runtime did not bootstrap\n");
    exit(1);
}

echo "Agent watchdog bootstrap tests passed.\n";
