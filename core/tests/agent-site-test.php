<?php

define('BASE_DIR', dirname(__DIR__, 2) . '/');

$site_test_data = [
    '.config' => ['managed_pages' => ['enabled' => false], 'site' => ['name' => 'Test site', 'languages' => ['en', 'nl']]],
    'articles' => ['.meta' => ['fields' => [
        'title' => ['type' => 'text', 'name' => 'Title', 'i18n' => true, 'required' => true],
        'status' => ['type' => 'select', 'name' => 'Status', 'options' => ['draft' => 'Draft', 'live' => 'Live']],
    ]], 'a1' => ['uuid' => 'a1', 'title' => ['en' => 'Hello moss', 'nl' => 'Hallo mos'], 'status' => 'live'],
        'a2' => ['uuid' => 'a2', 'title' => ['en' => 'Second', 'nl' => ''], 'status' => 'draft']],
    'projects' => ['.meta' => ['fields' => ['name' => ['type' => 'text']]], 'p1' => ['uuid' => 'p1', 'name' => 'Secret']],
    'pages' => ['.meta' => ['fields' => ['title' => ['type' => 'text', 'i18n' => true]]]],
    '.navigation' => ['.meta' => ['fields' => []]],
    '.content' => ['.meta' => ['fields' => ['text' => ['type' => 'html']]]],
    'users' => ['.meta' => ['encrypt' => 'password', 'fields' => []],
        'u1' => ['uuid' => 'u1', 'email' => 'editor@example.test', 'password' => 'hash', 'salt' => 'salt']],
    '.agent_conversations' => ['c1' => ['messages' => [
        ['from' => 'user', 'text' => 'hi', 'runs' => ['nimbly' => 'run-1'], 'asker' => 'editor@example.test'],
    ]]],
];
$site_test_users = ['editor@example.test' => ['view-articles' => true, 'edit-articles' => true, 'view-.content' => true]];

function load_library($_name): void {}
function load_libraries($_names): void {}
function data_exists($resource, $uuid): bool { return isset($GLOBALS['site_test_data'][$resource][$uuid]); }
function data_read($resource, $uuid = null)
{
    $records = $GLOBALS['site_test_data'][$resource] ?? [];
    unset($records['.meta']);
    return $uuid === null ? $records : ($records[$uuid] ?? null);
}
function data_path($resource) { return $GLOBALS['SYSTEM']['data_base'] . '/' . $resource; }
function data_meta($resource) { return $GLOBALS['site_test_data'][$resource]['.meta'] ?? null; }
function data_list($resource) { return array_keys(data_read($resource)); }
function data_resources_list(): array
{
    $result = [];
    foreach (array_keys($GLOBALS['site_test_data']) as $resource) {
        if ($resource[0] !== '.') {
            $result[$resource] = ['name' => $resource];
        }
    }
    return $result;
}
function data_create($resource, $uuid, $record)
{
    if (($record['status'] ?? '') === 'invalid') {
        $GLOBALS['site_test_error'] = 'VALIDATION_FAILED';
        return false;
    }
    $GLOBALS['site_test_data'][$resource][$uuid] = $record;
    return true;
}
function data_update($resource, $uuid, $changes)
{
    $GLOBALS['site_test_data'][$resource][$uuid] = array_merge($GLOBALS['site_test_data'][$resource][$uuid], $changes);
    return $GLOBALS['site_test_data'][$resource][$uuid];
}
function data_delete($resource, $uuid)
{
    unset($GLOBALS['site_test_data'][$resource][$uuid]);
    return true;
}
function data_error_get() { return $GLOBALS['site_test_error'] ?? ''; }
function data_error_detail_get() { return 'status:value'; }
function generate_uuid() { return 'fresh'; }
function md5_uuid($value) { return md5((string)$value); }
function sanitize_html_fields(array $_meta, array $data): array
{
    return array_map(fn($value) => is_string($value) ? strip_tags($value) : $value, $data);
}
function env($key, $default = '') { return ['SYSTEM_ALERT_EMAIL' => 'dev@test'][$key] ?? $GLOBALS['site_test_env'][$key] ?? $default; }
function data_lookup($_resource, $_uuid, $_field, $default) { return $default; }
function set_variable($name, $value): void { $GLOBALS['site_test_vars'][$name] = $value; }
function email(array $data): bool { $GLOBALS['site_test_emails'][] = $data; return true; }
function user_feature_map($name): array { return $GLOBALS['site_test_users'][$name] ?? ['(none)' => true]; }
function get_i18n_resolve(array $value, $_language) { return reset($value); }
function site_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

