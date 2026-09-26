<?php

// Agents that live on another Nimbly site still take part in this site's chat.
// The hub (where people chat) calls the agent's home site through its API, as a user of that
// site: it asks a question, and pulls back what the agents there said. The home never calls
// the hub. What the hub user may ask is decided by their role at home: the `agent-remote`
// feature and a `chat-<agent>` feature per agent. A question runs read-only unless both the
// asker's role at the hub and the hub user's role at home have `agent-act`; the agent's own
// authority at home then applies, as when someone talks to it there.

const AGENT_REMOTE_TIMEOUT = 1800;

/** Agents living elsewhere, as id => home site URL, from AGENT_REMOTES ("id=https://home,..."). */
function agent_remote_homes(): array
{
    load_library('env');
    $homes = [];
    foreach (explode(',', (string)env('AGENT_REMOTES', '')) as $entry) {
        [$agent_id, $url] = array_map('trim', explode('=', $entry, 2) + [1 => '']);
        if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $agent_id) === 1 && preg_match('#^https?://#', $url) === 1) {
            $homes[$agent_id] = rtrim($url, '/');
        }
    }
    return $homes;
}

/** Calls the home site's API as the hub's user there (AGENT_REMOTE_EMAIL / AGENT_REMOTE_PASSWORD). */
function agent_remote_call(string $home, array $payload): array
{
    static $tokens = [];
    load_library('env');
    if (($tokens[$home]['expires'] ?? 0) < time() + 60) {
        $login = agent_remote_http($home . '/api/v1/auth/token', [
            'email' => (string)env('AGENT_REMOTE_EMAIL', ''), 'password' => (string)env('AGENT_REMOTE_PASSWORD', ''),
        ]);
        if (empty($login['token'])) {
            throw new RuntimeException('Could not sign in at the agent\'s home site');
        }
        $tokens[$home] = ['token' => (string)$login['token'], 'expires' => (int)($login['token_expires'] ?? time() + 300)];
    }
    return agent_remote_http($home . '/api/v1/agent-remote', $payload, $tokens[$home]['token']);
}

function agent_remote_http(string $url, array $payload, string $token = ''): array
{
    if (isset($GLOBALS['AGENT_REMOTE_TEST_TRANSPORT']) && is_callable($GLOBALS['AGENT_REMOTE_TEST_TRANSPORT'])) {
        return $GLOBALS['AGENT_REMOTE_TEST_TRANSPORT']($url, $payload, $token);
    }
    $request = curl_init($url);
    curl_setopt_array($request, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $token !== '' ? ['Authorization: Bearer ' . $token] : []),
    ]);
    $response = curl_exec($request);
    $status = (int)curl_getinfo($request, CURLINFO_HTTP_CODE);
    $error = curl_error($request);
    curl_close($request);
    $answer = is_string($response) ? json_decode($response, true) : null;
    if ($status !== 200 || !is_array($answer)) {
        throw new RuntimeException('The agent\'s home site did not answer (' . ($error ?: 'HTTP ' . $status) . ')');
    }
    return $answer;
}

// Hub side.

/** A turn for an agent living elsewhere: a waiting run here, and the question sent to its home. */
function agent_remote_ask(string $uuid, array $conversation, string $agent_id, string $message_id, array $team): string
{
    load_library('util');
    $run_uuid = substr(md5(generate_uuid()), 0, 16);
    data_create('.agent_runs', $run_uuid, [
        'agent_id' => $agent_id, 'trigger' => 'remote', 'status' => 'waiting', 'scheduled_at' => time(),
        'event_context' => ['conversation' => $uuid], 'failure_reason' => '',
    ]);
    $messages = [];
    $asker = '';
    foreach (agent_chat_visible((array)($conversation['messages'] ?? [])) as $message) {
        $messages[] = array_intersect_key($message, array_flip(['id', 'from', 'text', 'at']));
        $asker = (string)($message['asker'] ?? $asker);
    }
    try {
        agent_remote_call(agent_remote_homes()[$agent_id], [
            'operation' => 'ask', 'conversation' => $uuid, 'owner' => (string)$conversation['owner_uuid'],
            'agent' => $agent_id, 'run' => $run_uuid, 'message' => $message_id, 'may_act' => agent_remote_may_act($asker),
            'first_name' => (string)agent_chat_first_name($conversation), 'title' => (string)($conversation['title'] ?? ''),
            'team' => $team, 'messages' => array_slice($messages, -60),
        ]);
    } catch (Throwable $error) {
        data_update('.agent_runs', $run_uuid, ['status' => 'failed', 'failure_reason' => $error->getMessage()]);
    }
    return $run_uuid;
}

/** Whether the person who asked may let an agent living elsewhere act, not only look. */
function agent_remote_may_act(string $asker): bool
{
    if ($asker === '') {
        return false;
    }
    load_libraries(['access', 'permissions']);
    return permission_features_have(user_feature_map($asker), 'agent-act');
}

