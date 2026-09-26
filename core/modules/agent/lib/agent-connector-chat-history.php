<?php

// Input for a chat turn: the whole group conversation, seen from this agent, as model messages.
function agent_connector_chat_history(array $_source, array $_config, array $context): array
{
    load_library('agent-chat');
    $agent_id = (string)$context['run']['agent_id'];
    $uuid = (string)($context['run']['event_context']['conversation'] ?? '');
    $lock = agent_lock('chat-' . $uuid);
    try {
        $conversation = data_read('.agent_conversations', $uuid);
    } finally {
        agent_unlock($lock);
    }
    if (!is_array($conversation)) {
        throw new RuntimeException('Conversation not found');
    }
    $messages = [['role' => 'user', 'content' => [['type' => 'input_text', 'text' => json_encode([
        'chat' => 'You are in a group chat with a colleague and the other agents of this team.'
            . ' Where it comes naturally you may call the colleague by their first name; never force it.',
        'colleague_first_name' => agent_chat_first_name($conversation),
        'now' => agent_chat_now((string)($context['definition']['timezone'] ?? '')),
        'team' => agent_chat_colleagues($conversation),
        'earlier_conversations' => agent_chat_recent_for($conversation, $uuid),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]]]];
    foreach ((array)($conversation['messages'] ?? []) as $message) {
        $from = (string)($message['from'] ?? 'user');
        $text = (string)($message['text'] ?? '');
        if ($from === 'occasion') {
            $messages[] = ['role' => 'user', 'content' => [['type' => 'input_text',
                'text' => json_encode(['you_start_this_conversation_because' => $text], JSON_UNESCAPED_UNICODE)]]];
            continue;
        }
        $messages[] = $from === $agent_id
            ? ['role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => $text]]]
            : ['role' => 'user', 'content' => [['type' => 'input_text',
                'text' => ($from === 'user' ? 'Colleague' : agent_chat_name($from)) . ': ' . $text]]];
    }
    return agent_artifact('openai.input', 1, ['messages' => $messages]);
}

/** The colleague's first name, when their account has one; the agent may use it where it comes naturally. */
function agent_chat_first_name(array $conversation): ?string
{
    load_library('get-user');
    foreach (array_reverse((array)($conversation['messages'] ?? [])) as $message) {
        if (($message['asker'] ?? '') !== '') {
            $user = find_user_by_email((string)$message['asker']);
            $name = trim((string)(is_array($user) ? ($user['name'] ?? '') : ''));
            return $name === '' || str_contains($name, '@') ? null : explode(' ', $name)[0];
        }
    }
    return null;
}

/** Short memory: the same person's other recent conversations with this team. */
function agent_chat_recent_for(array $conversation, string $uuid, int $limit = 40): array
{
    $recent = [];
    foreach (data_read_index('.agent_conversations', 'owner_uuid',
        data_index_uuids((string)$conversation['owner_uuid'])[0]) as $other_uuid => $other) {
        if ($other_uuid === $uuid || (int)($other['updated_at'] ?? 0) < time() - 14 * 86400) {
            continue;
        }
        foreach ((array)($other['messages'] ?? []) as $message) {
            $recent[] = ['at' => gmdate('Y-m-d H:i', (int)($message['at'] ?? 0)),
                'from' => (string)($message['from'] ?? 'user'), 'text' => mb_substr((string)($message['text'] ?? ''), 0, 600)];
        }
    }
    usort($recent, fn($a, $b) => strcmp($a['at'], $b['at']));
    return array_slice($recent, -$limit);
}
