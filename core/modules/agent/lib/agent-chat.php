<?php

/**
 * Group chat with a site's team of agents.
 *
 * A conversation belongs to one user and includes every agent that user may talk to.
 * Each message addressed to an agent becomes one chat run of that agent; the agent's
 * chat pipeline answers by appending its reply to the conversation.
 */

const AGENT_CHAT_MAX_TEXT = 8000;

function agent_chat_owner(): string
{
    load_libraries(['util', 'username']);
    return md5_uuid((string)username_get());
}

/** Agents with a chat pipeline that the current user may talk to, as id => display name. */
function agent_chat_team(): array
{
    load_library('access');
    $team = [];
    foreach (agent_ids() as $agent_id) {
        if (!access_by_feature('chat-' . $agent_id)) {
            continue;
        }
        try {
            $definition = agent_definition($agent_id);
        } catch (Throwable) {
            continue;
        }
        if (is_array($definition['chat_pipeline'] ?? null) && agent_chat_configured($definition)) {
            $team[$agent_id] = (string)($definition['name'] ?? $agent_id);
        }
    }
    return $team;
}

/** An agent joins the chat only where the settings it needs (such as its model key) are present. */
function agent_chat_configured(array $definition): bool
{
    load_library('env');
    foreach ((array)($definition['requires_env'] ?? []) as $name) {
        if (trim((string)env((string)$name)) === '') {
            return false;
        }
    }
    return true;
}

function agent_chat_ensure_resource(): void
{
    agent_ensure_resources();
    if (!data_exists('.agent_conversations', '.meta')) {
        $meta = json_decode((string)file_get_contents(agent_base_dir() . 'core/modules/agent/resources/agent-conversations.json'), true);
        data_create_resource('.agent_conversations', $meta);
    }
}

function agent_chat_conversation(string $uuid, string $owner): array
{
    $conversation = preg_match('/^[a-f0-9]{16}$/', $uuid) === 1 ? data_read('.agent_conversations', $uuid) : null;
    if (!is_array($conversation) || !hash_equals((string)($conversation['owner_uuid'] ?? ''), $owner)) {
        throw new InvalidArgumentException('Conversation not found');
    }
    return $conversation;
}

function agent_chat_list(string $owner): array
{
    $list = [];
    foreach (data_read_index('.agent_conversations', 'owner_uuid', data_index_uuids($owner)[0]) as $uuid => $conversation) {
        $messages = (array)($conversation['messages'] ?? []);
        $list[] = [
            'uuid' => $uuid, 'title' => (string)($conversation['title'] ?? ''),
            'updated_at' => (int)($conversation['updated_at'] ?? 0),
            'unread' => agent_chat_unread_count($conversation),
            'last' => mb_substr((string)(end($messages)['text'] ?? ''), 0, 120),
        ];
    }
    usort($list, fn($a, $b) => $b['updated_at'] <=> $a['updated_at']);
    return $list;
}

function agent_chat_unread_count(array $conversation): int
{
    $read_at = (int)($conversation['read_at'] ?? 0);
    return count(array_filter((array)($conversation['messages'] ?? []),
        fn($message) => ($message['from'] ?? 'user') !== 'user' && (int)($message['at'] ?? 0) > $read_at));
}

function agent_chat_create(string $owner, array $team): string
{
    load_library('util');
    $uuid = substr(md5(generate_uuid()), 0, 16);
    data_create('.agent_conversations', $uuid, [
        'owner_uuid' => $owner, 'title' => '', 'agents' => array_keys($team),
        'messages' => [], 'read_at' => time(), 'updated_at' => time(),
    ]);
    return $uuid;
}

/** Agents a message goes to: those named with @, otherwise the last agent who spoke, otherwise Nimbly. */
function agent_chat_addressees(array $conversation, array $team, string $text): array
{
    $participants = array_values(array_intersect((array)($conversation['agents'] ?? []), array_keys($team)));
    $named = array_values(array_filter($participants, function ($agent_id) use ($team, $text) {
        $names = array_unique([strtolower($agent_id), strtolower($team[$agent_id])]);
        foreach ($names as $name) {
            if (preg_match('/(^|\s)@' . preg_quote($name, '/') . '\b/i', $text) === 1) {
                return true;
            }
        }
        return false;
    }));
    if ($named !== []) {
        return $named;
    }
    foreach (array_reverse((array)($conversation['messages'] ?? [])) as $message) {
        if (in_array(($message['from'] ?? ''), $participants, true)) {
            return [$message['from']];
        }
    }
    return in_array('nimbly', $participants, true) ? ['nimbly'] : array_slice($participants, 0, 1);
}