/** A waiting turn elsewhere that never came back counts as failed; a late answer is still taken. */
function agent_remote_expire(string $run_uuid, array $run): array
{
    if (($run['trigger'] ?? '') === 'remote' && ($run['status'] ?? '') === 'waiting'
        && (int)($run['scheduled_at'] ?? 0) < time() - AGENT_REMOTE_TIMEOUT) {
        $run['status'] = 'failed';
        data_update('.agent_runs', $run_uuid, ['status' => 'failed', 'failure_reason' => 'No answer from the agent\'s home site']);
    }
    return $run;
}

/**
 * Pulls what the agents at each home said since last time, and adds it here. The chat worker calls
 * this every loop; it asks every few seconds while a turn waits, and once a minute otherwise.
 */
function agent_remote_pull(bool $now = false): int
{
    static $last = 0;
    $homes = array_unique(array_values(agent_remote_homes()));
    $waiting = $homes !== [] && data_read_index('.agent_runs', 'status', data_index_uuids('waiting')[0]) !== [];
    if ($homes === [] || (!$now && time() - $last < ($waiting ? 3 : 60))) {
        return 0;
    }
    $last = time();
    $count = 0;
    foreach ($homes as $home) {
        $cursor_file = $GLOBALS['SYSTEM']['file_base'] . 'ext/data/.tmp/agent-remote-' . md5($home) . '.json';
        $since = (int)(json_decode((string)@file_get_contents($cursor_file), true)['since'] ?? time() - 86400);
        try {
            $answer = agent_remote_call($home, ['operation' => 'updates', 'since' => $since - 120]);
        } catch (Throwable $error) {
            error_log('agent remote pull ' . $home . ': ' . $error->getMessage());
            continue;
        }
        foreach ((array)($answer['updates'] ?? []) as $update) {
            try {
                $count += agent_remote_take(is_array($update) ? $update : []) ? 1 : 0;
            } catch (Throwable $error) {
                error_log('agent remote update: ' . $error->getMessage());
            }
        }
        @mkdir(dirname($cursor_file), 0775, true);
        file_put_contents($cursor_file, json_encode(['since' => (int)($answer['now'] ?? time())]));
    }
    return $count;
}

/** One thing an agent at home said: a reply, a hand-over, a failed turn, or a notice to someone. */
function agent_remote_take(array $update): bool
{
    $agent_id = (string)($update['agent'] ?? '');
    if (!isset(agent_remote_homes()[$agent_id])) {
        return false;
    }
    if (($update['type'] ?? '') === 'notice') {
        agent_chat_notice((string)($update['recipient'] ?? ''), $agent_id, (string)($update['text'] ?? ''), 'notice-' . (string)($update['id'] ?? ''));
        return true;
    }
    $run_uuid = (string)($update['run'] ?? '');
    $run = preg_match('/^[a-f0-9]{16}$/', $run_uuid) === 1 ? data_read('.agent_runs', $run_uuid) : null;
    if (!is_array($run) || ($run['trigger'] ?? '') !== 'remote' || ($run['agent_id'] ?? '') !== $agent_id) {
        return false;
    }
    $uuid = (string)($run['event_context']['conversation'] ?? '');
    return match ((string)($update['type'] ?? '')) {
        'reply' => (function () use ($uuid, $agent_id, $update, $run_uuid) {
            agent_chat_append($uuid, $agent_id, (string)($update['text'] ?? ''), $run_uuid);
            data_update('.agent_runs', $run_uuid, ['status' => 'completed', 'completed_at' => time()]);
            return true;
        })(),
        'hand_over' => !empty(agent_chat_hand_over($uuid, $run_uuid, $agent_id,
            (string)($update['to'] ?? ''), (string)($update['note'] ?? ''))['handed_over']),
        'failed' => ($run['status'] ?? '') === 'waiting'
            && data_update('.agent_runs', $run_uuid, ['status' => 'failed', 'failure_reason' => 'The agent could not answer']),
        default => false,
    };
}

// Home side.

/** JSON endpoint for a hub, called with an API token of a user here who has the `agent-remote` feature. */
function agent_remote_sc($_params = null): void
{
    load_libraries(['api', 'access', 'username', 'data', 'json', 'agent', 'agent-chat']);
    if (!api_access('agent-remote')) {
        json_result(['message' => 'ACCESS_DENIED'], 403);
    }
    $input = json_decode((string)file_get_contents('php://input'), true);
    agent_chat_ensure_resource();
    try {
        $result = match ((string)($input['operation'] ?? '')) {
            'ask' => agent_remote_receive_ask((array)$input),
            'updates' => ['updates' => agent_remote_updates((int)($input['since'] ?? 0)), 'now' => time()],
            default => throw new InvalidArgumentException('Unknown operation'),
        };
    } catch (InvalidArgumentException $error) {
        json_result(['message' => $error->getMessage()], 422);
    } catch (Throwable $error) {
        json_result(['message' => agent_safe_error($error->getMessage())], 500);
    }
    json_result($result, 200);
}

/** The agents the hub's user here may ask: those their role lets them chat with, and that answer here. */
function agent_remote_may_ask(string $agent_id): bool
{
    try {
        return access_by_feature('chat-' . $agent_id) && agent_chat_takes_part($agent_id)
            && agent_chat_configured(agent_definition($agent_id));
    } catch (Throwable) {
        return false;
    }
}