require_once BASE_DIR . 'core/modules/user/lib/permissions.php';
require_once BASE_DIR . 'core/modules/agent/lib/agent.php';
require_once BASE_DIR . 'core/modules/agent/lib/agent-site.php';
require_once BASE_DIR . 'core/modules/agent/lib/agent-connector-nimbly.php';
require_once BASE_DIR . 'core/lib/docs.php';

// Scope: everything under the site's data, never a path outside it.
foreach (['articles' => true, '.content' => true, 'users' => true, '.config' => true, '.navigation' => true,
    'pages' => true, '../etc' => false, '' => false] as $resource => $expected) {
    site_test_assert(agent_site_resource_in_scope($resource) === $expected, 'scope of ' . $resource);
}
$GLOBALS['SYSTEM']['data_base'] = sys_get_temp_dir() . '/agent-site-test-' . getmypid();
foreach (array_keys($site_test_data) as $resource) {
    @mkdir($GLOBALS['SYSTEM']['data_base'] . '/' . $resource, 0777, true);
}
register_shutdown_function(fn() => exec('rm -rf ' . escapeshellarg($GLOBALS['SYSTEM']['data_base'])));

// The asker is found from the run, with the features of their roles.
$context = ['run_uuid' => 'run-1', 'run' => ['event_context' => ['conversation' => 'c1']]];
$asker = agent_site_asker($context);
site_test_assert($asker['username'] === 'editor@example.test' && agent_site_can($asker, 'edit-articles')
    && !agent_site_can($asker, 'view-projects'), 'tools act with the rights of the colleague who asked');
site_test_assert(agent_site_asker(['run_uuid' => 'other', 'run' => $context['run']])['features'] === [],
    'an unknown run has no rights');

// Site map: only what the asker may see, with fields the agent can write correctly.
$map = agent_site_map($asker);
site_test_assert(array_keys($map['resources']) === ['articles', '.content'], 'the map lists only resources the asker may see');
site_test_assert($map['resources']['articles']['you_may'] === ['view', 'edit'], 'the map says what the asker may do');
site_test_assert(!empty($map['resources']['articles']['fields']['title']['translated'])
    && $map['resources']['articles']['fields']['status']['options'] === ['draft', 'live'], 'fields show translations and options');
site_test_assert(!isset($map['admin_pages']['/nb-admin/settings']), 'admin pages follow the asker\'s rights');

// Records.
site_test_assert(isset(agent_site_records($asker, 'projects')['error']), 'records outside the asker\'s rights stay hidden');
site_test_assert(isset(agent_site_records($asker, 'users')['error']), 'users need the view right');
$admin = ['username' => 'a', 'features' => ['(all)' => true]];
$user = agent_site_records($admin, 'users', 'u1')['record'];
site_test_assert($user['email'] === 'editor@example.test' && !isset($user['password']) && !isset($user['salt']),
    'password hashes and salts never reach the model');
$list = agent_site_records($asker, 'articles');
site_test_assert($list['total'] === 2 && $list['records'][0]['uuid'] === 'a1', 'records are listed');
site_test_assert(agent_site_records($asker, 'articles', '', 'mos')['total'] === 1, 'records can be searched');
$sorted = agent_site_records($asker, 'articles', '', '', ['status'], '-status');
site_test_assert($sorted['records'] === [['uuid' => 'a1', 'status' => 'live'], ['uuid' => 'a2', 'status' => 'draft']],
    'a list shows only the chosen fields, sorted');
site_test_assert(agent_site_records($asker, 'articles', 'a1')['record']['title']['nl'] === 'Hallo mos'
    && agent_site_records($asker, 'articles', 'a1')['admin_page'] === '/nb-admin/articles/a1', 'one record is read whole');

