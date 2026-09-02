<?php

function t($value) { return $value; }

require_once __DIR__ . '/../modules/admin/lib/get-system-log.php';

function system_log_category_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

system_log_category_assert(
    system_log_category(['type' => 'PHP message', 'message' => 'Nimbly: Spam caught: honeypot filled']) === 'Spam caught',
    'spam events receive an operator-facing category'
);
system_log_category_assert(
    system_log_category(['type' => 'PHP message', 'message' => 'resource create failed: validation failed']) === 'Validation failure',
    'validation events receive their own category'
);
system_log_category_assert(
    system_log_category(['type' => 'PHP Fatal error', 'message' => 'Uncaught Error']) === 'PHP Fatal error',
    'PHP failures preserve their severity'
);
system_log_category_assert(
    system_log_category(['type' => 'PHP message', 'message' => 'Nimbly: scheduler completed']) === 'System event',
    'ordinary messages are system events'
);
system_log_category_assert(system_log_category_label('Spam caught') === 'Spam caught', 'category labels use translations');

$plain = system_log_display('Nimbly: ordinary message');
system_log_category_assert($plain['summary'] === 'Nimbly: ordinary message' && $plain['details'] === '', 'ordinary messages do not get duplicate details');

$structured = system_log_display('Nimbly: Spam caught | details={"resource":"contact-messages","value":"spam"}');
system_log_category_assert($structured['summary'] === 'Nimbly: Spam caught', 'structured event keeps a concise summary');
system_log_category_assert(str_contains($structured['details'], "\n"), 'structured event details are formatted for expansion');
system_log_category_assert(str_contains($structured['details'], 'contact-messages'), 'structured event context remains available');

echo "System log category tests passed.\n";
