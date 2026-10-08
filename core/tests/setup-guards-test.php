<?php

require dirname(__DIR__) . '/cli/helpers/guards.php';

$templates = dirname(__DIR__) . '/cli/setup/';
$base = sys_get_temp_dir() . '/nimbly-setup-guards-' . bin2hex(random_bytes(6)) . '/';
foreach (['ext/data/.tmp/cache', 'ext/static', 'core/static'] as $dir) {
    mkdir($base . $dir, 0700, true);
}

function setup_guards_assert($condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}

function setup_guards_read(string $base, string $dir): string {
    return trim(file_get_contents($base . $dir . '/.htaccess'));
}

$legacy_deny = "<files *.*>\n    deny from all\n</files>";
$legacy_allow = "<files *.*>\n    allow from all\n</files>";
$custom = "Require ip 10.0.0.0/8\n";

try {
    setup_guards_assert(trim(file_get_contents($templates . 'deny.htaccess')) === 'Require all denied', 'deny template denies every file');
    $allow = trim(file_get_contents($templates . 'allow.htaccess'));
    setup_guards_assert(str_starts_with($allow, 'Require all granted'), 'allow template grants every file');
    setup_guards_assert(!setup_guard_is_legacy($allow), 'allow template is not mistaken for a legacy guard');
    setup_guards_assert(setup_guards_read(dirname(__DIR__, 2) . '/', 'core/static') === $allow, 'tracked core/static guard matches the allow template');

    // Existing site: legacy guards, a legacy allow guard on the cache, one custom guard.
    file_put_contents($base . 'ext/.htaccess', $legacy_deny);
    file_put_contents($base . 'ext/static/.htaccess', $legacy_allow);
    file_put_contents($base . 'ext/data/.tmp/cache/.htaccess', $legacy_allow);
    file_put_contents($base . 'core/.htaccess', $custom);

    $actions = array_column(setup_sync_guards($base, $templates), 'action', 'dir');
    setup_guards_assert($actions === [
        'ext' => 'updated',
        'core' => 'custom',
        'ext/data' => 'created',
        'ext/data/.tmp/cache' => 'updated',
        'ext/static' => 'updated',
        'core/static' => 'created',
    ], 'guards are created, updated or left alone: ' . json_encode($actions));

    foreach (['ext', 'ext/data', 'ext/data/.tmp/cache'] as $dir) {
        setup_guards_assert(setup_guards_read($base, $dir) === 'Require all denied', "$dir is denied");
    }
    foreach (['ext/static', 'core/static'] as $dir) {
        setup_guards_assert(setup_guards_read($base, $dir) === $allow, "$dir is granted");
    }
    setup_guards_assert(file_get_contents($base . 'core/.htaccess') === $custom, 'custom guard is kept');

    $again = array_column(setup_sync_guards($base, $templates), 'action', 'dir');
    setup_guards_assert($again === ['core' => 'custom'], 'second run changes nothing');

    // The starter home page goes only into a site that has no page yet.
    $starter = dirname(__DIR__) . '/cli/setup/starter/';
    mkdir($base . 'fresh/uri', 0755, true);
    file_put_contents($base . 'fresh/uri/.htaccess', 'Require all denied');
    $written = setup_starter_copy($starter, $base . 'fresh/');
    sort($written);
    setup_guards_assert($written === [
        'data/messages/.meta', 'uri/index.tpl', 'uri/main.tpl',
        'uri/message-form.tpl', 'uri/message-login.tpl', 'uri/message.json',
    ], 'a site without pages gets the starter home page, form and resource: ' . json_encode($written));
    $form = json_decode(file_get_contents($base . 'fresh/uri/message.json'), true);
    $meta = json_decode(file_get_contents($base . 'fresh/data/messages/.meta'), true);
    setup_guards_assert(($form['resource'] ?? '') === 'messages' && array_keys($form['fields']) === array_keys($meta['fields']),
        'the starter form saves the fields of the starter resource');
    setup_guards_assert(str_contains(file_get_contents($base . 'fresh/uri/index.tpl'), '[#html#]'), 'the starter page uses the page shell');
    setup_guards_assert(str_contains(file_get_contents($base . 'fresh/uri/main.tpl'), 'data-nb-edit="[#cfield title#]"'), 'the starter title is editable');
    setup_guards_assert(setup_starter_copy($starter, $base . 'fresh/') === [], 'a second run writes nothing');
    mkdir($base . 'taken/uri', 0755, true);
    mkdir($base . 'taken/data/messages', 0755, true);
    setup_guards_assert(!in_array('data/messages/.meta', setup_starter_copy($starter, $base . 'taken/'), true)
        && !is_file($base . 'taken/data/messages/.meta'), 'a resource the site already has is left alone');
    mkdir($base . 'own/uri/about', 0755, true);
    setup_guards_assert(setup_starter_copy($starter, $base . 'own/') === [] && !is_file($base . 'own/uri/index.tpl'),
        'a site with a page of its own is left alone');

    echo "setup guards tests passed\n";
} finally {
    exec('rm -rf ' . escapeshellarg($base));
}
