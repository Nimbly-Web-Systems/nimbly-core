<?php

function get_variable($key, $default = null)
{
    return $default;
}

require_once dirname(__DIR__) . '/modules/images/lib/thumbnail.php';

function thumbnail_test_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function thumbnail_test_remove_directory($directory)
{
    if (!is_dir($directory)) {
        return;
    }

    foreach (scandir($directory) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $directory . '/' . $entry;
        if (is_dir($path)) {
            thumbnail_test_remove_directory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($directory);
}

if (!extension_loaded('gd')) {
    echo "Thumbnail cache tests skipped: GD is unavailable.\n";
    exit(0);
}

$fixture = sys_get_temp_dir() . '/nimbly-thumbnail-cache-' . getmypid();
$uuid = '45c5e473de77584161ea240ada52ab5d';
$source_directory = $fixture . '/data/.files';
mkdir($source_directory, 0755, true);

$source = imagecreatetruecolor(128, 80);
$background = imagecolorallocate($source, 245, 242, 237);
$accent = imagecolorallocate($source, 212, 63, 43);
imagefill($source, 0, 0, $background);
imagefilledrectangle($source, 0, 0, 127, 31, $accent);
imagejpeg($source, $source_directory . '/' . $uuid, 90);
imagedestroy($source);

$GLOBALS['SYSTEM'] = [
    'data_base' => $fixture . '/data',
    'file_base' => $fixture . '/',
    'request_uri' => 'img/' . $uuid . '/64w',
];

$cache_path = thumbnail_create($uuid, 64, 0, 'w', 'jpg');
thumbnail_test_assert(is_file($cache_path), 'thumbnail was not written to the cache');

$contents = file_get_contents($cache_path);
thumbnail_test_assert(substr($contents, 0, 2) === "\xff\xd8", 'cached JPEG has no start marker');
thumbnail_test_assert(substr($contents, -2) === "\xff\xd9", 'cached JPEG has no end marker');
thumbnail_test_assert(glob($cache_path . '.tmp.*') === [], 'temporary thumbnail was not removed');

$first_hash = hash_file('sha256', $cache_path);
$second_path = thumbnail_create($uuid, 64, 0, 'w', 'jpg');
thumbnail_test_assert($second_path === $cache_path, 'cache hit returned a different path');
thumbnail_test_assert(hash_file('sha256', $cache_path) === $first_hash, 'cache hit rewrote the thumbnail');

thumbnail_test_remove_directory($fixture);
echo "Thumbnail cache tests passed.\n";