function agent_chat_post(string $uuid, string $owner, string $text, string $channel = 'web'): array
{
    $text = trim($text);
    if ($text === '' || mb_strlen($text) > AGENT_CHAT_MAX_TEXT) {
        throw new InvalidArgumentException('Message is empty or too long');
    }
    $team = agent_chat_team();
    $lock = agent_lock('chat-' . $uuid);
    try {
        $conversation = agent_chat_conversation($uuid, $owner);
        $addressees = agent_chat_addressees($conversation, $team, $text);
        foreach ($addressees as $agent_id) {
            if (agent_chat_pending_run($conversation, $agent_id) !== null) {
                throw new InvalidArgumentException('Still waiting for ' . $team[$agent_id]);
            }
        }
        load_library('util');
        $message_id = substr(md5(generate_uuid()), 0, 12);
        $runs = [];
        foreach ($addressees as $agent_id) {
            $runs[$agent_id] = agent_enqueue_result($agent_id, null, [
                'trigger' => 'chat', 'idempotency_suffix' => 'chat-' . $message_id,
                'event_context' => ['conversation' => $uuid],
            ])['run_uuid'];
        }
        // The asker is kept with the message (never shown to the model): tools act with their rights.
        $conversation['messages'][] = ['id' => $message_id, 'from' => 'user', 'text' => $text,
            'at' => time(), 'channel' => $channel, 'runs' => $runs, 'asker' => (string)username_get()];
        data_update('.agent_conversations', $uuid, [
            'messages' => $conversation['messages'], 'updated_at' => time(), 'read_at' => time(),
            'title' => (string)($conversation['title'] ?? '') ?: mb_substr($text, 0, 60),
        ]);
    } finally {
        agent_unlock($lock);
    }
    return agent_chat_view($uuid, $owner);
}

/** The run still working on the latest message to this agent, if any. */
function agent_chat_pending_run(array $conversation, string $agent_id): ?array
{
    foreach (array_reverse((array)($conversation['messages'] ?? [])) as $message) {
        $run_uuid = (string)($message['runs'][$agent_id] ?? '');
        if ($run_uuid === '') {
            continue;
        }
        $run = data_read('.agent_runs', $run_uuid);
        return is_array($run) && !in_array(($run['status'] ?? ''), AGENT_TERMINAL_STATUSES, true)
            ? $run + ['uuid' => $run_uuid] : null;
    }
    return null;
}

/** Called by an agent's chat pipeline to add its answer. */
function agent_chat_append(string $uuid, string $agent_id, string $text, string $run_uuid, array $link = []): void
{
    $lock = agent_lock('chat-' . $uuid);
    try {
        $conversation = data_read('.agent_conversations', $uuid);
        if (!is_array($conversation)) {
            throw new RuntimeException('Conversation not found');
        }
        foreach ((array)$conversation['messages'] as $message) {
            if (($message['run_uuid'] ?? '') === $run_uuid) {
                return;
            }
        }
        load_library('util');
        $conversation['messages'][] = ['id' => substr(md5(generate_uuid()), 0, 12), 'from' => $agent_id,
            'text' => mb_substr(trim($text), 0, 20000), 'at' => time(), 'channel' => 'web', 'run_uuid' => $run_uuid]
            + (agent_chat_link($link) ? ['link' => agent_chat_link($link)] : []);
        data_update('.agent_conversations', $uuid, ['messages' => $conversation['messages'], 'updated_at' => time()]);
    } finally {
        agent_unlock($lock);
    }
}

function agent_chat_takes_part(string $agent_id): bool
{
    try {
        return is_array(agent_definition($agent_id)['chat_pipeline'] ?? null);
    } catch (Throwable) {
        return false;
    }
}

