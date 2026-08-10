<?php

require_once __DIR__ . '/agent-runtime.php';

function agent_watchdog_sc($params)
{
    $agent_id = get_param_value($params, 'agent', current($params));
    $status = agent_watchdog_status((string)$agent_id);
    http_response_code($status['healthy'] ? 200 : 503);
    header('Content-Type: text/plain; charset=utf-8');
    return $status['healthy'] ? 'ok' : 'unavailable';
}
