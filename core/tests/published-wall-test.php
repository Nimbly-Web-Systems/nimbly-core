<?php

$dir = sys_get_temp_dir() . '/nimbly-published-wall-' . bin2hex(random_bytes(5));
mkdir($dir, 0700);
file_put_contents($dir . '/walled.tpl', "[#published-wall#]\n[#html#]");
file_put_contents($dir . '/open.tpl', "[#html#]");

function load_libraries($names) {}
function find_uri($path) {
    global $dir;
    return ['nl/nieuw' => $dir . '/walled.tpl', 'nl/klaar' => $dir . '/walled.tpl', 'nl/over' => $dir . '/open.tpl'][$path] ?? false;
}
function data_read($resource, $uuid) {
    return ['nl_klaar' => ['published' => true], 'nl_nieuw' => ['page_title' => 'Nieuw']][$uuid] ?? null;
}
require __DIR__ . '/../lib/published-wall.php';

function published_wall_test_assert($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
try {
    published_wall_test_assert(published_wall_is_published(['published' => true]), 'published page counted as unpublished');
    published_wall_test_assert(!published_wall_is_published(['published' => false]), 'unpublished page counted as published');
    published_wall_test_assert(!published_wall_is_published(null), 'page without settings counted as published');
    published_wall_test_assert(published_wall_hides('nl/nieuw'), 'walled page without published was not hidden');
    published_wall_test_assert(published_wall_hides('/nl/nieuw#agenda'), 'section link to a walled page was not hidden');
    published_wall_test_assert(!published_wall_hides('nl/klaar'), 'published walled page was hidden');
    published_wall_test_assert(!published_wall_hides('nl/over'), 'page without wall was hidden');
    published_wall_test_assert(!published_wall_hides('nl/onbekend'), 'unknown path was hidden');
    echo "published wall tests passed\n";
} finally {
    array_map('unlink', glob($dir . '/*'));
    rmdir($dir);
}
