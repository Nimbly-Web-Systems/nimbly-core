<?php

if (php_sapi_name() !== 'cli') {
    die("agent_gateway.php must be run from the command line.\n");
}

require_once BASE_DIR . 'core/modules/agent/lib/agent-gateway.php';

try {
    $result = agent_gateway_execute((string)(getenv('SSH_ORIGINAL_COMMAND') ?: ''));
    echo json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
} catch (Throwable) {
    fwrite(STDERR, "Restricted command denied\n");
    exit(77);
}
