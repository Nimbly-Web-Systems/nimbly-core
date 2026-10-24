<?php

/*
 * POST api/v1/.jobs/(id)/run runs a queued job at once, for the page whose
 * save queued it; see job_queued_summary(). The scheduler runs it otherwise.
 */

router_deny();

$parts = $GLOBALS['SYSTEM']['uri_parts'];
if (count($parts) !== 5 || $parts[0] !== 'api' || $parts[1] !== 'v1' || $parts[2] !== '.jobs' || $parts[4] !== 'run') {
    return;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}

load_library('data');
load_library('json');
load_library('job');
$uuid = $parts[3];
$job = data_exists('.jobs', $uuid) ? data_read('.jobs', $uuid) : false;
if (!is_array($job) || !job_may_run($job)) {
    return json_result(['message' => 'RESOURCE_NOT_FOUND'], 404);
}

// a page that is left while the job runs does not stop it
ignore_user_abort(true);
$job = job_run_one($uuid);
return json_result(['job' => [
    'id' => $uuid,
    'type' => (string)($job['type'] ?? ''),
    'status' => (string)($job['status'] ?? ''),
]]);
