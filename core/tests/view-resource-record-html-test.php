<?php

$GLOBALS['SYSTEM'] = [
    'uri_base' => '/jereis',
];

function load_library($_name) {}

require_once __DIR__ . '/../lib/base-url.php';
require_once __DIR__ . '/../modules/admin/lib/view-resource-record.php';

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function assert_not_contains(string $needle, string $haystack, string $message): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$stored = '<p><img src="/old-base/img/43d37e6341ec5e5b45a7fd95a0e6fdba/1200w"></p>';
$rendered = view_resource_record_value('html', $stored);

assert_contains('/jereis/img/43d37e6341ec5e5b45a7fd95a0e6fdba/1200w', $rendered, 'admin record view normalizes stale stored base path');
assert_not_contains('/old-base/', $rendered, 'admin record view drops stale stored base path');

echo "View resource record HTML tests passed.\n";
