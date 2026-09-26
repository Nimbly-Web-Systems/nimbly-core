<?php

// Tool: bring a colleague agent into the conversation, openly; they answer after this reply.
function agent_connector_chat_hand_over(array $source, array $_config, array $context): array
{
    load_library('agent-chat');
    $arguments = (array)(agent_artifact_data($source)['arguments'] ?? []);
    return agent_artifact('agent.tool-result', 1, agent_chat_hand_over(
        (string)($context['run']['event_context']['conversation'] ?? ''), (string)$context['run_uuid'],
        (string)$context['run']['agent_id'], trim((string)($arguments['agent'] ?? '')), trim((string)($arguments['note'] ?? ''))
    ));
}
