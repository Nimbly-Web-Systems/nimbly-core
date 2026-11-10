<?php

require __DIR__ . '/../modules/user/lib/login-return.php';

function test_assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

foreach (['lms-artifacts/design-01', 'nl/opschalingstafel', 'img/(id)'] as $path) {
    test_assert_same(true, login_return_is_safe($path), "Rejected local path $path.");
}
foreach (['', 'https://evil.example', '//evil.example', '/evil', '\\evil', 'a/../b', 'a//b', 'a?x=1', "a\nb"] as $path) {
    test_assert_same(false, login_return_is_safe($path), "Accepted unsafe path $path.");
}

$_GET['return'] = '//evil.example';
test_assert_same('', login_return_value(), 'Unsafe return reached the redirect.');
$_POST['return'] = 'nl/projects';
test_assert_same('nl/projects', login_return_value(), 'Posted return was not used.');

echo "login-return tests passed\n";
