<?php

$root = dirname(__DIR__, 2) . '/';
$tmp = sys_get_temp_dir() . '/nimbly-scheduler-logrotate-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700, true);
$environment = [
    'NIMBLY_SCHEDULER_CONFIG' => $tmp . '/projects.json',
    'NIMBLY_SCHEDULER_BIN' => $tmp . '/nimbly-scheduler',
    'NIMBLY_SCHEDULER_CRON' => $tmp . '/cron.d/nimbly-scheduler',
    'NIMBLY_SCHEDULER_LOG' => $tmp . '/scheduler.log',
    'NIMBLY_SCHEDULER_LOGROTATE' => $tmp . '/logrotate.d/nimbly-scheduler',
    'NIMBLY_SCHEDULER_LOCK' => $tmp . '/scheduler.lock',
];
$user = function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'www-data';

$run = function (array $arguments) use ($root, $environment): string {
    $process = proc_open(array_merge([PHP_BINARY, $root . 'core/cli/nimbly.php'], $arguments),
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $environment);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not launch scheduler command');
    }
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Scheduler command failed: ' . implode(' ', $arguments) . "\n" . $output);
    }
    return $output;
};

try {
    $run(['scheduler:cron:install', '--user=' . $user]);
    $rule = (string)@file_get_contents($environment['NIMBLY_SCHEDULER_LOGROTATE']);
    if (!str_starts_with($rule, $environment['NIMBLY_SCHEDULER_LOG'] . " {\n")
        || !preg_match('/^    create 0644 ' . preg_quote($user, '/') . ' \S+$/m', $rule)
        || !str_contains($rule, "    weekly\n")
        || str_contains($rule, 'copytruncate')) {
        throw new RuntimeException('Cron install did not write the rotation rule for the scheduler log');
    }
    if (!is_file($environment['NIMBLY_SCHEDULER_CRON'])) {
        throw new RuntimeException('Cron install did not write the cron file');
    }

    $status = $run(['scheduler:cron:status']);
    if (!str_contains($status, 'rotate:  installed ' . $environment['NIMBLY_SCHEDULER_LOGROTATE'])) {
        throw new RuntimeException('Cron status does not report the rotation rule');
    }

    $run(['scheduler:cron:remove']);
    if (file_exists($environment['NIMBLY_SCHEDULER_LOGROTATE']) || file_exists($environment['NIMBLY_SCHEDULER_CRON'])) {
        throw new RuntimeException('Cron remove left the cron file or the rotation rule behind');
    }
    $status = $run(['scheduler:cron:status']);
    if (!str_contains($status, 'rotate:  missing ')) {
        throw new RuntimeException('Cron status reports a removed rotation rule as installed');
    }
} finally {
    exec('rm -rf ' . escapeshellarg($tmp));
}

echo "scheduler logrotate test passed\n";
