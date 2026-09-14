<?php

$root = dirname(__DIR__, 2) . '/';
$tmp = sys_get_temp_dir() . '/nimbly-scheduler-cap-' . bin2hex(random_bytes(6));
$cap = 2;
$project_names = ['p1', 'p2', 'p3', 'p4', 'p5'];

foreach ($project_names as $name) {
    mkdir($tmp . '/' . $name . '/core/cli', 0700, true);
    file_put_contents($tmp . '/' . $name . '/core/cli/nimbly.php', <<<'PHP'
<?php
$base = dirname(__DIR__, 2);
$id = basename($base);
file_put_contents(dirname($base) . '/events', "start $id\n", FILE_APPEND | LOCK_EX);
usleep(700000);
file_put_contents(dirname($base) . '/events', "end $id\n", FILE_APPEND | LOCK_EX);
PHP);
}

$config = ['projects' => array_combine(
    $project_names,
    array_map(fn ($name) => ['path' => $tmp . '/' . $name], $project_names)
)];
file_put_contents($tmp . '/projects.json', json_encode($config));

$semaphore_dir = $tmp . '/slots';
mkdir($semaphore_dir, 0700, true);
$environment = [
    'NIMBLY_SCHEDULER_CONFIG' => $tmp . '/projects.json',
    'NIMBLY_SCHEDULER_LOG' => $tmp . '/scheduler.log',
    'NIMBLY_SCHEDULER_LOCK' => $tmp . '/scheduler.lock',
    'NIMBLY_SCHEDULER_MAX_CONCURRENCY' => (string)$cap,
    'NIMBLY_SCHEDULER_SEMAPHORE_DIR' => $semaphore_dir,
];

try {
    $process = proc_open([PHP_BINARY, $root . 'core/cli/nimbly.php', 'scheduler:run'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $environment);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not launch scheduler');
    }
    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    // 5 projects x 2 modes (jobs, schedule) = 10 fixture invocations expected.
    $expected_events = count($project_names) * 2 * 2; // start + end per invocation
    $deadline = microtime(true) + 15;
    do {
        $lines = is_file($tmp . '/events') ? file($tmp . '/events', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
        if (count($lines) >= $expected_events) {
            break;
        }
        usleep(100000);
    } while (microtime(true) < $deadline);

    if (count($lines) !== $expected_events) {
        throw new RuntimeException('Expected ' . $expected_events . ' events, got ' . count($lines));
    }

    $running = 0;
    $peak = 0;
    foreach ($lines as $line) {
        if (str_starts_with($line, 'start ')) {
            $running++;
            $peak = max($peak, $running);
        } elseif (str_starts_with($line, 'end ')) {
            $running--;
        }
    }

    if ($peak > $cap) {
        throw new RuntimeException("Concurrency cap violated: {$peak} workers ran at once, cap was {$cap}");
    }
    if ($peak < $cap) {
        throw new RuntimeException("Cap never reached: peak concurrency was only {$peak}, expected {$cap}");
    }

    echo "scheduler concurrency cap tests passed\n";
} finally {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
        if ($entry->isDir()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($tmp);
}
