<?php

define('BASE_DIR', realpath(__DIR__ . '/../..') . '/');
require BASE_DIR . 'core/cli/cli_bootstrap.inc';

function cli_bootstrap_test_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

cli_bootstrap_test_assert(function_exists('find_template'), 'CLI bootstrap loads the find library');
cli_bootstrap_test_assert(function_exists('run_buffered'), 'CLI bootstrap loads the run library');
cli_bootstrap_test_assert(function_exists('plain_text'), 'CLI bootstrap loads the utility library');

load_libraries(['email', 'set']);
set_variable('name', 'CLI Test');
set_variable('reset-url', 'https://example.test/reset');

$result = email_result([
    'service' => 'resend',
    'recipient' => 'test@example.com',
    'subject' => 'CLI render test',
    'tpl' => 'email-password-reset',
    'from' => 'sender@example.com',
    'request' => fn() => ['success' => true, 'body' => ['id' => 'test_id'], 'error' => null],
]);

cli_bootstrap_test_assert(!empty($result['success']), 'CLI bootstrap renders a template email');
echo "CLI bootstrap tests passed.\n";
