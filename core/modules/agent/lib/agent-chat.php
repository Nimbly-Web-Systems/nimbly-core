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

/** Agents that can take part in this site's chat, switched on or not, as id => display name. */
function agent_chat_agents(): array
{
    load_library('agent-remote');
    $agents = [];
    $remote = agent_remote_homes();
    foreach (array_unique([...agent_ids(), ...array_keys($remote)]) as $agent_id) {
        if (isset($remote[$agent_id])) {
            $agents[$agent_id] = agent_chat_name($agent_id);
            continue;
        }
        try {
            $definition = agent_definition($agent_id);
        } catch (Throwable) {
            continue;
        }
        if (is_array($definition['chat_pipeline'] ?? null) && agent_chat_configured($definition)) {
            $agents[$agent_id] = (string)($definition['name'] ?? $agent_id);
        }
    }
    return $agents;
}

/** Agents with a chat pipeline that the current user may talk to, as id => display name. */
function agent_chat_team(): array
{
    load_library('access');
    return array_filter(agent_chat_agents(), fn($agent_id) => agent_chat_switched_on($agent_id)
        && access_by_feature('chat-' . $agent_id), ARRAY_FILTER_USE_KEY);
}

/** Site settings can turn the whole chat, or a single agent in it, off. Both are on by default. */
function agent_chat_switched_on(string $agent_id): bool
{
    $chat = data_lookup('.config', 'site', 'chat', []);
    $chat = is_array($chat) ? $chat : [];
    return ($chat['enabled'] ?? true) !== false && ($chat[$agent_id] ?? true) !== false;
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
        $messages = agent_chat_visible((array)($conversation['messages'] ?? []));
        if ($messages === [] && array_filter(array_column((array)($conversation['messages'] ?? []), 'runs')) === []) {
            continue;
        }
        $list[] = [
            'uuid' => $uuid, 'title' => (string)($conversation['title'] ?? ''),
            'updated_at' => (int)($conversation['updated_at'] ?? 0),
            'unread' => agent_chat_unread_count($conversation),
            'waiting' => agent_chat_waiting($conversation),
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
        fn($message) => !in_array(($message['from'] ?? 'user'), ['user', 'occasion'], true) && (int)($message['at'] ?? 0) > $read_at));
}

/** A turn as the chat sees it; a turn waiting on another site too long counts as failed. */
function agent_chat_run(string $run_uuid): ?array
{
    load_library('agent-remote');
    $run = data_read('.agent_runs', $run_uuid);
    return is_array($run) ? agent_remote_expire($run_uuid, $run) : null;
}

/** Whether an agent is still working on the latest message of this conversation. */
function agent_chat_waiting(array $conversation): bool
{
    $last = end($conversation['messages']) ?: [];
    foreach ((array)($last['runs'] ?? []) as $run_uuid) {
        $run = agent_chat_run((string)$run_uuid);
        if (is_array($run) && !in_array(($run['status'] ?? ''), AGENT_TERMINAL_STATUSES, true)) {
            return true;
        }
    }
    return false;
}

/** Messages people see; an occasion only tells the agent why it starts talking. */
function agent_chat_visible(array $messages): array
{
    return array_values(array_filter($messages, fn($message) => ($message['from'] ?? '') !== 'occasion'));
}

/**
 * An agent starts a conversation with someone: the occasion is for the agent only, and its own
 * words become the first message (with a red dot until it is read).
 */
function agent_chat_open(string $username, array $team, string $agent_id, string $title, string $occasion): string
{
    load_library('util');
    $uuid = substr(md5(generate_uuid()), 0, 16);
    $message_id = substr(md5(generate_uuid()), 0, 12);
    data_create('.agent_conversations', $uuid, [
        'owner_uuid' => md5_uuid($username), 'title' => $title, 'agents' => array_keys($team),
        'messages' => [], 'read_at' => 0, 'updated_at' => time(),
    ]);
    $run_uuid = agent_enqueue_result($agent_id, null, [
        'trigger' => 'chat', 'idempotency_suffix' => 'chat-' . $message_id,
        'event_context' => ['conversation' => $uuid],
    ])['run_uuid'];
    data_update('.agent_conversations', $uuid, ['messages' => [['id' => $message_id, 'from' => 'occasion',
        'text' => $occasion, 'at' => time(), 'channel' => 'web', 'runs' => [$agent_id => $run_uuid], 'asker' => $username]]]);
    return $uuid;
}

