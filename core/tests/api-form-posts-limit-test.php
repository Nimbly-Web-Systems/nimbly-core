<?php

function load_library($_name) {}
function honeypot_field_name() { return 'website'; }
function log_system($_message) {}

require_once __DIR__ . '/../modules/api/lib/api.php';

function form_posts_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$base = sys_get_temp_dir() . '/nimbly-form-posts-' . getmypid() . '/';
$GLOBALS['SYSTEM']['file_base'] = $base;
$_SERVER['REMOTE_ADDR'] = '192.0.2.10';

form_posts_assert(api_form_posts('contact-messages') === [], 'a new address has sent nothing');
for ($i = 0; $i < API_FORM_POSTS_MAX; $i++) {
    api_form_posted('contact-messages');
}
form_posts_assert(count(api_form_posts('contact-messages')) === API_FORM_POSTS_MAX, 'each post from the address is counted');
form_posts_assert(api_form_posts('newsletter') === [], 'another form has its own count');

$_SERVER['REMOTE_ADDR'] = '192.0.2.11';
form_posts_assert(api_form_posts('contact-messages') === [], 'another address has its own count');

$_SERVER['REMOTE_ADDR'] = '192.0.2.10';
file_put_contents(api_form_posts_path('contact-messages'), (time() - API_FORM_POSTS_SECONDS - 1) . "\n" . time() . "\n");
form_posts_assert(count(api_form_posts('contact-messages')) === 1, 'posts older than the window no longer count');

array_map('unlink', glob($base . 'ext/data/.tmp/form-posts/*'));
echo "API form posts limit tests passed.\n";
