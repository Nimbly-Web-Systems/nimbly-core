<?php

// Template, route and library lookups answer from one listing per folder.
// The answers and the ext-before-core, root-before-module order must be the
// ones plain file_exists() gives.

function find_exists_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$fixture = sys_get_temp_dir() . '/nimbly-find-exists-test-' . bin2hex(random_bytes(4));
register_shutdown_function(fn() => exec('rm -rf ' . escapeshellarg($fixture)));

$files = [
    'core/tpl/card/index.tpl',
    'core/tpl/html/head.tpl',
    'core/tpl/both/index.tpl',
    'ext/tpl/both/index.tpl',
    'core/uri/login/index.tpl',
    'core/uri/login/main.tpl',
    'ext/uri/docs/intro/index.tpl',
    'core/lib/get.php',
    'ext/lib/get.php',
    'core/lib/email/email.php',
    'core/modules/user/tpl/badge/index.tpl',
    'ext/modules/shop/tpl/badge/index.tpl',
    'core/modules/user/lib/access.php',
    'core/modules/user/uri/profile/route.inc',
    'core/tpl/0/index.tpl',
];
foreach ($files as $file) {
    @mkdir(dirname($fixture . '/' . $file), 0755, true);
    file_put_contents($fixture . '/' . $file, 'x');
}

$GLOBALS['SYSTEM'] = [
    'env_paths' => ['ext', 'core'],
    'file_base' => $fixture . '/',
    'modules' => ['root' => '/'],
    'sc_stack' => [],
    'uri_path' => '',
    'uri' => 'login',
];

require_once __DIR__ . '/../lib/find.php';

// 1. every lookup answers as file_exists() does
$roots = ['ext/', 'core/', 'ext/modules/shop/', 'core/modules/user/', 'core/modules/none/'];
$rels = [
    'tpl/card/index.tpl', 'tpl/both/index.tpl', 'tpl/html/head.tpl', 'tpl/html/nope.tpl', 'tpl/nope/index.tpl',
    'tpl/0/index.tpl', 'tpl/badge/index.tpl', 'uri/login/index.tpl', 'uri/login/nope.tpl', 'uri//index.tpl',
    'uri/docs/intro/index.tpl', 'uri/docs/nope/index.tpl', 'uri/profile/route.inc', 'lib/get.php', 'lib/nope.php',
    'lib/email/email.php', 'lib/access.php', 'tpl/../lib/get.php', 'tpl/./card/index.tpl', 'tpl', 'nope/a/b',
    'tpl/Card/index.tpl', '/tpl/card/index.tpl', 'tpl//card/index.tpl',
];
foreach ([1, 2] as $pass) { // the second pass is answered from the listings
    foreach ($roots as $root) {
        foreach ($rels as $rel) {
            find_exists_test_assert(
                find_exists($fixture . '/' . $root, $rel) === file_exists($fixture . '/' . $root . $rel),
                "pass {$pass}: {$root}{$rel} differs from file_exists()"
            );
        }
    }
}

// 2. lookup order is unchanged
find_exists_test_assert(find_path('both', 'tpl') === $fixture . '/ext/tpl/both/index.tpl', 'ext does not win over core');
find_exists_test_assert(find_path('card', 'tpl') === $fixture . '/core/tpl/card/index.tpl', 'core template not found');
find_exists_test_assert(find_path('badge', 'tpl') === $fixture . '/ext/modules/shop/tpl/badge/index.tpl', 'ext module does not win over core module');
find_exists_test_assert(find_path('nope', 'tpl') === false, 'a missing template was found');
find_exists_test_assert(find_uri('login') === $fixture . '/core/uri/login/index.tpl', 'route not found');
find_exists_test_assert(find_uri('docs/intro') === $fixture . '/ext/uri/docs/intro/index.tpl', 'nested route not found');
find_exists_test_assert(find_uri('profile', 'route.inc') === $fixture . '/core/modules/user/uri/profile/route.inc', 'module route not found');
find_exists_test_assert(find_template('main') === $fixture . '/core/uri/login/main.tpl', 'template next to the current route not found');
find_exists_test_assert(find_template('head', 'tpl/html') === $fixture . '/core/tpl/html/head.tpl', 'template in a named folder not found');
find_exists_test_assert(find_library('get') === $fixture . '/ext/lib/get.php', 'ext library does not win over core');
find_exists_test_assert(find_library('access') === $fixture . '/core/modules/user/lib/access.php', 'module library not found');
find_exists_test_assert(find_library('email/email') === $fixture . '/core/lib/email/email.php', 'library in a subfolder not found');

// 3. a file added to a folder that is already listed is found at once
file_put_contents($fixture . '/core/uri/login/footer.tpl', 'x');
find_exists_test_assert(find_template('footer') === $fixture . '/core/uri/login/footer.tpl', 'a new file in a known folder was not found');

// 4. a new folder is found after find_forget()
mkdir($fixture . '/ext/tpl/fresh');
file_put_contents($fixture . '/ext/tpl/fresh/index.tpl', 'x');
find_forget();
find_exists_test_assert(find_path('fresh', 'tpl') === $fixture . '/ext/tpl/fresh/index.tpl', 'a new folder was not found after find_forget()');

// 5. a name no module has costs no disk lookup once the folders are listed
find_path('nope', 'tpl');
$listed = count($GLOBALS['SYSTEM']['find_listings']);
for ($i = 0; $i < 50; $i++) {
    find_path('nope-' . $i, 'tpl');
}
find_exists_test_assert(count($GLOBALS['SYSTEM']['find_listings']) === $listed, 'missing names listed folders again');

echo "find exists tests passed\n";
