<?php

function find_library_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function find_library_test_remove_fixture(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $item) {
        $item_path = $path . '/' . $item;
        if (is_dir($item_path)) {
            find_library_test_remove_fixture($item_path);
        } else {
            unlink($item_path);
        }
    }
    rmdir($path);
}

$fixture = sys_get_temp_dir() . '/nimbly-find-library-test-' . bin2hex(random_bytes(4));
mkdir($fixture . '/core/lib', 0755, true);

$GLOBALS['SYSTEM'] = [
    'env_paths' => ['core'],
    'file_base' => $fixture . '/',
    'modules' => [],
    'sc_stack' => [],
    'uri_path' => '',
];

require_once __DIR__ . '/../lib/find.php';

find_library_test_assert(
    load_library('available_later') === false,
    'initial missing library lookup should fail'
);

file_put_contents(
    $fixture . '/core/lib/available_later.php',
    "<?php\nfunction available_later(): bool { return true; }\n"
);

find_library_test_assert(
    load_library('available_later') !== false,
    'a failed lookup must not be cached as loaded'
);
find_library_test_assert(
    function_exists('available_later') && available_later(),
    'library created after the initial miss should be loaded'
);

find_library_test_remove_fixture($fixture);
echo "Find library tests passed\n";
