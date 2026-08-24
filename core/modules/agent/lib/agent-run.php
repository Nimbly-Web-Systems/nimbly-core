<?php

require_once __DIR__ . '/agent-runtime.php';

function agent_run_job(array $job)
{
    $run_uuid = trim((string)($job['payload']['run_uuid'] ?? ''));
    if ($run_uuid === '') {
        throw new RuntimeException('Agent run UUID is missing');
    }
    $attempts = (int)($job['attempts'] ?? 1);
    $max_attempts = (int)($job['max_attempts'] ?? 3);
    $result = agent_run($run_uuid, ['terminal_on_failure' => $attempts >= $max_attempts]);
    if (($result['status'] ?? '') !== 'completed') {
        $definition = agent_definition((string)($result['agent_id'] ?? ''));
        $shadow_triggers = (array)($definition['report_delivery']['shadow_triggers'] ?? []);
        if (array_intersect(agent_run_triggers($run_uuid), $shadow_triggers) !== []) {
            return true;
        }
        $reason = agent_safe_error((string)($result['failure_reason'] ?? 'Agent run failed'));
        throw new RuntimeException($reason === '' ? 'Agent run failed' : $reason);
    }
    return true;
}
