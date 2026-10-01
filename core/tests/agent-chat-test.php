<?php

define('BASE_DIR', dirname(__DIR__, 2) . '/');

$agent_test_data = [];
$agent_test_jobs = [];
$chat_test_user = 'hermen';
$chat_test_features = ['chat-helper', 'chat-coder', 'chat-silent'];
$chat_test_model_fails = false;

function load_library($_name): void {}
function load_libraries($_names): void {}
function job_enqueue($type, $payload, $options): bool
{
    $GLOBALS['agent_test_jobs'][] = compact('type', 'payload', 'options');
    return true;
}
function md5_uuid(string $value): string
{
    return md5($value);
}
function generate_uuid(): string
{
    return bin2hex(random_bytes(16));
}
function username_get(): string
{
    return $GLOBALS['chat_test_user'];
}
function access_by_feature(string $feature): bool
{
    return in_array($feature, $GLOBALS['chat_test_features'], true);
}
function env($key, $default = null)
{
    return ($GLOBALS['chat_test_env'] ?? [])[$key] ?? ['SYSTEM_ALERT_EMAIL' => 'ops@example.test', 'APP_ENV' => 'testing'][$key] ?? $default;
}
function data_lookup($_resource, $_uuid, $field, $default)
{
    return $GLOBALS['agent_test_site_config'][$field] ?? $default;
}
function find_user_by_email($email)
{
    return $GLOBALS['chat_test_names'][$email] ?? false;
}
function set_variable($name, $value): void
{
    $GLOBALS['chat_test_vars'][$name] = $value;
}
function email(array $email_data): bool
{
    $GLOBALS['chat_test_emails'][] = $email_data;
    return true;
}
function data_exists($resource, $uuid): bool
{
    return isset($GLOBALS['agent_test_data'][$resource][$uuid]);
}
function data_create_resource($resource, $meta): bool
{
    $GLOBALS['agent_test_data'][$resource]['.meta'] = $meta;
    return true;
}
function data_create($resource, $uuid, $record): bool
{
    if (isset($GLOBALS['agent_test_data'][$resource][$uuid])) {
        return false;
    }
    $GLOBALS['agent_test_data'][$resource][$uuid] = $record + ['uuid' => $uuid];
    return true;
}
function data_update($resource, $uuid, $changes): bool
{
    if (!isset($GLOBALS['agent_test_data'][$resource][$uuid])) {
        return false;
    }
    $GLOBALS['agent_test_data'][$resource][$uuid] = array_merge($GLOBALS['agent_test_data'][$resource][$uuid], $changes);
    return true;
}
function data_delete($resource, $uuid): bool
{
    unset($GLOBALS['agent_test_data'][$resource][$uuid]);
    return true;
}
function data_read($resource, $selector = null)
{
    $records = $GLOBALS['agent_test_data'][$resource] ?? [];
    if (is_string($selector)) {
        return $records[$selector] ?? null;
    }
    unset($records['.meta']);
    return array_values($records);
}
function data_index_uuids($value): array
{
    return [md5_uuid((string)$value)];
}
function data_read_index($resource, $field, $index_uuid): array
{
    $records = $GLOBALS['agent_test_data'][$resource] ?? [];
    unset($records['.meta']);
    return array_filter($records, fn($record) => md5_uuid((string)($record[$field] ?? '')) === $index_uuid);
}

function user_feature_map($name): array
{
    return array_fill_keys($GLOBALS['chat_test_user_features'][$name] ?? [], true);
}
require_once BASE_DIR . 'core/modules/user/lib/permissions.php';
require_once BASE_DIR . 'core/modules/agent/lib/agent.php';
require_once BASE_DIR . 'core/modules/agent/lib/agent-chat.php';
require_once BASE_DIR . 'core/modules/agent/lib/agent-connector-chat-history.php';
require_once BASE_DIR . 'core/modules/agent/lib/agent-connector-chat-reply.php';
require_once BASE_DIR . 'core/modules/agent/lib/agent-connector-notify-operator.php';
require_once BASE_DIR . 'core/modules/agent/lib/agent-remote.php';
require_once BASE_DIR . 'core/modules/agent/lib/agent-connector-chat-post.php';
require_once BASE_DIR . 'core/modules/agent/lib/agent-connector-chat-note.php';

// Stands in for the model: reads the conversation the way the OpenAI connector does.
function agent_connector_fixture_model(array $source, array $_config, array $context): array
{
    if ($GLOBALS['chat_test_model_fails']) {
        throw new RuntimeException('Model unavailable');
    }
    $GLOBALS['chat_test_seen'][$context['run']['agent_id']] = $source['type'] === 'openai.input' ? $source['data']['messages'] : [];
    return agent_artifact('fixture.reply', 1, ['reply' => 'Disk is fine.']);
}
function agent_connector_fixture_daily(array $_source, array $_config, array $_context): array
{
    return agent_artifact('delivery.receipt', 1, ['success' => true, 'deliveries' => []]);
}
function chat_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function chat_test_expect_error(callable $callback, string $expected, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        chat_test_assert(str_contains($error->getMessage(), $expected), $message . ' (got: ' . $error->getMessage() . ')');
        return;
    }
    chat_test_assert(false, $message);
}