function agent_chat_name(string $agent_id): string
{
    try {
        return (string)(agent_definition($agent_id)['name'] ?? $agent_id);
    } catch (Throwable) {
        return $agent_id;
    }
}

/** Who is in a conversation, as the agents see it: identity, name and what they do. */
function agent_chat_colleagues(array $conversation): array
{
    $colleagues = [];
    foreach ((array)($conversation['agents'] ?? []) as $agent_id) {
        try {
            $definition = agent_definition($agent_id);
        } catch (Throwable) {
            continue;
        }
        $colleagues[] = ['id' => $agent_id, 'name' => (string)($definition['name'] ?? $agent_id),
            'role' => (string)($definition['role'] ?? '')];
    }
    return $colleagues;
}

/**
 * An agent brings a colleague agent into the conversation: the colleague gets its own turn on the
 * same message, and answers in the chat after this agent's reply.
 */
function agent_chat_hand_over(string $uuid, string $run_uuid, string $from, string $to, string $note): array
{
    $lock = agent_lock('chat-' . $uuid);
    try {
        $conversation = data_read('.agent_conversations', $uuid);
        if (!is_array($conversation) || $to === $from || !in_array($to, (array)($conversation['agents'] ?? []), true)
            || !agent_chat_takes_part($to)) {
            return ['handed_over' => false, 'reason' => 'That colleague is not in this conversation.'];
        }
        if (agent_chat_pending_run($conversation, $to) !== null) {
            return ['handed_over' => true, 'note' => 'They are already working on it.'];
        }
        foreach ($conversation['messages'] as $index => $message) {
            if (!in_array($run_uuid, (array)($message['runs'] ?? []), true)) {
                continue;
            }
            $conversation['messages'][$index]['runs'][$to] = agent_enqueue_result($to, null, [
                'trigger' => 'chat', 'idempotency_suffix' => 'chat-' . $message['id'],
                'event_context' => ['conversation' => $uuid, 'handed_over_by' => $from, 'note' => mb_substr($note, 0, 1000)],
            ])['run_uuid'];
            data_update('.agent_conversations', $uuid, ['messages' => $conversation['messages']]);
            return ['handed_over' => true, 'note' => 'They will answer in the chat right after your reply.'];
        }
        return ['handed_over' => false, 'reason' => 'The message you are answering was not found.'];
    } finally {
        agent_unlock($lock);
    }
}

/** A page of this site an agent points to: a same-site path only, with a short label. */
function agent_chat_link(array $link): ?array
{
    $path = trim((string)($link['path'] ?? ''));
    if ($path === '' || mb_strlen($path) > 300 || preg_match('#^/(?!/)[^\s\\\\]*$#', $path) !== 1) {
        return null;
    }
    return ['path' => $path, 'label' => mb_substr(trim((string)($link['label'] ?? '')) ?: 'Open', 0, 60)];
}

/** A conversation for display: messages, and what each agent is doing right now. */
function agent_chat_view(string $uuid, string $owner): array
{
    $conversation = agent_chat_conversation($uuid, $owner);
    $team = agent_chat_team();
    $working = [];
    foreach ((array)($conversation['agents'] ?? []) as $agent_id) {
        $last_message = null;
        foreach (array_reverse((array)$conversation['messages']) as $message) {
            if (isset($message['runs'][$agent_id])) {
                $last_message = $message;
                break;
            }
        }
        if ($last_message === null) {
            continue;
        }
        $run = data_read('.agent_runs', (string)$last_message['runs'][$agent_id]);
        $answered = array_filter((array)$conversation['messages'],
            fn($message) => ($message['run_uuid'] ?? '') === $last_message['runs'][$agent_id]);
        if ($answered !== [] || !is_array($run)) {
            continue;
        }
        $working[] = [
            'agent' => $agent_id, 'name' => $team[$agent_id] ?? $agent_id,
            'status' => ($run['status'] ?? '') === 'failed' ? 'failed' : 'working',
            'steps' => agent_chat_progress((string)$last_message['runs'][$agent_id]),
        ];
    }
    return [
        'uuid' => $uuid, 'title' => (string)($conversation['title'] ?? ''),
        'team' => $team, 'messages' => array_map(fn($message) => array_intersect_key($message,
            array_flip(['id', 'from', 'text', 'at', 'link'])), (array)$conversation['messages']),
        'working' => $working,
    ];
}

