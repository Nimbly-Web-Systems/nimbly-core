<?php

$GLOBALS['SYSTEM'] = [
    'file_base' => dirname(__DIR__, 2) . '/',
    'variables' => [],
];

require_once dirname(__DIR__) . '/lib/data.php';

function data_atomic_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function data_atomic_remove_fixture($directory)
{
    if (!is_dir($directory)) {
        return;
    }

    foreach (array_diff(scandir($directory) ?: [], ['.', '..']) as $entry) {
        $path = $directory . '/' . $entry;
        if (is_dir($path)) {
            data_atomic_remove_fixture($path);
        } else {
            unlink($path);
        }
    }
    rmdir($directory);
}

$fixture = sys_get_temp_dir() . '/nimbly-data-atomic-' . bin2hex(random_bytes(4));
mkdir($fixture, 0755, true);
$file = $fixture . '/record';
$original = json_encode(['version' => 'original'], JSON_UNESCAPED_UNICODE);
$replacement = json_encode([
    'version' => 'replacement',
    'body' => str_repeat('Atomic data publication ', 4096),
], JSON_UNESCAPED_UNICODE);
file_put_contents($file, $original);
chmod($file, 0640);

$bytes = _data_write_file_atomically($file, $replacement);
data_atomic_assert($bytes === strlen($replacement), 'atomic write returned an incorrect byte count');
data_atomic_assert(file_get_contents($file) === $replacement, 'destination does not contain the complete replacement');
data_atomic_assert((fileperms($file) & 0777) === 0640, 'atomic replacement did not preserve file permissions');
data_atomic_assert(glob($fixture . '/.record.tmp.*') === [], 'temporary data file was not removed');
data_atomic_assert(
    _data_write_file_atomically($fixture . '/missing/record', $replacement) === false,
    'atomic write unexpectedly created a missing destination directory'
);

if (function_exists('pcntl_fork')) {
    $version_a = json_encode(['version' => 'a', 'body' => str_repeat('a', 262144)]);
    $version_b = json_encode(['version' => 'b', 'body' => str_repeat('b', 262144)]);
    _data_write_file_atomically($file, $version_a);

    $writer = pcntl_fork();
    data_atomic_assert($writer !== -1, 'could not start concurrent data writer');
    if ($writer === 0) {
        for ($iteration = 0; $iteration < 100; $iteration++) {
            $contents = $iteration % 2 === 0 ? $version_b : $version_a;
            if (_data_write_file_atomically($file, $contents) === false) {
                exit(1);
            }
        }
        exit(0);
    }

    do {
        $published = file_get_contents($file);
        data_atomic_assert(
            $published === $version_a || $published === $version_b,
            'concurrent reader observed a partial data file'
        );
        $wait_result = pcntl_waitpid($writer, $writer_status, WNOHANG);
    } while ($wait_result === 0);

    data_atomic_assert(
        $wait_result === $writer && pcntl_wexitstatus($writer_status) === 0,
        'concurrent data writer failed'
    );
}

$data_source = file_get_contents(dirname(__DIR__) . '/lib/data.php');
data_atomic_assert(
    str_contains($data_source, 'return _data_write_file_atomically($cache_file, $json_data);'),
    'derived data cache does not use atomic publication'
);
data_atomic_assert(
    str_contains($data_source, 'if (_data_write_file_atomically($file, $json_data) !== false)'),
    'record writes do not use atomic publication'
);

data_atomic_remove_fixture($fixture);
echo "Atomic data write tests passed.\n";
