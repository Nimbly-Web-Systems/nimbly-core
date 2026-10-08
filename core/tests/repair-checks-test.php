<?php

function repair_checks_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$fixture = sys_get_temp_dir() . '/nimbly-repair-checks-' . bin2hex(random_bytes(4));
mkdir($fixture . '/ext/data', 0755, true);
mkdir($fixture . '/ext/static', 0755, true);
symlink(dirname(__DIR__), $fixture . '/core');
define('BASE_DIR', $fixture . '/');

require_once dirname(__DIR__) . '/cli/cli_bootstrap.inc';
require_once dirname(__DIR__) . '/cli/helpers/repair.php';
load_library('data');
repair_checks_assert(str_starts_with(data_path('users', 'x'), BASE_DIR), 'the data layer writes inside the fixture');

// directory guards: missing and old-form guards are written, own content is kept
file_put_contents(BASE_DIR . 'ext/.htaccess', "<files *.*>\ndeny from all\n</files>\n");
file_put_contents(BASE_DIR . 'ext/static/.htaccess', "# site rule\n");
$state = repair_guards_state(BASE_DIR);
repair_checks_assert($state['action'] === 'write', 'old and missing guards are reported');
repair_checks_assert(str_contains($state['message'], 'ext (old form)') && str_contains($state['message'], 'ext/data (missing)'), 'the report names them');
repair_checks_assert(!file_exists(BASE_DIR . 'ext/data/.htaccess'), 'reporting does not write');
repair_checks_assert(repair_guards_apply($state), 'guards are written');
repair_checks_assert(str_contains(file_get_contents(BASE_DIR . 'ext/data/.htaccess'), 'Require all denied'), 'the data folder is denied');
repair_checks_assert(file_get_contents(BASE_DIR . 'ext/static/.htaccess') === "# site rule\n", 'a guard with own content is kept');
repair_checks_assert(repair_guards_state(BASE_DIR)['action'] === 'ok', 'repaired guards need nothing');

// ext/.gitignore: missing rules are appended, existing lines stay
repair_checks_assert(repair_gitignore_state(BASE_DIR)['action'] === 'skip', 'a missing .gitignore is skipped');
file_put_contents(BASE_DIR . 'ext/.gitignore', ".env\n/static/_thumb_/\n/data/.jobs/*");
$state = repair_gitignore_state(BASE_DIR);
repair_checks_assert($state['action'] === 'write', 'missing ignore rules are reported');
repair_checks_assert(array_keys($state['missing']) === ['job queue', 'scheduler state', 'agent chats and runs'], 'a half-present rule counts as missing');
repair_checks_assert(repair_gitignore_apply($state), 'ignore rules are written');
$written = file_get_contents(BASE_DIR . 'ext/.gitignore');
repair_checks_assert(str_starts_with($written, ".env\n/static/_thumb_/\n/data/.jobs/*\n"), 'existing lines stay');
repair_checks_assert(str_contains($written, "!/data/.jobs/.meta\n") && str_contains($written, "/data/.agent_runs/*\n"), 'missing lines are added');
repair_checks_assert(repair_gitignore_state(BASE_DIR)['action'] === 'ok', 'a repaired .gitignore needs nothing');

// .config upsert
repair_checks_assert(repair_config_upsert_state()['action'] === 'skip', 'no .config resource: skipped');
data_create_resource('.config', ['fields' => false]);
$state = repair_config_upsert_state();
repair_checks_assert($state['action'] === 'write', '.config without upsert is reported');
repair_checks_assert(repair_config_upsert_apply($state), 'upsert is written');
repair_checks_assert(data_read('.config', '.meta')['fields'] === false, 'the rest of the meta stays');
repair_checks_assert(repair_config_upsert_state()['action'] === 'ok', 'a repaired .config needs nothing');

// core routes
repair_checks_assert(repair_core_routes_state()['action'] === 'skip', 'no .routes resource: skipped');
data_create_resource('.routes', ['fields' => false]);
$state = repair_core_routes_state();
repair_checks_assert($state['action'] === 'write', 'a missing core route is reported');
repair_checks_assert(repair_core_routes_apply($state), 'the route is registered');
repair_checks_assert(data_read('.routes', md5('nb-admin/roles/(id)'))['order'] === 200, 'with its order');
repair_checks_assert(repair_core_routes_state()['action'] === 'ok', 'registered routes need nothing');

// users email index
repair_checks_assert(repair_users_email_state()['action'] === 'skip', 'no users resource: skipped');
data_create_resource('users', ['fields' => false]);
data_create('users', 'u1', ['email' => 'a@example.com']);
$state = repair_users_email_state();
repair_checks_assert($state['action'] === 'write', 'users without an email index are reported');
repair_checks_assert(repair_users_email_apply($state), 'the index is built');
$meta = data_read('users', '.meta');
repair_checks_assert(in_array('email', $meta['index'], true) && in_array('email', $meta['unique'], true), 'email is indexed and unique');
repair_checks_assert(repair_users_email_state()['action'] === 'ok', 'indexed users need nothing');

exec('rm -rf ' . escapeshellarg($fixture));
echo "Repair checks tests passed.\n";
