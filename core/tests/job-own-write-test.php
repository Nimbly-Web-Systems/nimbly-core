<?php

require dirname(__DIR__) . '/lib/job.php';

function job_own_write_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

// a job's own save of its record does not queue it again; other jobs and records still do
$GLOBALS['SYSTEM']['running_job'] = ['type' => 'document-cover', 'payload' => ['resource' => 'documents', 'uuid' => 'd1']];
job_own_write_assert(job_is_running_for('document-cover', 'documents', 'd1'), 'the running job is found for its record');
job_own_write_assert(!job_is_running_for('document-cover', 'documents', 'd2'), 'another record queues the job');
job_own_write_assert(!job_is_running_for('send-mail', 'documents', 'd1'), 'another job type is queued');
unset($GLOBALS['SYSTEM']['running_job']);
job_own_write_assert(!job_is_running_for('document-cover', 'documents', 'd1'), 'outside a job everything is queued');

echo "job own write tests passed\n";