// Writing: with the asker's rights, the way the admin does, translations merged per language.
$writer = ['username' => 'w', 'features' => ['view-articles' => true, 'create-articles' => true, 'edit-articles' => true]];
site_test_assert(agent_site_write($asker, 'create', 'articles', '', ['title' => ['en' => 'x']])['status'] === 'blocked',
    'an editor without the create right cannot create');
site_test_assert(agent_site_write($writer, 'delete', 'articles', 'a2', [])['status'] === 'blocked', 'deleting needs the delete right');
site_test_assert(agent_site_write($admin, 'update', 'users', 'u1', [])['status'] === 'blocked',
    'resources with protected values are changed in the admin');
$site_test_data['.navigation']['main-en'] = ['uuid' => 'main-en', 'items' => [], 'revision' => 'old', '_revision' => 'current'];
agent_site_write($admin, 'update', '.navigation', 'main-en', ['items' => [['id' => 'a']]]);
site_test_assert($site_test_data['.navigation']['main-en']['revision'] === 'current',
    'a menu save carries the version the agent just read, not the one stored by the last editor save');
$site_test_data['.config']['bovenruimte'] = ['uuid' => 'bovenruimte', 'page_title' => 'Old'];
$site_test_data['.config']['.meta'] = ['fields' => false];
site_test_assert(agent_site_write($admin, 'update', '.config', 'bovenruimte', ['page_title' => 'Groepen'])['status'] === 'done'
    && $site_test_data['.config']['bovenruimte']['page_title'] === 'Groepen', 'page settings can be changed');
$saved = agent_site_write($writer, 'update', 'articles', 'a1', ['title' => ['nl' => 'Hallo wereld'], '_created_by' => 'x', 'uuid' => 'zz']);
site_test_assert($saved['status'] === 'done' && $site_test_data['articles']['a1']['title'] === ['en' => 'Hello moss', 'nl' => 'Hallo wereld'],
    'a translation is added without losing the other languages');
site_test_assert(!isset($site_test_data['articles']['a1']['_created_by']) && $site_test_data['articles']['a1']['uuid'] === 'a1',
    'system fields and the uuid cannot be written');
$created = agent_site_write($writer, 'create', 'articles', '', ['title' => ['en' => 'New'], 'status' => 'draft']);
site_test_assert($site_test_data['articles'][md5('fresh')]['_created_by'] === md5('w'), 'the colleague who asked is the creator');
site_test_assert($created['uuid'] === md5('fresh') && $created['admin_page'] === '/nb-admin/articles/' . md5('fresh'), 'a new record gets a uuid and a link');
site_test_assert(str_contains(agent_site_write($writer, 'create', 'articles', '', ['status' => 'invalid'])['reason'], 'validation'),
    'validation failures come back as a reason');
require_once BASE_DIR . 'core/modules/agent/lib/agent-connector-nimbly-authorize.php';
$site_test_data['.agent_conversations']['c1']['messages'][0]['runs']['nimbly'] = 'run-1';
$decision = agent_artifact_data(agent_connector_nimbly_authorize(agent_artifact('agent.action-request', 1, [
    'tool' => 'save_record', 'action_digest' => 'digest-1',
    'arguments' => ['action' => 'delete', 'resource' => 'articles', 'uuid' => 'a2', 'fields_json' => '{}'],
]), [], $context));
site_test_assert($decision['status'] === 'denied' && $decision['action_digest'] === 'digest-1', 'the authorizer refuses what the asker may not do');
$site_test_users['editor@example.test']['delete-articles'] = true;
$decision = agent_artifact_data(agent_connector_nimbly_authorize(agent_artifact('agent.action-request', 1, [
    'tool' => 'save_record', 'action_digest' => 'digest-2',
    'arguments' => ['action' => 'delete', 'resource' => 'articles', 'uuid' => 'a2', 'fields_json' => '{}'],
]), [], $context));
site_test_assert($decision['status'] === 'authorized' && $decision['action_digest'] === 'digest-2', 'and allows what they may');

