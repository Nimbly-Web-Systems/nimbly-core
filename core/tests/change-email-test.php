<?php

// A new email address is stored next to the one in use and only becomes the
// user's address through the mailed link: the right link, within a day, and
// while no other user has the address.

$test_users = $test_jobs = $test_logs = [];
$test_uuid = 0;

function load_library($library): void {}
function load_libraries($libraries): void {}
function log_system($message): void { global $test_logs; $test_logs[] = $message; }
function log_system_event(string $event, array $context = []): void { log_system($event); }
function generate_uuid(): string { global $test_uuid; return 'token-' . ++$test_uuid; }
function url_absolute($path): string { return 'https://example.com/' . ltrim($path, '/'); }
function data_exists($resource, $uuid): bool { global $test_users; return isset($test_users[$uuid]); }
function data_read($resource, $uuid) { global $test_users; return $test_users[$uuid] ?? null; }
function data_update($resource, $uuid, $updates)
{
    global $test_users;
    if (!isset($test_users[$uuid])) { return false; }
    return $test_users[$uuid] = array_merge($test_users[$uuid], $updates);
}
function find_user_by_email($email)
{
    global $test_users;
    foreach ($test_users as $user) {
        if (strtolower($user['email']) === strtolower($email)) { return $user; }
    }
    return false;
}
function job_enqueue($type, $payload = [])
{
    global $test_jobs;
    $test_jobs[] = ['type' => $type, 'payload' => $payload];
    return 'job-' . count($test_jobs);
}

require dirname(__DIR__) . '/modules/user/lib/change-email.php';

function change_email_assert($condition, $message): void
{
    if (!$condition) { fwrite(STDERR, 'FAIL: ' . $message . "\n"); exit(1); }
}

$test_users = [
    'u1' => ['uuid' => 'u1', 'name' => 'Ada', 'email' => 'ada@example.com'],
    'u2' => ['uuid' => 'u2', 'name' => 'Bob', 'email' => 'bob@example.com'],
];

// Asking stores the address and mails a link; the address in use stays.
change_email_assert(change_email_request($test_users['u1'], ' ada@new.example ') === true, 'a request is stored');
change_email_assert($test_users['u1']['email'] === 'ada@example.com', 'the address in use stays until the link is followed');
change_email_assert($test_users['u1']['new_email'] === 'ada@new.example', 'the asked address is stored trimmed');
$job = end($test_jobs);
change_email_assert($job['type'] === 'change-email' && $job['payload']['email'] === 'ada@new.example', 'the link is mailed to the new address');
$link = explode('/', substr($job['payload']['change_email_url'], strlen('https://example.com/change-email/')));
change_email_assert($link[0] === 'u1' && $link[1] === md5('ada@new.example') && $link[2] === $test_users['u1']['change_email_token'], 'the link names the user, the address and the token');

// A link that is not the mailed one changes nothing.
change_email_assert(change_email_confirm('u1', $link[1], 'wrong') === false, 'a wrong token is refused');
change_email_assert(change_email_confirm('u1', md5('other@example.com'), $link[2]) === false, 'another address is refused');
change_email_assert(change_email_confirm('u2', $link[1], $link[2]) === false, 'another user is refused');
change_email_assert(change_email_confirm('nobody', $link[1], $link[2]) === false, 'an unknown user is refused');
change_email_assert($test_users['u1']['email'] === 'ada@example.com', 'refused links leave the address alone');

// A second request replaces the first link.
change_email_request($test_users['u1'], 'ada@newer.example');
change_email_assert(change_email_confirm('u1', $link[1], $link[2]) === false, 'an older link stops working after a new request');
$job = end($test_jobs);
$link = explode('/', substr($job['payload']['change_email_url'], strlen('https://example.com/change-email/')));

// A link older than a day is refused.
$test_users['u1']['change_email_token_at'] = time() - 86401;
change_email_assert(change_email_confirm('u1', $link[1], $link[2]) === false, 'a link older than a day is refused');
$test_users['u1']['change_email_token_at'] = time() - 60;

// An address another user took in the meantime is refused.
$test_users['u2']['email'] = 'ada@newer.example';
change_email_assert(change_email_confirm('u1', $link[1], $link[2]) === false, 'an address another user has is refused');
$test_users['u2']['email'] = 'bob@example.com';

// The mailed link changes the address, once.
$stored = change_email_confirm('u1', $link[1], $link[2]);
change_email_assert(is_array($stored) && $stored['email'] === 'ada@newer.example' && $stored['old_email'] === 'ada@example.com', 'the mailed link changes the address');
change_email_assert($test_users['u1']['change_email_token'] === '' && $test_users['u1']['new_email'] === '', 'the link is used up');
change_email_assert(change_email_confirm('u1', $link[1], $link[2]) === false, 'a link works once');

echo "change email tests passed\n";
