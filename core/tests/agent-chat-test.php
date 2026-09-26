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
    return ['SYSTEM_ALERT_EMAIL' => 'ops@example.test', 'APP_ENV' => 'testing'][$key] ?? $default;
}
function data_lookup($_resource, $_uuid, $_field, $default)
{
    return $default;
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

require_once BASE_DIR . 'core/modules/agent/lib/agent.php';
require_once BASE_DIR . 'core/modules/agent/lib/agent-chat.php';
require_once BASE_DIR . 'core/modules/agent/lib/agent-connector-chat-history.php';
require_once BASE_DIR . 'core/modules/agent/lib/agent-connector-chat-reply.php';
require_once BASE_DIR . 'core/modules/agent/lib/agent-connector-notify-operator.php';

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
chat_test_assert(agent_chat_configured(['requires_env' => ['APP_ENV']]) && !agent_chat_configured(['requires_env' => ['NO_SUCH_KEY']]),
    'an agent joins the chat only where its settings are present');
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
chat_test_assert(agent_chat_link(['path' => '/nb-admin/articles', 'label' => 'Open articles']) === ['path' => '/nb-admin/articles', 'label' => 'Open articles'],
    'a site path becomes a link');
foreach (['//evil.test/x', 'https://evil.test', 'javascript:alert(1)', 'nb-admin', '/a b', ''] as $bad) {
    chat_test_assert(agent_chat_link(['path' => $bad, 'label' => 'x']) === null, 'only same-site paths are links: ' . $bad);
}

// A chat turn: no job queue, reply appended once, whole conversation passed to the model.
$uuid = agent_chat_create($owner, ['helper' => 'Helper']);
agent_chat_append($uuid, 'coder', 'I fixed the build.', 'earlier-run');
$view = agent_chat_post($uuid, $owner, 'How is disk space?');
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
chat_test_assert($seen[2]['content'][0]['text'] === 'Colleague: How is disk space?', 'the colleague\'s message reaches the model');
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
$result = agent_connector_notify_operator(agent_artifact('agent.tool-request', 1, ['tool' => 'notify_operator',
    'arguments' => ['subject' => 'Visitor numbers', 'message' => "Luuk asks for <numbers>\nsince 2025."]]), [],
    ['definition' => ['name' => 'Helper'], 'run' => ['agent_id' => 'helper']]);
chat_test_assert(agent_artifact_data($result)['sent'] === true, 'the escalation reports it was sent');
chat_test_assert($chat_test_emails[0]['recipient'] === 'ops@example.test' && $chat_test_emails[0]['subject'] === '[Nimbly] Helper: Visitor numbers',
    'the escalation goes to the system alert address');
chat_test_assert($chat_test_vars['agent_message'] === "Luuk asks for &lt;numbers&gt;<br />\nsince 2025.", 'the agent\'s message is escaped');

echo "Agent chat tests passed.\n";