$instructions = __DIR__ . '/fixtures/agent-instructions.md';
$daily = [
    'version' => 3,
    'input' => [['id' => 'collect', 'connector' => 'fixture-daily']],
    'agent' => [['id' => 'think', 'connector' => 'fixture-daily']],
    'output' => [['id' => 'delivery', 'connector' => 'fixture-daily']],
    'result_from' => 'delivery', 'delivery_from' => 'delivery',
];
$chat = [
    'version' => 3,
    'input' => [['id' => 'history', 'connector' => 'chat-history']],
    'agent' => [['id' => 'reply', 'connector' => 'fixture-model', 'from' => 'history']],
    'output' => [['id' => 'answer', 'connector' => 'chat-reply', 'from' => 'reply']],
    'result_from' => 'reply', 'delivery_from' => 'answer',
];
foreach (['helper' => 'Helper', 'coder' => 'Coder'] as $agent_id => $name) {
    $GLOBALS['AGENT_TEST_DEFINITIONS'][$agent_id] = ['id' => $agent_id, 'name' => $name, 'version' => '1.0.0',
        'instructions' => $instructions, 'pipeline' => $daily, 'chat_pipeline' => $chat, 'tools' => []];
}
$GLOBALS['AGENT_TEST_DEFINITIONS']['silent'] = ['id' => 'silent', 'version' => '1.0.0',
    'instructions' => $instructions, 'pipeline' => $daily, 'tools' => []];

// Definitions: chat pipeline is validated and its files are resolved like the daily pipeline.
$invalid = $GLOBALS['AGENT_TEST_DEFINITIONS']['helper'];
$invalid['chat_pipeline']['version'] = 2;
chat_test_expect_error(fn() => agent_validate_definition($invalid), 'chat pipeline version', 'an invalid chat pipeline is rejected');
$resolved = agent_resolve_definition_paths(['chat_pipeline' => ['agent' => [['id' => 'reply', 'instructions' => 'agent-instructions.md']]]],
    __DIR__ . '/fixtures/');
chat_test_assert($resolved['chat_pipeline']['agent'][0]['instructions'] === $instructions, 'chat pipeline file references are resolved');

// A chat-only agent needs no scheduled pipeline, and cannot be run as one.
$GLOBALS['AGENT_TEST_DEFINITIONS']['guide'] = ['id' => 'guide', 'version' => '1.0.0',
    'instructions' => $instructions, 'chat_pipeline' => $chat, 'tools' => []];
agent_validate_definition($GLOBALS['AGENT_TEST_DEFINITIONS']['guide'], 'guide');
$guide_run = agent_enqueue_result('guide', null, ['idempotency_suffix' => 'daily-guide']);
chat_test_assert(agent_run($guide_run['run_uuid'])['failure_reason'] === 'Agent only takes part in chat',
    'a chat-only agent has no scheduled work');
$agent_test_jobs = [];
chat_test_expect_error(fn() => agent_validate_definition(['id' => 'x', 'version' => '1', 'instructions' => $instructions], 'x'),
    'pipeline version', 'an agent needs a pipeline or a chat pipeline');
unset($GLOBALS['AGENT_TEST_DEFINITIONS']['guide']);

// Team: agents with a chat pipeline and the chat feature.
agent_chat_ensure_resource();
chat_test_assert(agent_chat_team() === ['helper' => 'Helper', 'coder' => 'Coder'],
    'the team holds agents with a chat pipeline the user may talk to');
$GLOBALS['agent_test_site_config'] = ['chat' => ['enabled' => true, 'coder' => false]];
chat_test_assert(agent_chat_team() === ['helper' => 'Helper'] && !agent_chat_takes_part('coder'),
    'a switched-off agent leaves the team and cannot be handed over to');
$GLOBALS['agent_test_site_config'] = ['chat' => ['enabled' => false]];
chat_test_assert(agent_chat_team() === [], 'a switched-off chat has nobody in it');
unset($GLOBALS['agent_test_site_config']);
chat_test_assert(agent_chat_configured(['requires_env' => ['APP_ENV']]) && !agent_chat_configured(['requires_env' => ['NO_SUCH_KEY']]),
    'an agent joins the chat only where its settings are present');
chat_test_assert(agent_chat_now('Europe/Amsterdam', strtotime('2026-09-26T12:00:00Z')) === 'Saturday 2026-09-26 14:00 (Europe/Amsterdam; Unix time ' . strtotime('2026-09-26T12:00:00Z') . ')',
    'agents know the weekday, date and time');