/** Nimbly says hello the first time someone who may chat opens the site. */
function agent_chat_welcome(string $username, array $team): ?string
{
    if (!isset($team['nimbly'])) {
        return null;
    }
    $owner = md5_uuid($username);
    $lock = agent_lock('chat-welcome-' . $owner);
    try {
        if (agent_chat_list($owner) !== []) {
            return null;
        }
        return agent_chat_open($username, $team, 'nimbly', 'Welcome',
            'This colleague just opened this site for the first time since you are here. Say hello: '
            . 'introduce yourself in one or two sentences, name one or two useful things you can do '
            . 'for them on this site (look at the site first), and invite them to ask. Keep it short and warm.');
    } finally {
        agent_unlock($lock);
    }
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

/** Agents a message goes to: those named with @, otherwise the agent most recently involved, otherwise Nimbly. */
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
    // A follow-up goes to the agent most recently involved: the last to speak, or the last one asked.
    foreach (array_reverse((array)($conversation['messages'] ?? [])) as $message) {
        if (in_array(($message['from'] ?? ''), $participants, true)) {
            return [$message['from']];
        }
        $asked = array_values(array_intersect(array_keys((array)($message['runs'] ?? [])), $participants));
        if ($asked !== []) {
            return array_slice($asked, 0, 1);
        }
    }
    return in_array('nimbly', $participants, true) ? ['nimbly'] : array_slice($participants, 0, 1);
}

/** CLI operator context for an existing chat, with the original colleague's rights. */
function agent_chat_follow_up(string $agent_id, string $uuid, string $text): array
{
    $text = trim($text);
    if ($text === '' || mb_strlen($text) > AGENT_CHAT_MAX_TEXT) {
        throw new InvalidArgumentException('Message is empty or too long');
    }
    if (preg_match('/^[a-f0-9]{16}$/', $uuid) !== 1) {
        throw new InvalidArgumentException('Conversation not found');
    }
    load_libraries(['data', 'permissions', 'util']);
    $lock = agent_lock('chat-' . $uuid);
    try {
        $conversation = data_read('.agent_conversations', $uuid);
        if (!is_array($conversation)) {
            throw new InvalidArgumentException('Conversation not found');
        }
        $team = agent_chat_team_of($conversation);
        if (!isset($team[$agent_id]) || !agent_chat_takes_part($agent_id)) {
            throw new InvalidArgumentException('That agent does not take part in this conversation');
        }
        $asker = '';
        foreach (array_reverse((array)($conversation['messages'] ?? [])) as $message) {
            $candidate = (string)($message['asker'] ?? '');
            if ($candidate !== '' && hash_equals((string)$conversation['owner_uuid'], md5_uuid($candidate))) {
                $asker = $candidate;
                break;
            }
        }
        if ($asker === '' || !permission_features_have(user_feature_map($asker), 'chat-' . $agent_id)) {
            throw new InvalidArgumentException('The conversation owner may not talk to this agent');
        }
        foreach (array_keys($team) as $participant) {
            if (agent_chat_pending_run($conversation, $participant) !== null) {
                return ['queued' => false, 'reason' => 'An active turn is still pending; retry after it finishes.'];
            }
        }
        $message_id = substr(md5(generate_uuid()), 0, 12);
        $conversation['messages'][] = ['id' => $message_id, 'from' => 'occasion',
            'text' => 'Operator follow-up: ' . $text, 'at' => time(), 'channel' => 'operator',
            'asker' => $asker, 'runs' => []];
        $last = array_key_last($conversation['messages']);
        $run_uuid = agent_chat_start_turn($uuid, $conversation, $agent_id, $message_id, $team, ['conversation' => $uuid]);
        $conversation['messages'][$last]['runs'][$agent_id] = $run_uuid;
        data_update('.agent_conversations', $uuid, ['messages' => $conversation['messages'], 'updated_at' => time()]);
        return ['queued' => true, 'conversation' => $uuid, 'agent' => $agent_id, 'run_uuid' => $run_uuid];
    } finally {
        agent_unlock($lock);
    }
}

