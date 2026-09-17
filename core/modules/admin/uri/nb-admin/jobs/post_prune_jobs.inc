<?php

load_library('data');

$cutoff = time() - (30 * 86400);
$pruned = 0;

foreach (data_read('.jobs') as $uuid => $job) {
    if ($uuid === '.meta') {
        continue;
    }
    $status = $job['status'] ?? '';
    if ($status === 'done') {
        $age_at = (int)($job['completed_at'] ?? 0);
    } elseif ($status === 'failed') {
        $age_at = (int)($job['_modified'] ?? 0);
    } else {
        continue;
    }
    if ($age_at === 0 || $age_at > $cutoff) {
        continue;
    }
    data_delete('.jobs', $uuid);
    $pruned++;
}

load_library('system-messages');
system_message($pruned . ' completed or failed job(s) pruned');
