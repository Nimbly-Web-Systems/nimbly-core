<?php

/** Authorizer of record changes: the asker must hold the right for exactly this change. */
function agent_connector_nimbly_authorize(array $source, array $_config, array $context): array
{
    load_library('agent-site');
    $request = agent_artifact_data($source);
    $arguments = (array)($request['arguments'] ?? []);
    try {
        $refusal = agent_site_write_refusal(agent_site_asker($context), (string)($arguments['action'] ?? ''),
            (string)($arguments['resource'] ?? ''), trim((string)($arguments['uuid'] ?? '')));
    } catch (Throwable $error) {
        $refusal = 'The change could not be checked.';
    }
    return agent_artifact('agent.action-decision', 1, [
        'status' => $refusal === null ? 'authorized' : 'denied',
        'reason' => $refusal ?? 'The colleague may make this change.',
        'action_digest' => (string)($request['action_digest'] ?? ''),
    ]);
}