function agent_chat_post(string $uuid, string $owner, string $text, string $channel = 'web'): array
{
    $text = trim($text);
    if ($text === '' || mb_strlen($text) > AGENT_CHAT_MAX_TEXT) {
        throw new InvalidArgumentException('Message is empty or too long');
    }
    $team = agent_chat_team();
    // A new chat is created together with its first message, so a message that cannot be sent leaves nothing behind.
    if ($uuid === '') {
        if (agent_chat_addressees(['agents' => array_keys($team), 'messages' => []], $team, $text) === []) {
            throw new InvalidArgumentException('Nobody in this chat can answer right now');
        }
        $uuid = agent_chat_create($owner, $team);
    }
    $lock = agent_lock('chat-' . $uuid);
    try {
        $conversation = agent_chat_conversation($uuid, $owner);
        // Everyone the colleague may talk to now takes part, also in conversations from before they joined.
        $conversation['agents'] = array_values(array_unique([...(array)($conversation['agents'] ?? []), ...array_keys($team)]));
        $addressees = agent_chat_addressees($conversation, $team, $text);
        if ($addressees === []) {
            throw new InvalidArgumentException('Nobody in this chat can answer right now');
        }
        foreach ($addressees as $agent_id) {
            if (agent_chat_pending_run($conversation, $agent_id) !== null) {
                throw new InvalidArgumentException('Still waiting for ' . $team[$agent_id]);
            }
        }
        load_library('util');
        $message_id = substr(md5(generate_uuid()), 0, 12);
        // The asker is kept with the message (never shown to the model): tools act with their rights.
        $conversation['messages'][] = ['id' => $message_id, 'from' => 'user', 'text' => $text,
            'at' => time(), 'channel' => $channel, 'runs' => [], 'asker' => (string)username_get()];
        $last = array_key_last($conversation['messages']);
        foreach ($addressees as $agent_id) {
            $conversation['messages'][$last]['runs'][$agent_id] = agent_chat_start_turn($uuid, $conversation, $agent_id,
                $message_id, $team, ['conversation' => $uuid]);
        }
        data_update('.agent_conversations', $uuid, [
            'agents' => $conversation['agents'],
            'messages' => $conversation['messages'], 'updated_at' => time(), 'read_at' => time(),
            'title' => (string)($conversation['title'] ?? '') ?: mb_substr($text, 0, 60),
        ]);
    } finally {
        agent_unlock($lock);
    }
    return agent_chat_view($uuid, $owner);
}

/** An agent's turn on a message: a chat run here, or a question to the site the agent lives on. */
function agent_chat_start_turn(string $uuid, array $conversation, string $agent_id, string $message_id, array $team, array $context): string
{
    load_library('agent-remote');
    if (isset(agent_remote_homes()[$agent_id])) {
        return agent_remote_ask($uuid, $conversation, $agent_id, $message_id, $team);
    }
    return agent_enqueue_result($agent_id, null, [
        'trigger' => 'chat', 'idempotency_suffix' => 'chat-' . $message_id, 'event_context' => $context,
    ])['run_uuid'];
}

/** The run still working on the latest message to this agent, if any. */
function agent_chat_pending_run(array $conversation, string $agent_id): ?array
{
    foreach (array_reverse((array)($conversation['messages'] ?? [])) as $message) {
        $run_uuid = (string)($message['runs'][$agent_id] ?? '');
        if ($run_uuid === '') {
            continue;
        }
        $run = agent_chat_run($run_uuid);
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
        return agent_chat_switched_on($agent_id) && is_array(agent_definition($agent_id)['chat_pipeline'] ?? null);
    } catch (Throwable) {
        return false;
    }
}

/** Today as the agent needs it for dates: weekday, date, time and timezone. */
function agent_chat_now(string $timezone = '', ?int $now = null): string
{
    try {
        $zone = new DateTimeZone($timezone !== '' ? $timezone : date_default_timezone_get());
    } catch (Throwable) {
        $zone = new DateTimeZone('UTC');
    }
    $now ??= time();
    // the Unix time too: agents compute tool timestamps from it rather than converting dates by hand
    return (new DateTimeImmutable('@' . $now))->setTimezone($zone)->format('l Y-m-d H:i')
        . ' (' . $zone->getName() . '; Unix time ' . $now . ')';
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
    foreach (agent_chat_team_of($conversation) as $agent_id => $name) {
        try {
            $role = (string)(agent_definition($agent_id)['role'] ?? '');
        } catch (Throwable) {
            $role = '';
        }
        $colleagues[] = ['id' => $agent_id, 'name' => $name, 'role' => $role];
    }
    return $colleagues;
}

/** The agents in a conversation as id => name; a conversation from another site has that site's team. */
function agent_chat_team_of(array $conversation): array
{
    if (is_array($conversation['hub_team'] ?? null) && $conversation['hub_team'] !== []) {
        return array_map('strval', $conversation['hub_team']);
    }
    $team = [];
    foreach ((array)($conversation['agents'] ?? []) as $agent_id) {
        $team[(string)$agent_id] = agent_chat_name((string)$agent_id);
    }
    return $team;
}

/**
 * An agent brings a colleague agent into the conversation: the colleague gets its own turn on the
 * same message, and answers in the chat after this agent's reply.
 */