$owner = agent_chat_owner();

// Addressing.
$conversation = ['agents' => ['helper', 'coder'], 'messages' => []];
$team = agent_chat_team();
chat_test_assert(agent_chat_addressees($conversation, $team, 'hi @coder') === ['coder'], '@id addresses that agent');
chat_test_assert(agent_chat_addressees($conversation, $team, '@Helper how?') === ['helper'], '@name addresses that agent');
chat_test_assert(agent_chat_addressees(['agents' => ['coder']], $team, 'hi') === ['coder'], 'a lone participant answers');
$conversation['messages'][] = ['from' => 'coder', 'text' => 'done'];
chat_test_assert(agent_chat_addressees($conversation, $team, 'thanks') === ['coder'], 'without a mention the last speaker answers');
$with_nimbly = ['agents' => ['nimbly', 'coder'], 'messages' => []];
$nimbly_team = $team + ['nimbly' => 'Nimbly'];
chat_test_assert(agent_chat_addressees($with_nimbly, $nimbly_team, 'what is this?') === ['nimbly'], 'Nimbly answers when nobody spoke yet');
$with_nimbly['messages'][] = ['from' => 'coder', 'text' => 'here'];
chat_test_assert(agent_chat_addressees($with_nimbly, $nimbly_team, 'and then?') === ['coder'], 'a follow-up stays with the agent who answered');
chat_test_assert(agent_chat_addressees($with_nimbly, $nimbly_team, '@nimbly help') === ['nimbly'], '@name still goes direct');

// Links an agent gives are same-site paths only.
chat_test_assert(agent_chat_link(['path' => '/nb-admin/articles', 'label' => 'Open articles']) === ['path' => '/nb-admin/articles', 'label' => 'Open articles', 'open' => false],
    'a site path becomes a link');
chat_test_assert(agent_chat_link(['path' => '/nb-admin/settings', 'label' => 'Settings', 'open' => true])['open'] === true,
    'an agent can open the page right away');
foreach (['//evil.test/x', 'https://evil.test', 'javascript:alert(1)', 'nb-admin', '/a b', ''] as $bad) {
    chat_test_assert(agent_chat_link(['path' => $bad, 'label' => 'x']) === null, 'only same-site paths are links: ' . $bad);
}

// A chat turn: no job queue, reply appended once, whole conversation passed to the model.
$uuid = agent_chat_create($owner, ['helper' => 'Helper']);
agent_chat_append($uuid, 'coder', 'I fixed the build.', 'earlier-run');
$view = agent_chat_post($uuid, $owner, '@Helper how is disk space?');
chat_test_assert($agent_test_jobs === [], 'chat runs stay out of the site-wide job queue');
chat_test_assert(count($view['working']) === 1 && $view['working'][0]['status'] === 'working', 'the addressed agent is shown working');
chat_test_expect_error(fn() => agent_chat_post($uuid, $owner, 'Hello?'), 'Still waiting', 'one unanswered message per agent');
chat_test_assert(agent_chat_run_pending() === 1, 'the chat worker runs the waiting turn');
$messages = data_read('.agent_conversations', $uuid)['messages'];
chat_test_assert(end($messages)['from'] === 'helper' && end($messages)['text'] === 'Disk is fine.', 'the reply is appended');
chat_test_assert($messages[count($messages) - 2]['asker'] === 'hermen', 'the asker is kept with their message');
chat_test_assert(!str_contains(json_encode($chat_test_seen['helper']), 'hermen'), 'the model never sees who asked');
$seen = $chat_test_seen['helper'];
chat_test_assert($seen[1]['content'][0]['text'] === 'Coder: I fixed the build.' && $seen[1]['role'] === 'user',
    'other agents\' messages reach the model with their name');
chat_test_assert($seen[2]['content'][0]['text'] === 'Colleague: @Helper how is disk space?', 'the colleague\'s message reaches the model');
$GLOBALS['chat_test_names'] = [$GLOBALS['chat_test_user'] => ['name' => 'Hermen Reitsma']];
chat_test_assert(agent_chat_first_name(['messages' => [['asker' => $GLOBALS['chat_test_user']]]]) === 'Hermen',
    'the agent knows the colleague\'s first name');
chat_test_assert(agent_chat_first_name(['messages' => [['asker' => 'nobody@example.test']]]) === null,
    'without a name on the account the agent gets none');
chat_test_assert(agent_chat_view($uuid, $owner)['working'] === [], 'an answered agent is no longer working');
$reply_run = end($messages)['run_uuid'];
agent_chat_append($uuid, 'helper', 'Disk is fine.', $reply_run);
chat_test_assert(count(data_read('.agent_conversations', $uuid)['messages']) === count($messages), 'a replayed reply is not added twice');
agent_chat_post($uuid, $owner, 'And memory?');
agent_chat_run_pending();
chat_test_assert(end($chat_test_seen['helper'])['role'] === 'user' && $chat_test_seen['helper'][3]['role'] === 'assistant',
    'the agent\'s own earlier replies reach the model as its own');