/** The hub's question: kept here as a mirror of its conversation, and answered by a chat turn (read-only unless allowed to act). */
function agent_remote_receive_ask(array $input): array
{
    $agent_id = (string)($input['agent'] ?? '');
    $hub_run = (string)($input['run'] ?? '');
    if (!agent_remote_may_ask($agent_id) || preg_match('/^[a-f0-9]{16}$/', $hub_run) !== 1) {
        throw new InvalidArgumentException('That agent does not answer here');
    }
    $hub = (string)username_get();
    $uuid = substr(hash('sha256', 'hub:' . $hub . ':' . (string)($input['conversation'] ?? '')), 0, 16);
    $lock = agent_lock('chat-' . $uuid);
    try {
        $run_uuid = agent_enqueue_result($agent_id, null, [
            'trigger' => 'chat', 'idempotency_suffix' => 'hub-' . $hub_run,
            'read_only' => empty($input['may_act']) || !access_by_feature('agent-act'),
            'event_context' => ['conversation' => $uuid, 'hub_run' => $hub_run],
        ])['run_uuid'];
        $messages = [];
        foreach ((array)($input['messages'] ?? []) as $message) {
            $message = (array)$message;
            $messages[] = ['id' => (string)($message['id'] ?? ''), 'from' => (string)($message['from'] ?? 'user'),
                'text' => mb_substr((string)($message['text'] ?? ''), 0, 20000), 'at' => (int)($message['at'] ?? 0)]
                + ((string)($message['id'] ?? '') === (string)($input['message'] ?? '') ? ['runs' => [$agent_id => $run_uuid]] : []);
        }
        $mirror = [
            'owner_uuid' => md5('hub:' . $hub . ':' . (string)($input['owner'] ?? '')), 'hub_user' => $hub,
            'title' => (string)($input['title'] ?? ''), 'agents' => [$agent_id], 'messages' => $messages,
            'hub_team' => array_map('strval', (array)($input['team'] ?? [])), 'first_name' => (string)($input['first_name'] ?? ''),
            'read_at' => time(), 'updated_at' => time(),
        ];
        data_exists('.agent_conversations', $uuid)
            ? data_update('.agent_conversations', $uuid, $mirror)
            : data_create('.agent_conversations', $uuid, $mirror);
    } finally {
        agent_unlock($lock);
    }
    return ['ok' => true];
}

/** What agents here said for this hub user since a moment: replies, hand-overs and failures in its mirrors, and notices. */
function agent_remote_updates(int $since): array
{
    $hub = (string)username_get();
    $updates = [];
    foreach (data_read('.agent_conversations') ?: [] as $conversation) {
        $mirror = ($conversation['hub_user'] ?? '') === $hub;
        if (!$mirror && empty($conversation['notice_for'])) {
            continue;
        }
        foreach ((array)($conversation['messages'] ?? []) as $message) {
            foreach ($mirror ? (array)($message['runs'] ?? []) : [] as $agent_id => $run_uuid) {
                $run = data_read('.agent_runs', (string)$run_uuid);
                if (is_array($run) && ($run['status'] ?? '') === 'failed' && max((int)($run['completed_at'] ?? 0), (int)($run['scheduled_at'] ?? 0)) >= $since) {
                    $updates[] = ['type' => 'failed', 'agent' => (string)$agent_id, 'run' => (string)($run['event_context']['hub_run'] ?? '')];
                }
            }
            $agent_id = (string)($message['from'] ?? '');
            // In a mirror only what agents said here counts, not the hub's own history.
            if (($mirror && empty($message['run_uuid'])) || (int)($message['at'] ?? 0) < $since || in_array($agent_id, ['user', 'occasion'], true) || !agent_remote_may_ask($agent_id)) {
                continue;
            }
            $run = $mirror ? data_read('.agent_runs', (string)($message['run_uuid'] ?? '')) : null;
            $updates[] = $mirror
                ? ['type' => 'reply', 'agent' => $agent_id, 'run' => (string)($run['event_context']['hub_run'] ?? ''), 'text' => (string)$message['text']]
                : ['type' => 'notice', 'agent' => $agent_id, 'recipient' => (string)$conversation['notice_for'],
                    'id' => (string)($message['id'] ?? ''), 'text' => (string)$message['text']];
        }
        foreach ($mirror ? (array)($conversation['outbox'] ?? []) : [] as $event) {
            if ((int)($event['at'] ?? 0) >= $since && agent_remote_may_ask((string)($event['agent'] ?? ''))) {
                $updates[] = (array)$event;
            }
        }
    }
    return $updates;
}

/** Something an agent here does in a hub's conversation that the hub must carry out, such as a hand-over. */
function agent_remote_outbox(string $uuid, array $event): void
{
    $lock = agent_lock('chat-' . $uuid);
    try {
        $conversation = (array)data_read('.agent_conversations', $uuid);
        $outbox = array_slice([...(array)($conversation['outbox'] ?? []), $event + ['at' => time()]], -50);
        data_update('.agent_conversations', $uuid, ['outbox' => $outbox]);
    } finally {
        agent_unlock($lock);
    }
}