function agent_chat_hand_over(string $uuid, string $run_uuid, string $from, string $to, string $note): array
{
    $mirror = data_read('.agent_conversations', $uuid);
    if (is_array($mirror) && !empty($mirror['hub_user'])) {
        // A conversation from another site: its colleagues are there, so that site brings them in.
        if (!isset($mirror['hub_team'][$to]) || $to === $from) {
            return ['handed_over' => false, 'reason' => 'That colleague is not in this conversation.'];
        }
        load_library('agent-remote');
        agent_remote_outbox($uuid, ['type' => 'hand_over', 'agent' => $from, 'to' => $to, 'note' => mb_substr($note, 0, 1000),
            'run' => (string)(data_read('.agent_runs', $run_uuid)['event_context']['hub_run'] ?? '')]);
        return ['handed_over' => true, 'note' => 'They will answer in the chat after your reply.'];
    }
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
            if (isset($message['runs'][$to])) {
                return ['handed_over' => true, 'note' => 'They already answered this message.'];
            }
            $conversation['messages'][$index]['runs'][$to] = agent_chat_start_turn($uuid, $conversation, $to,
                (string)$message['id'], agent_chat_team_of($conversation),
                ['conversation' => $uuid, 'handed_over_by' => $from, 'note' => mb_substr($note, 0, 1000)]);
            data_update('.agent_conversations', $uuid, ['messages' => $conversation['messages']]);
            return ['handed_over' => true, 'note' => 'They will answer in the chat right after your reply.'];
        }
        return ['handed_over' => false, 'reason' => 'The message you are answering was not found.'];
    } finally {
        agent_unlock($lock);
    }
}

/**
 * An agent tells someone something on its own initiative, in that person's conversation with the
 * agent (made the first time); it shows with a red dot until read.
 */
function agent_chat_notice(string $recipient, string $agent_id, string $text, string $notice_id = ''): string
{
    load_libraries(['util', 'get-user']);
    $user = find_user_by_email($recipient);
    $text = trim($text);
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || $text === '') {
        throw new InvalidArgumentException('Nobody to tell, or nothing to say');
    }
    // Kept for the recipient also without an account here: a hub may pick it up for them.
    $owner = md5_uuid(is_array($user) && !empty($user['email']) ? (string)$user['email'] : $recipient);
    $uuid = substr(hash('sha256', 'notice:' . $owner . ':' . $agent_id), 0, 16);
    $lock = agent_lock('chat-' . $uuid);
    try {
        if (!data_exists('.agent_conversations', $uuid)) {
            data_create('.agent_conversations', $uuid, ['owner_uuid' => $owner, 'title' => agent_chat_name($agent_id),
                'agents' => [$agent_id], 'messages' => [], 'read_at' => 0, 'updated_at' => time(), 'notice_for' => $recipient]);
        }
    } finally {
        agent_unlock($lock);
    }
    agent_chat_append($uuid, $agent_id, $text, $notice_id !== '' ? $notice_id : 'notice-' . substr(md5(generate_uuid()), 0, 12));
    return $uuid;
}

/** The colleague's first name, when known; the agent may use it where it comes naturally. */
function agent_chat_first_name(array $conversation): ?string
{
    if (trim((string)($conversation['first_name'] ?? '')) !== '') {
        return trim((string)$conversation['first_name']);
    }
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

/** A page of this site an agent points to: a same-site path only, with a short label. */
function agent_chat_link(array $link): ?array
{
    $path = trim((string)($link['path'] ?? ''));
    if ($path === '' || mb_strlen($path) > 300 || preg_match('#^/(?!/)[^\s\\\\]*$#', $path) !== 1) {
        return null;
    }
    return ['path' => $path, 'label' => mb_substr(trim((string)($link['label'] ?? '')) ?: 'Open', 0, 60),
        'open' => !empty($link['open'])];
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
        $run = agent_chat_run((string)$last_message['runs'][$agent_id]);
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
            array_flip(['id', 'from', 'text', 'at', 'link'])), agent_chat_visible((array)$conversation['messages'])),
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

/** Someone removes one of their own conversations (it also leaves the agents' memory). */
function agent_chat_delete(string $uuid, string $owner): array
{
    $lock = agent_lock('chat-' . $uuid);
    try {
        agent_chat_conversation($uuid, $owner);
        data_delete('.agent_conversations', $uuid);
    } finally {
        agent_unlock($lock);
    }
    return ['ok' => true];
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

/** JSON endpoint: list | get | post (without uuid: a new chat) | read | delete | unread. */
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
            'get' => agent_chat_view((string)($input['uuid'] ?? ''), $owner),
            'post' => agent_chat_post((string)($input['uuid'] ?? ''), $owner, (string)($input['text'] ?? '')),
            'read' => agent_chat_mark_read((string)($input['uuid'] ?? ''), $owner),
            'delete' => agent_chat_delete((string)($input['uuid'] ?? ''), $owner),
            'unread' => (function () use ($owner) {
                $list = agent_chat_list($owner);
                return ['unread' => array_sum(array_column($list, 'unread')), 'waiting' => in_array(true, array_column($list, 'waiting'), true)];
            })(),
            default => throw new InvalidArgumentException('Unknown operation'),
        };
    } catch (InvalidArgumentException $error) {
        json_result(['message' => $error->getMessage()], 422);
    }
    json_result($result, 200);
}
