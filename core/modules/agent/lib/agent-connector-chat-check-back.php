<?php

// Tool: be woken in this conversation later, to finish and report work that runs on after the reply.
function agent_connector_chat_check_back(array $source, array $_config, array $context): array
{
    load_library('agent-chat');
    $arguments = (array)(agent_artifact_data($source)['arguments'] ?? []);
    return agent_artifact('agent.tool-result', 1, agent_chat_check_back(
        (string)($context['run']['event_context']['conversation'] ?? ''), (string)$context['run_uuid'],
        (string)$context['run']['agent_id'], (int)($arguments['minutes'] ?? 0), (string)($arguments['note'] ?? '')
    ));
}