// Hand-over: an agent brings a colleague in on the same message; the colleague answers after it.
$group = agent_chat_create($owner, ['helper' => 'Helper', 'coder' => 'Coder']);
agent_chat_post($group, $owner, 'Is the build broken?');
$asked = end(data_read('.agent_conversations', $group)['messages']);
$helper_run = $asked['runs']['helper'];
chat_test_assert(agent_chat_hand_over($group, $helper_run, 'helper', 'nobody', 'x')['handed_over'] === false
    && agent_chat_hand_over($group, $helper_run, 'helper', 'helper', 'x')['handed_over'] === false,
    'only a colleague in the conversation can be brought in');
chat_test_assert(agent_chat_hand_over($group, $helper_run, 'helper', 'coder', 'Builds are yours')['handed_over'] === true,
    'a colleague is brought in');
$runs = end(data_read('.agent_conversations', $group)['messages'])['runs'];
chat_test_assert(array_keys($runs) === ['helper', 'coder'], 'the colleague works on the same message');
chat_test_assert(data_read('.agent_runs', $runs['coder'])['event_context']['handed_over_by'] === 'helper',
    'the colleague knows who brought them in');
chat_test_assert(agent_chat_hand_over($group, $helper_run, 'helper', 'coder', 'again')['note'] === 'They are already working on it.',
    'a colleague is not brought in twice');
chat_test_assert(count(agent_chat_view($group, $owner)['working']) === 2, 'both show as working');
agent_chat_run_pending();
agent_chat_run_pending();
$replies = array_column(array_slice(data_read('.agent_conversations', $group)['messages'], -2), 'from');
chat_test_assert($replies === ['helper', 'coder'], 'the colleague answers after the agent who brought them in');

// An agent starts a conversation: the occasion is for the agent only; its words come first.
$opened = agent_chat_open('hermen', ['helper' => 'Helper'], 'helper', 'Hello', 'Say hello to a new colleague.');
$view = agent_chat_view($opened, $owner);
chat_test_assert($view['messages'] === [] && $view['working'][0]['agent'] === 'helper', 'the occasion is not shown, the agent is at work');
agent_chat_run_pending();
chat_test_assert(str_contains(json_encode(end($chat_test_seen['helper'])), 'you_start_this_conversation_because')
    || str_contains(json_encode($chat_test_seen['helper']), 'Say hello to a new colleague.'), 'the agent knows why it starts talking');
$list = array_column(agent_chat_list($owner), null, 'uuid');
chat_test_assert($list[$opened]['unread'] === 1 && $list[$opened]['last'] === 'Disk is fine.', 'its first words arrive as unread');
chat_test_assert(agent_chat_welcome('hermen', ['helper' => 'Helper']) === null, 'only Nimbly says welcome');
chat_test_assert(agent_chat_welcome('hermen', ['nimbly' => 'Nimbly']) === null, 'no welcome for someone who already chatted');
$welcome = agent_chat_welcome('newcomer', ['nimbly' => 'Nimbly']);
chat_test_assert(is_string($welcome) && data_read('.agent_conversations', $welcome)['owner_uuid'] === md5_uuid('newcomer'),
    'a newcomer is welcomed by Nimbly');
chat_test_assert(agent_chat_welcome('newcomer', ['nimbly' => 'Nimbly']) === null, 'and only once');

// A colleague who joined the team later takes part in an older conversation.
$older = agent_chat_create($owner, ['helper' => 'Helper']);
$chat_test_features = ['chat-coder'];
agent_chat_post($older, $owner, '@coder are you there?');
chat_test_assert(in_array('coder', data_read('.agent_conversations', $older)['agents'], true)
    && isset(end(data_read('.agent_conversations', $older)['messages'])['runs']['coder']), 'a newer colleague answers in an older conversation');
$chat_test_features = [];
chat_test_expect_error(fn() => agent_chat_post($older, $owner, 'anyone?'), 'Nobody in this chat can answer', 'a message nobody can answer is refused');
$chat_test_features = ['chat-helper', 'chat-coder', 'chat-silent'];
agent_chat_run_pending();

// A new chat is created with its first message; if it cannot be sent, nothing is created.
$count_before = count(data_read('.agent_conversations'));
$chat_test_features = [];
chat_test_expect_error(fn() => agent_chat_post('', $owner, 'hello?'), 'Nobody in this chat can answer', 'a message nobody can answer is refused');
chat_test_assert(count(data_read('.agent_conversations')) === $count_before, 'and leaves no empty chat behind');
$chat_test_features = ['chat-helper', 'chat-coder', 'chat-silent'];
$fresh = agent_chat_post('', $owner, '@Helper new question');
chat_test_assert(end($fresh['messages'])['text'] === '@Helper new question' && $fresh['uuid'] !== '', 'a new chat starts with its first message');
agent_chat_run_pending();
$empty = agent_chat_create($owner, ['helper' => 'Helper']);
chat_test_assert(!in_array($empty, array_column(agent_chat_list($owner), 'uuid'), true), 'an empty chat does not show in the history');

