<?php

$request_failure_events = [];
function load_library($library): void {}
function log_system_event(string $event, array $context = []): void {
    global $request_failure_events;
    $request_failure_events[] = ['event' => $event, 'context' => $context];
}
function request_failure_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

require_once __DIR__ . '/../lib/run.php';

$GLOBALS['SYSTEM']['validated_request_route'] = [
    'route_pattern' => '/password-reset/(uuid)/(key)',
    'reason' => 'validated_reset_route_not_accepted',
];
run_log_validated_route_404();

request_failure_assert(count($request_failure_events) === 1, 'logs validated reset 404 immediately');
request_failure_assert(
    $request_failure_events[0]['context']['route_pattern'] === '/password-reset/(uuid)/(key)',
    'logs only the normalized reset route'
);
request_failure_assert(
    !isset($GLOBALS['SYSTEM']['validated_request_route']),
    'clears validated route marker after logging'
);

echo "Request failure tests passed\n";
