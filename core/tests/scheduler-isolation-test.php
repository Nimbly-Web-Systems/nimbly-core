<?php

$root = dirname(__DIR__, 2) . '/';
$tmp = sys_get_temp_dir() . '/nimbly-scheduler-isolation-' . bin2hex(random_bytes(6));
foreach (['slow', 'fast'] as $name) {
    mkdir($tmp . '/' . $name . '/core/cli', 0700, true);
    file_put_contents($tmp . '/' . $name . '/core/cli/nimbly.php', <<<'PHP'
<?php
$base = dirname(__DIR__, 2);
$command = $argv[1] ?? '';
file_put_contents($base . '/calls', $command . "\n", FILE_APPEND | LOCK_EX);
if (basename($base) === 'slow' && $command === 'schedule:run') {
    sleep(4);
    file_put_contents($base . '/done', 'yes');
}
PHP);
}
$config = ['projects' => [
    'slow' => ['path' => $tmp . '/slow'],
    'fast' => ['path' => $tmp . '/fast'],
]];
file_put_contents($tmp . '/projects.json', json_encode($config));
$environment = [
    'NIMBLY_SCHEDULER_CONFIG' => $tmp . '/projects.json',
    'NIMBLY_SCHEDULER_LOG' => $tmp . '/scheduler.log',
    'NIMBLY_SCHEDULER_LOCK' => $tmp . '/scheduler.lock',
];

try {
    for ($run = 0; $run < 2; $run++) {
        $start = microtime(true);
        $process = proc_open([PHP_BINARY, $root . 'core/cli/nimbly.php', 'scheduler:run'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $environment);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not launch scheduler');
        }
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 0 || microtime(true) - $start > 2.0) {
            throw new RuntimeException('Slow project blocked the host scheduler');
        }
    }

    $deadline = microtime(true) + 3;
    do {
        $slow_calls = is_file($tmp . '/slow/calls') ? file($tmp . '/slow/calls') : [];
        $fast_calls = is_file($tmp . '/fast/calls') ? file($tmp . '/fast/calls') : [];
        if (substr_count(implode('', $slow_calls), 'jobs:run') >= 2
            && in_array("jobs:run\n", $fast_calls, true)) {
            break;
        }
        usleep(50000);
    } while (microtime(true) < $deadline);
    if (substr_count(implode('', $slow_calls), 'jobs:run') < 2
        || !in_array("jobs:run\n", $fast_calls, true)
        || is_file($tmp . '/slow/done')) {
        throw new RuntimeException('Jobs did not run while another project was busy');
    }
    echo "scheduler isolation tests passed\n";
} finally {
    sleep(5);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
        if ($entry->isDir()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($tmp);
}