// Deleting a conversation: only your own.
$trash = agent_chat_create($owner, ['helper' => 'Helper']);
chat_test_expect_error(fn() => agent_chat_delete($trash, md5_uuid('someone-else')), 'Conversation not found', 'nobody deletes another person\'s chat');
agent_chat_delete($trash, $owner);
chat_test_assert(data_read('.agent_conversations', $trash) === null, 'your own chat can be deleted');

// Unread.
data_update('.agent_conversations', $uuid, ['read_at' => time() - 10]);
chat_test_assert(agent_chat_list($owner)[0]['unread'] === 3, 'agent messages after the last read count as unread');
agent_chat_mark_read($uuid, $owner);
chat_test_assert(agent_chat_list($owner)[0]['unread'] === 0, 'opening the conversation clears unread');

// A failed turn is shown and can be tried again.
$chat_test_model_fails = true;
agent_chat_post($uuid, $owner, 'Try this');
agent_chat_run_pending();
chat_test_assert(agent_chat_view($uuid, $owner)['working'][0]['status'] === 'failed', 'a failed turn is shown as failed');
$chat_test_model_fails = false;
agent_chat_post($uuid, $owner, 'Try this again');
agent_chat_run_pending();
chat_test_assert(agent_chat_view($uuid, $owner)['working'] === [], 'after a failure the colleague can ask again');

// A chat run of an agent without a chat pipeline fails; daily runs still use the job queue.
$silent = agent_enqueue_result('silent', null, ['trigger' => 'chat', 'idempotency_suffix' => 'chat-x']);
chat_test_assert(agent_run($silent['run_uuid'])['failure_reason'] === 'Agent does not take part in chat', 'agents without a chat pipeline do not chat');
agent_enqueue_result('helper', null, ['idempotency_suffix' => 'daily']);
chat_test_assert(count($agent_test_jobs) === 1, 'daily runs still go through the job queue');

// Ownership and memory.
$other_owner = md5_uuid('someone-else');
chat_test_expect_error(fn() => agent_chat_view($uuid, $other_owner), 'Conversation not found', 'another user cannot open the conversation');
chat_test_expect_error(fn() => agent_chat_post($uuid, $other_owner, 'hi'), 'Conversation not found', 'another user cannot post');
$second = agent_chat_create($owner, ['helper' => 'Helper']);
$old = agent_chat_create($owner, ['helper' => 'Helper']);
agent_chat_append($old, 'helper', 'Ancient news', 'old-run');
data_update('.agent_conversations', $old, ['updated_at' => time() - 15 * 86400]);
$foreign = agent_chat_create($other_owner, ['helper' => 'Helper']);
agent_chat_append($foreign, 'helper', 'Not yours', 'foreign-run');
$recent = array_column(agent_chat_recent_for(data_read('.agent_conversations', $second), $second), 'text');
chat_test_assert(in_array('Disk is fine.', $recent, true), 'the same user\'s recent conversations are remembered');
chat_test_assert(!in_array('Ancient news', $recent, true) && !in_array('Not yours', $recent, true),
    'old conversations and other users\' conversations are not');
$agent_memory = array_column(agent_chat_recent('helper'), 'text');
chat_test_assert(in_array('Not yours', $agent_memory, true) && in_array('Disk is fine.', $agent_memory, true)
    && !in_array('Ancient news', $agent_memory, true), 'an agent remembers its recent conversations with everyone');
chat_test_assert(agent_chat_recent('silent') === [], 'conversations an agent is not part of are not its memory');

// Escalation: the agent emails the operator in its own words.
// Operator follow-ups retain ownership and use only the selected participant.
$chat_test_user_features['hermen'] = ['chat-helper', 'chat-coder'];
$follow = agent_chat_create($owner, ['helper' => 'Helper', 'coder' => 'Coder']);
agent_chat_post($follow, $owner, '@Helper Please check');
$before = data_read('.agent_conversations', $follow);
chat_test_assert(agent_chat_follow_up('coder', $follow, 'Verified by the operator')['queued'] === false
    && data_read('.agent_conversations', $follow) === $before, 'active turns defer without changing the conversation');
agent_chat_run_pending();
$queued = agent_chat_follow_up('coder', $follow, 'Verified by the operator');
$stored = data_read('.agent_conversations', $follow);
$occasion = end($stored['messages']);
chat_test_assert($queued['queued'] && array_keys($occasion['runs']) === ['coder'] && $occasion['asker'] === 'hermen'
    && $stored['owner_uuid'] === $owner, 'the selected agent replies with the owner rights and ownership unchanged');
