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
    'users' => ['.meta' => ['fields' => []]],
    '.agent_conversations' => ['c1' => ['messages' => [
        ['from' => 'user', 'text' => 'hi', 'runs' => ['nimbly' => 'run-1'], 'asker' => 'editor@test'],
    ]]],
];
$site_test_users = ['editor@test' => ['view-articles' => true, 'edit-articles' => true, 'view-.content' => true]];

function load_library($_name): void {}
function load_libraries($_names): void {}
function data_exists($resource, $uuid): bool { return isset($GLOBALS['site_test_data'][$resource][$uuid]); }
function data_read($resource, $uuid = null)
{
    $records = $GLOBALS['site_test_data'][$resource] ?? [];
    unset($records['.meta']);
    return $uuid === null ? $records : ($records[$uuid] ?? null);
}
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

// Scope: records yes, structure and people no; pages and navigation only with custom pages on.
foreach (['articles' => true, '.content' => true, 'users' => false, 'roles' => false, '.config' => false,
    '.agent_runs' => false, 'pages' => false, '.navigation' => false, '../etc' => false] as $resource => $expected) {
    site_test_assert(agent_site_resource_in_scope($resource) === $expected, 'scope of ' . $resource);
}
$site_test_data['.config']['managed_pages']['enabled'] = true;
site_test_assert(agent_site_resource_in_scope('pages') && agent_site_resource_in_scope('.navigation'),
    'pages and navigation are in scope when custom pages are on');
$site_test_data['.config']['managed_pages']['enabled'] = false;

// The asker is found from the run, with the features of their roles.
$context = ['run_uuid' => 'run-1', 'run' => ['event_context' => ['conversation' => 'c1']]];
$asker = agent_site_asker($context);
site_test_assert($asker['username'] === 'editor@test' && agent_site_can($asker, 'edit-articles')
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
site_test_assert(isset(agent_site_records($asker, 'users')['error']), 'users are never readable');
$list = agent_site_records($asker, 'articles');
site_test_assert($list['total'] === 2 && $list['records'][0]['uuid'] === 'a1', 'records are listed');
site_test_assert(agent_site_records($asker, 'articles', '', 'mos')['total'] === 1, 'records can be searched');
site_test_assert(agent_site_records($asker, 'articles', 'a1')['record']['title']['nl'] === 'Hallo mos'
    && agent_site_records($asker, 'articles', 'a1')['admin_page'] === '/nb-admin/articles/a1', 'one record is read whole');

// Docs: the real Nimbly reference, a piece at a time.
site_test_assert(count(agent_nimbly_docs('list', '')['outline']) > 10, 'the docs outline is available');
site_test_assert(str_contains(implode("\n", agent_nimbly_docs('search', 'router_accept')['matches']), 'router_accept'), 'the docs can be searched');
site_test_assert(str_contains(agent_nimbly_docs('section', 'Template Syntax')['section'] ?? '', 'Template Syntax'), 'a docs section can be read');
site_test_assert(isset(agent_nimbly_docs('search', 'zzqq-no-such-term')['hint']), 'a failed search suggests what to try');

// The Nimbly agent definition is valid Core.
$definition = agent_definition('nimbly');
site_test_assert(isset($definition['chat_pipeline']) && !isset($definition['pipeline']), 'Nimbly is a chat-only Core agent');

echo "Agent site tests passed.\n";
