<?php

// Tool: tell someone something in the team chat on the agent's own initiative, such as what it just did.
// It lands in their conversation with the agent here; a hub this agent takes part in picks it up from there.
function agent_connector_chat_post(array $source, array $config, array $context): array
{
    load_libraries(['env', 'agent-chat']);
    agent_chat_ensure_resource();
    $text = trim((string)(agent_artifact_data($source)['arguments']['message'] ?? ''));
    $recipient = trim((string)env((string)($config['recipient_env'] ?? 'SYSTEM_ALERT_EMAIL'), (string)($config['recipient_default'] ?? '')));
    try {
        agent_chat_notice($recipient, (string)$context['run']['agent_id'], $text);
    } catch (Throwable $error) {
        return agent_artifact('agent.tool-result', 1, ['posted' => false, 'reason' => agent_safe_error($error->getMessage())]);
    }
    return agent_artifact('agent.tool-result', 1, ['posted' => true]);
}