agent_chat_run_pending();
$stored = data_read('.agent_conversations', $follow);
chat_test_assert(end($stored['messages'])['from'] === 'coder', 'the follow-up answer is appended to the original conversation');
chat_test_expect_error(fn() => agent_chat_follow_up('silent', $follow, 'hi'), 'does not take part', 'a nonparticipant cannot receive a follow-up');
chat_test_expect_error(fn() => agent_chat_follow_up('helper', 'aaaaaaaaaaaaaaaa', 'hi'), 'Conversation not found', 'missing conversations are rejected');
chat_test_expect_error(fn() => agent_chat_follow_up('helper', '../bad', 'hi'), 'Conversation not found', 'invalid conversation ids are rejected');
$chat_test_user_features['hermen'] = [];
chat_test_expect_error(fn() => agent_chat_follow_up('helper', $follow, 'hi'), 'owner may not', 'revoked chat permission is respected');
$chat_test_user_features['hermen'] = ['chat-helper', 'chat-coder'];
data_update('.agent_conversations', $follow, ['owner_uuid' => $other_owner]);
chat_test_expect_error(fn() => agent_chat_follow_up('helper', $follow, 'hi'), 'owner may not', 'another user cannot provide the owner identity');

$result = agent_connector_notify_operator(agent_artifact('agent.tool-request', 1, ['tool' => 'notify_operator',
    'arguments' => ['subject' => 'Visitor numbers', 'message' => "Luuk asks for <numbers>\nsince 2025."]]), [],
    ['definition' => ['name' => 'Helper'], 'run' => ['agent_id' => 'helper']]);
chat_test_assert(agent_artifact_data($result)['sent'] === true, 'the escalation reports it was sent');
chat_test_assert($chat_test_emails[0]['recipient'] === 'ops@example.test' && $chat_test_emails[0]['subject'] === '[Nimbly] Helper: Visitor numbers',
    'the escalation goes to the system alert address');
chat_test_assert($chat_test_vars['agent_message'] === "Luuk asks for &lt;numbers&gt;<br />\nsince 2025.", 'the agent\'s message is escaped');
chat_test_assert($chat_test_vars['notification_heading'] === 'Review requested'
    && $chat_test_vars['agent_id'] === 'helper' && $chat_test_vars['conversation_id'] === '',
    'non-chat notifications request review and identify the agent');

// Remote agents: Coder lives on another site (its home). This site (the hub) asks it through the
// home's API as a user there, and pulls back what it said. Both sites run here, one at a time.
$GLOBALS['SYSTEM']['file_base'] = sys_get_temp_dir() . '/nimbly-agent-remote-test-' . getmypid() . '/';
$chat_test_sites = ['home' => ['agent_test_data' => [], 'chat_test_env' => ['NOTICE_TO' => 'hermen@example.test'],
    'chat_test_user' => 'hub@example.test', 'chat_test_features' => ['agent-remote', 'chat-coder']]];
function chat_test_site(string $site): void
{
    $keys = ['agent_test_data', 'chat_test_env', 'chat_test_user', 'chat_test_features'];
    $other = $site === 'home' ? 'hub' : 'home';
    $GLOBALS['chat_test_sites'][$other] = array_combine($keys, array_map(fn($key) => $GLOBALS[$key] ?? [], $keys));
    foreach ($GLOBALS['chat_test_sites'][$site] as $key => $value) {
        $GLOBALS[$key] = $value;
    }
}
$chat_test_calls = [];
$GLOBALS['AGENT_REMOTE_TEST_TRANSPORT'] = function (string $url, array $payload, string $token): array {
    $GLOBALS['chat_test_calls'][] = $url;
    if (str_ends_with($url, '/api/v1/auth/token')) {
        return ['token' => 'token-1', 'token_expires' => time() + 600];
    }
    chat_test_assert($url === 'https://home.test/api/v1/agent-remote' && $token === 'token-1', 'the hub calls the home API with its token');
    chat_test_site('home');
    try {
        if (!access_by_feature('agent-remote')) {
            throw new RuntimeException('The agent\'s home site did not answer (HTTP 403)');
        }
        agent_chat_ensure_resource();
        return match ($payload['operation']) {
            'ask' => agent_remote_receive_ask($payload),
            'updates' => ['updates' => agent_remote_updates((int)$payload['since']), 'now' => time()],
        };
    } catch (InvalidArgumentException $error) {
        throw new RuntimeException('The agent\'s home site did not answer (HTTP 422)');
    } finally {
        chat_test_site('hub');
    }
};
$chat_test_env = ['AGENT_REMOTES' => 'coder=https://home.test/'];
chat_test_assert(agent_remote_homes() === ['coder' => 'https://home.test'], 'remote agents are read from the settings');
chat_test_assert(agent_chat_team() === ['helper' => 'Helper', 'coder' => 'Coder'], 'a remote agent is in the team like any other');
$agent_test_jobs = [];
$remote = agent_chat_post('', $owner, '@Coder how is disk space?')['uuid'];
$hub_message = end(data_read('.agent_conversations', $remote)['messages']);
$hub_run = $hub_message['runs']['coder'];
chat_test_assert(data_read('.agent_runs', $hub_run)['trigger'] === 'remote' && $agent_test_jobs === [], 'the turn is not run here');
chat_test_assert(agent_chat_view($remote, $owner)['working'][0]['status'] === 'working', 'the chat shows the remote agent working');
chat_test_expect_error(fn() => agent_chat_post($remote, $owner, '@Coder hello?'), 'Still waiting for Coder', 'one question at a time');

