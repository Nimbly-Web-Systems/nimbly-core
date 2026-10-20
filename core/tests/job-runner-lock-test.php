<?php

$base = sys_get_temp_dir() . '/nimbly-job-lock-test-' . bin2hex(random_bytes(6)) . '/';
mkdir($base . 'ext/data/.state', 0700, true);
define('BASE_DIR', $base);
require dirname(__DIR__) . '/lib/job.php';

$path = BASE_DIR . 'ext/data/.state/jobs.lock';
$holder = fopen($path, 'c');
if (!$holder || !flock($holder, LOCK_EX | LOCK_NB)) {
    throw new RuntimeException('Could not hold the test job lock');
}
try {
    $result = job_run_queued(10);
    if ($result !== ['processed' => 0, 'done' => 0, 'failed' => 0]) {
        throw new RuntimeException('A second runner claimed jobs while the first held the lock');
    }
    job_mark_due(200);
    job_mark_due(100);
    job_mark_due(300);
    if (file_get_contents(job_due_path()) !== '100') {
        throw new RuntimeException('Due marker does not keep the earliest job');
    }
    job_run_queued(10);
    if (!is_file(job_due_path())) {
        throw new RuntimeException('A runner without the lock cleared the due marker');
    }
    echo "job runner lock tests passed\n";
} finally {
    flock($holder, LOCK_UN);
    fclose($holder);
    unlink($path);
    @unlink(job_due_path());
    rmdir($base . 'ext/data/.state');
    rmdir($base . 'ext/data');
    rmdir($base . 'ext');
    rmdir($base);
}
