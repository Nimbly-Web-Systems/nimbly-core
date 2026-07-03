<?php

load_library('data');

$cutoff = time() - (30 * 86400);
$pruned = 0;

foreach (data_read('.jobs') as $uuid => $job) {
    if ($uuid === '.meta' || ($job['status'] ?? '') !== 'done') {
        continue;
    }
    $completed_at = (int)($job['completed_at'] ?? 0);
    if ($completed_at === 0 || $completed_at > $cutoff) {
        continue;
    }
    data_delete('.jobs', $uuid);
    $pruned++;
}

load_library('system-messages');
system_message($pruned . ' completed job(s) pruned');
