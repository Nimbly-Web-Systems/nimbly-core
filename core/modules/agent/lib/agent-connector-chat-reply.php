<?php

// Output of a chat turn: the agent's reply is added to the conversation.
function agent_connector_chat_reply(array $source, array $_config, array $context): array
{
    load_library('agent-chat');
    $data = agent_artifact_data($source);
    $reply = trim((string)($data['reply'] ?? ''));
    if ($reply === '') {
        throw new RuntimeException('Agent gave no reply');
    }
    agent_chat_append((string)($context['run']['event_context']['conversation'] ?? ''),
        (string)$context['run']['agent_id'], $reply, (string)$context['run_uuid'],
        ['path' => $data['link_path'] ?? '', 'label' => $data['link_label'] ?? '']);
    return agent_artifact('delivery.receipt', 1, ['success' => true, 'deliveries' => ['chat' => ['accepted' => true]]]);
}