/** The agent's last few actions in plain words, for a "working on it" indicator. */
function agent_chat_progress(string $run_uuid): array
{
    $events = data_read_index('.agent_events', 'run_uuid', data_index_uuids($run_uuid)[0]);
    usort($events, fn($a, $b) => (int)($a['sequence'] ?? 0) <=> (int)($b['sequence'] ?? 0));
    $steps = [];
    foreach ($events as $event) {
        if (($event['type'] ?? '') !== 'tool_requested') {
            continue;
        }
        $payload = (array)($event['payload'] ?? []);
        $arguments = (array)($payload['arguments'] ?? []);
        $argv = (array)($arguments['argv'] ?? []);
        $steps[] = $argv !== []
            ? 'Running ' . mb_substr(implode(' ', array_slice($argv, (basename((string)$argv[0]) === 'bash') ? 2 : 0)), 0, 100)
            : ucfirst(str_replace('_', ' ', (string)($payload['tool'] ?? 'working')))
                . (!empty($arguments['server']) ? ' on ' . $arguments['server'] : '');
    }
    return array_slice($steps, -3);
}

/** Recent messages from every conversation this agent took part in, oldest first (memory for its other runs). */
function agent_chat_recent(string $agent_id, int $days = 14, int $limit = 60): array
{
    $recent = [];
    foreach (data_read('.agent_conversations') ?: [] as $conversation) {
        if (!in_array($agent_id, (array)($conversation['agents'] ?? []), true)
            || (int)($conversation['updated_at'] ?? 0) < time() - $days * 86400) {
            continue;
        }
        foreach ((array)($conversation['messages'] ?? []) as $message) {
            $recent[] = ['at' => gmdate('Y-m-d H:i', (int)($message['at'] ?? 0)),
                'from' => (string)($message['from'] ?? 'user'), 'text' => mb_substr((string)($message['text'] ?? ''), 0, 600)];
        }
    }
    usort($recent, fn($a, $b) => strcmp($a['at'], $b['at']));
    return array_slice($recent, -$limit);
}

function agent_chat_mark_read(string $uuid, string $owner): array
{
    agent_chat_conversation($uuid, $owner);
    data_update('.agent_conversations', $uuid, ['read_at' => time()]);
    return ['ok' => true];
}

/** Runs every waiting chat turn once; the chat worker calls this in a loop. */
function agent_chat_run_pending(): int
{
    agent_ensure_resources();
    $count = 0;
    foreach (data_read_index('.agent_runs', 'status', data_index_uuids('scheduled')[0]) as $uuid => $run) {
        if (($run['trigger'] ?? '') !== 'chat') {
            continue;
        }
        agent_run((string)$uuid);
        $count++;
    }
    return $count;
}

/** JSON endpoint: list | create | get | post | read. */
function agent_chat_sc($_params = null): void
{
    load_libraries(['data', 'json', 'agent']);
    agent_chat_ensure_resource();
    $owner = agent_chat_owner();
    $team = agent_chat_team();
    if ($team === []) {
        json_result(['message' => 'NO_CHAT_ACCESS'], 403);
    }
    $input = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        ? (json_decode((string)file_get_contents('php://input'), true) ?: []) : $_GET;
    $operation = (string)($input['operation'] ?? 'list');
    try {
        $result = match ($operation) {
            'list' => ['conversations' => agent_chat_list($owner), 'team' => $team],
            'create' => agent_chat_view(agent_chat_create($owner, $team), $owner),
            'get' => agent_chat_view((string)($input['uuid'] ?? ''), $owner),
            'post' => agent_chat_post((string)($input['uuid'] ?? ''), $owner, (string)($input['text'] ?? '')),
            'read' => agent_chat_mark_read((string)($input['uuid'] ?? ''), $owner),
            'unread' => ['unread' => array_sum(array_column(agent_chat_list($owner), 'unread'))],
            default => throw new InvalidArgumentException('Unknown operation'),
        };
    } catch (InvalidArgumentException $error) {
        json_result(['message' => $error->getMessage()], 422);
    }
    json_result($result, 200);
}
