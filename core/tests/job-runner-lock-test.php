<?php

$base = sys_get_temp_dir() . '/nimbly-job-lock-test-' . bin2hex(random_bytes(6)) . '/';
mkdir($base, 0700);
define('BASE_DIR', $base);
require dirname(__DIR__) . '/lib/job.php';

$path = sys_get_temp_dir() . '/nimbly-jobs-' . md5(BASE_DIR) . '.lock';
$holder = fopen($path, 'c');
if (!$holder || !flock($holder, LOCK_EX | LOCK_NB)) {
    throw new RuntimeException('Could not hold the test job lock');
}
try {
    $result = job_run_queued(10);
    if ($result !== ['processed' => 0, 'done' => 0, 'failed' => 0]) {
        throw new RuntimeException('A second runner claimed jobs while the first held the lock');
    }
    echo "job runner lock tests passed\n";
} finally {
    flock($holder, LOCK_UN);
    fclose($holder);
    unlink($path);
    rmdir($base);
}
