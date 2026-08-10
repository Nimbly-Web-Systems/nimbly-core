<?php

if (php_sapi_name() !== 'cli') {
    die("agent_gateway.php must be run from the command line.\n");
}

$repo_gateway = defined('BASE_DIR') ? BASE_DIR . 'core/modules/agent/lib/agent-gateway.php' : '';
$installed_gateway = '/usr/local/lib/nimbly-agent-gateway.php';
if ($repo_gateway !== '' && is_file($repo_gateway)) {
    require_once $repo_gateway;
} elseif (is_file($installed_gateway)) {
    require_once $installed_gateway;
} else {
    fwrite(STDERR, "Restricted gateway library is unavailable\n");
    exit(78);
}

try {
    $result = agent_gateway_execute((string)(getenv('SSH_ORIGINAL_COMMAND') ?: ''));
    echo json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
} catch (Throwable) {
    fwrite(STDERR, "Restricted command denied\n");
    exit(77);
}
