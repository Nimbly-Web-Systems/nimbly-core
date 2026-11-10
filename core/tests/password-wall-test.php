<?php

function load_libraries($names) { return true; }
function load_library($name) { return true; }
require __DIR__ . '/../modules/user/lib/login-return.php';
require __DIR__ . '/../lib/password-wall.php';

function test_assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

test_assert_same('lms-artifacts', password_wall_name('lms-artifacts'), 'Rejected a valid wall name.');
foreach (['', 'form/../x', '../users', 'A', '-x', 'a b'] as $name) {
    test_assert_same('', password_wall_name($name), "Accepted wall name $name.");
}
test_assert_same(['a', 'b'], password_wall_features(['features' => 'a, b,']), 'Wall features were not split.');
test_assert_same(false, hash_equals(password_wall_pass(['password' => 'h1']), password_wall_pass(['password' => 'h2'])), 'A new hash kept the old pass.');

echo "password-wall tests passed\n";