chat_test_site('home');
$mirror = array_values(array_filter(data_read('.agent_conversations'), fn($conversation) => ($conversation['hub_user'] ?? '') === 'hub@example.test'))[0];
$home_run = end($mirror['messages'])['runs']['coder'];
chat_test_assert(data_read('.agent_runs', $home_run)['read_only'] === true, 'a question from the hub runs read-only at home by default');
chat_test_assert(agent_chat_list(md5_uuid('hub@example.test')) === [], 'the hub\'s conversations are not the hub user\'s chats at home');
agent_chat_run_pending();
$intro = json_decode($chat_test_seen['coder'][0]['content'][0]['text'], true);
chat_test_assert($intro['colleague_first_name'] === 'Hermen' && array_column($intro['team'], 'name') === ['Helper', 'Coder'],
    'at home the agent knows who asks and the hub\'s team');
chat_test_site('hub');

chat_test_assert(agent_remote_pull(true) === 1, 'the hub pulls the reply');
$view = agent_chat_view($remote, $owner);
chat_test_assert(end($view['messages'])['from'] === 'coder' && end($view['messages'])['text'] === 'Disk is fine.' && $view['working'] === [],
    'the reply shows in the hub\'s chat');
chat_test_assert(data_read('.agent_runs', $hub_run)['status'] === 'completed', 'the waiting turn is completed');
agent_remote_pull(true);
chat_test_assert(count(agent_chat_view($remote, $owner)['messages']) === 2, 'a reply pulled twice shows once');

// At home the agent hands over to a colleague at the hub; the hub brings them in.
chat_test_site('home');
chat_test_assert(agent_chat_hand_over(array_key_first(array_filter(data_read_index('.agent_conversations', 'hub_user', md5_uuid('hub@example.test'))) ?: ['x' => 1]),
    $home_run, 'coder', 'nobody', '')['handed_over'] === false, 'only a colleague from the hub\'s team is brought in');
$mirror_uuid = array_key_first(data_read_index('.agent_conversations', 'hub_user', md5_uuid('hub@example.test')));
chat_test_assert(agent_chat_hand_over($mirror_uuid, $home_run, 'coder', 'helper', 'Content question')['handed_over'], 'the hand-over is noted');
chat_test_site('hub');
agent_remote_pull(true);
chat_test_assert(isset(data_read('.agent_conversations', $remote)['messages'][0]['runs']['helper']), 'the hub brings the colleague in');
agent_chat_run_pending();
chat_test_assert(end(agent_chat_view($remote, $owner)['messages'])['from'] === 'helper', 'the colleague answers at the hub');
agent_remote_pull(true);
chat_test_assert(count(array_filter(data_read('.agent_conversations', $remote)['messages'], fn($message) => ($message['from'] ?? '') === 'helper')) === 1,
    'a hand-over pulled twice brings the colleague in once');

// At home the agent tells someone something; the hub puts it in their conversation with the agent.
chat_test_site('home');
$posted = agent_connector_chat_post(agent_artifact('agent.tool-request', 1, ['tool' => 'post_to_chat', 'arguments' => ['message' => 'I restarted Apache.']]),
    ['recipient_env' => 'NOTICE_TO'], ['run' => ['agent_id' => 'coder']]);
chat_test_assert(agent_artifact_data($posted)['posted'] === true, 'the agent posts a notice');
chat_test_site('hub');
$GLOBALS['chat_test_names']['hermen@example.test'] = ['email' => $chat_test_user, 'name' => 'Hermen Reitsma'];
agent_remote_pull(true);
agent_remote_pull(true);
$notices = array_values(array_filter(agent_chat_list($owner), fn($chat) => $chat['title'] === 'Coder'));
chat_test_assert(count($notices) === 1 && $notices[0]['unread'] === 1 && $notices[0]['last'] === 'I restarted Apache.',
    'the notice shows once, unread, in the colleague\'s chat with the agent');

