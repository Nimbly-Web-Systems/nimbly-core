<?php

// Tool: be woken in this conversation later, to finish and report work that runs on after the reply.
// A run outside a chat, such as a scheduled one, is woken in the chat where its own messages land.
function agent_connector_chat_check_back(array $source, array $config, array $context): array
{
    load_libraries(['env', 'agent-chat']);
    $arguments = (array)(agent_artifact_data($source)['arguments'] ?? []);
    $agent_id = (string)$context['run']['agent_id'];
    $uuid = (string)($context['run']['event_context']['conversation'] ?? '');
    if ($uuid === '') {
        $recipient = trim((string)env((string)($config['recipient_env'] ?? 'SYSTEM_ALERT_EMAIL'), (string)($config['recipient_default'] ?? '')));
        try {
            agent_chat_ensure_resource();
            $uuid = agent_chat_notice_conversation($recipient, $agent_id);
        } catch (InvalidArgumentException $error) {
            $uuid = '';
        }
    }
    return agent_artifact('agent.tool-result', 1, agent_chat_check_back(
        $uuid, (string)$context['run_uuid'], $agent_id, (int)($arguments['minutes'] ?? 0), (string)($arguments['note'] ?? '')
    ));
}