// Contacting the developer from a chat: they learn who asked and can reply to them.
require_once BASE_DIR . 'core/modules/agent/lib/agent-connector-notify-operator.php';
$site_test_users['editor@example.test'] = $site_test_users['editor@example.test'] ?? [];
agent_connector_notify_operator(agent_artifact('agent.tool-request', 1, ['arguments' => [
    'subject' => 'Remove test resources', 'message' => 'Please remove them.']]), [],
    $context + ['definition' => ['name' => 'Nimbly', 'id' => 'nimbly']]);
site_test_assert($site_test_emails[0]['recipient'] === 'dev@test' && $site_test_emails[0]['reply_to'] === 'editor@example.test'
    && $site_test_vars['asked_by'] === 'editor@example.test', 'the developer gets the request and can reply to the colleague');
site_test_assert($site_test_vars['notification_heading'] === 'Client request' && $site_test_vars['conversation_id'] === 'c1'
    && $site_test_vars['agent_name'] === 'Nimbly' && $site_test_vars['agent_id'] === 'nimbly',
    'chat escalations identify the conversation, agent and request');
$GLOBALS['site_test_env'] = ['DEVELOPER_EMAIL' => 'builder@test'];
site_test_assert(agent_notify_operator_recipient('site') === 'builder@test' && agent_notify_operator_recipient('hosting') === 'dev@test',
    'a site with its own developer sends site work to them and hosting to the operator');
$GLOBALS['site_test_env'] = [];

// Docs: the real Nimbly reference, a piece at a time.
site_test_assert(count(agent_nimbly_docs('list', '')['outline']) > 10, 'the docs outline is available');
site_test_assert(str_contains(implode("\n", agent_nimbly_docs('search', 'router_accept')['matches']), 'router_accept'), 'the docs can be searched');
site_test_assert(str_contains(agent_nimbly_docs('section', 'Template Syntax')['section'] ?? '', 'Template Syntax'), 'a docs section can be read');
site_test_assert(isset(agent_nimbly_docs('search', 'zzqq-no-such-term')['hint']), 'a failed search suggests what to try');

// The Nimbly agent definition is valid Core.
$definition = agent_definition('nimbly');
site_test_assert(isset($definition['chat_pipeline']) && !isset($definition['pipeline']), 'Nimbly is a chat-only Core agent');

site_test_assert(isset(agent_site_stats(['username' => 'x', 'features' => []], '2026-09-01', '2026-09-02')['error']),
    'visitor statistics need the view-stats right');
site_test_assert(isset($definition['tools']['visitor_stats']), 'Nimbly can look at visitor statistics');

// Deployed history is local, bounded and read-only, with the chat colleague's rights.
$reader = ['username' => 'reader', 'features' => ['chat-nimbly' => true]];
$changes = agent_site_changes($reader);
site_test_assert($changes['days'] === 7 && preg_match('/^[a-f0-9]{40}$/', $changes['core']['deployed']['hash'] ?? '') === 1
    && !empty($changes['core']['deployed']['date']) && !empty($changes['core']['deployed']['subject']),
    'the default window includes deployed Core hash, date and subject');
site_test_assert($changes['core']['deployed']['hash'] === agent_site_git(BASE_DIR, ['rev-parse', 'HEAD']),
    'history describes the checked-out Core');
site_test_assert(file_exists(BASE_DIR . 'ext/.git') ? isset($changes['ext']['deployed']['hash'])
    : isset($changes['ext']['error']), 'Ext history is reported only when its separate Git metadata exists');
foreach ([1, 90] as $days) {
    site_test_assert(agent_site_changes($reader, $days)['days'] === $days, 'window endpoint ' . $days);
}
foreach ([0, 91, '7', 1.5] as $days) {
    site_test_assert(isset(agent_site_changes($reader, $days)['error']), 'invalid windows are refused');
}
site_test_assert(isset(agent_site_changes(['username' => '', 'features' => []])['error']), 'unknown askers cannot read changes');
site_test_assert(isset($definition['tools']['site_changes']) && $definition['tools']['site_changes']['risk'] === 'read_only',
    'the read-only history tool is registered');
try {
    agent_site_git('/tmp/nimbly-no-such-repository', ['rev-parse', 'HEAD']);
    site_test_assert(false, 'unavailable Git is reported');
} catch (RuntimeException) {}

echo "Agent site tests passed.\n";