// A role at home that may not chat with the agent: the turn fails here, and the message is kept.
$chat_test_sites['home']['chat_test_features'] = ['agent-remote'];
$refused = agent_chat_post($notices[0]['uuid'], $owner, 'Thanks, why?');
chat_test_assert($refused['working'][0]['status'] === 'failed' && end($refused['messages'])['text'] === 'Thanks, why?',
    'a question the home refuses shows as failed');
$chat_test_sites['home']['chat_test_features'] = ['agent-remote', 'chat-coder'];

// A turn that fails at home, and one that never comes back, show as failed at the hub.
$retry = agent_chat_post($notices[0]['uuid'], $owner, 'Once more?');
$retry_run = end(data_read('.agent_conversations', $notices[0]['uuid'])['messages'])['runs']['coder'];
chat_test_site('home');
$chat_test_model_fails = true;
agent_chat_run_pending();
$chat_test_model_fails = false;
chat_test_site('hub');
agent_remote_pull(true);
chat_test_assert(data_read('.agent_runs', $retry_run)['status'] === 'failed', 'a failed turn at home fails at the hub');
agent_chat_post($notices[0]['uuid'], $owner, 'And now?');
$late_run = end(data_read('.agent_conversations', $notices[0]['uuid'])['messages'])['runs']['coder'];
data_update('.agent_runs', $late_run, ['scheduled_at' => time() - AGENT_REMOTE_TIMEOUT - 1]);
chat_test_assert(agent_chat_view($notices[0]['uuid'], $owner)['working'][0]['status'] === 'failed', 'a turn that never comes back fails');

// Letting the agent act takes agent-act on both sides: the asker's role at the hub, and the hub user's role at home.
$act_chat = agent_chat_post('', $owner, '@Coder please restart Apache')['uuid'];
$act_run = fn() => (function () {
    chat_test_site('home');
    $runs = array_filter(data_read('.agent_runs'), fn($run) => ($run['trigger'] ?? '') === 'chat' && ($run['status'] ?? '') === 'scheduled');
    $read_only = end($runs)['read_only'];
    agent_chat_run_pending();
    chat_test_site('hub');
    agent_remote_pull(true);
    return $read_only;
})();
chat_test_assert($act_run() === true, 'without agent-act at the hub the question stays read-only');
$chat_test_user_features = [$chat_test_user => ['agent-act']];
agent_chat_post($act_chat, $owner, '@Coder please restart Apache now');
chat_test_assert($act_run() === true, 'without agent-act for the hub user at home it stays read-only');
$chat_test_sites['home']['chat_test_features'][] = 'agent-act';
agent_chat_post($act_chat, $owner, '@Coder go ahead');
chat_test_assert($act_run() === false, 'with agent-act on both sides the agent may act');
$chat_test_user_features[$chat_test_user][] = 'chat-coder';
$remote_follow = agent_chat_follow_up('coder', $act_chat, 'Operator verified recovery; tell the colleague.');
chat_test_assert($remote_follow['queued'] && $act_run() === false, 'remote follow-ups retain existing authority');
chat_test_assert(str_contains(json_encode($chat_test_seen['coder']), 'Operator verified recovery'),
    'the remote agent receives the operator context through the chat mechanism');
$chat_test_user_features = [];
$chat_test_env = [];

// After its email went out, the agent's own chat line (if it wrote one) lands in the recipient's chat.
$chat_test_env = ['NOTE_TO' => 'hermen@example.test'];
$note_step = fn($note, $accepted, $shadow = false) => agent_connector_chat_note(agent_artifact('agent.artifact-set', 1, [
    'decision' => agent_artifact('infra.decision', 1, ['subject' => 'Daily', 'chat_note' => $note]),
    'delivery' => agent_artifact('delivery.receipt', 1, ['success' => true, 'deliveries' => ['a' => ['accepted' => $accepted] + ($shadow ? ['shadow' => true] : [])]]),
]), ['note_from' => 'decision', 'delivery_from' => 'delivery', 'recipient_env' => 'NOTE_TO'], ['run' => ['agent_id' => 'helper']]);
$posted_note = fn($result) => agent_artifact_data($result)['deliveries']['chat']['accepted'];
chat_test_assert($posted_note($note_step('New report in your inbox: all quiet.', true)), 'the chat line is posted once the email went out');
chat_test_assert(!$posted_note($note_step('Not sent.', false, true)), 'nothing is posted when the email was not really sent');
chat_test_assert(!$posted_note($note_step('', true)), 'nothing is posted when the agent had nothing to say');
$helper_chat = array_values(array_filter(agent_chat_list($owner), fn($chat) => $chat['title'] === 'Helper'));
chat_test_assert(count($helper_chat) === 1 && $helper_chat[0]['last'] === 'New report in your inbox: all quiet.', 'the line shows in the chat with the agent');
$chat_test_env = [];

echo "Agent chat tests passed.\n";
