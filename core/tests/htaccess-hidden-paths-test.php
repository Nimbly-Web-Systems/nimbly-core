<?php

function htaccess_hidden_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$fixture = sys_get_temp_dir() . '/nimbly-htaccess-hidden-' . bin2hex(random_bytes(4));
mkdir($fixture, 0755, true);
symlink(dirname(__DIR__), $fixture . '/core');
define('BASE_DIR', $fixture . '/');

require_once dirname(__DIR__) . '/cli/helpers/repair.php';

$current = repair_htaccess_render(BASE_DIR, 'pepper', '/');
$rule = 'RewriteRule (^|/)\.(?!well-known/) - [F]';
htaccess_hidden_assert(
    str_contains($current, "RewriteCond %{REQUEST_URI} !/api/v1/\\.\n" . $rule),
    'template refuses hidden paths at any depth except the API dot resources'
);
htaccess_hidden_assert(
    strpos($current, $rule) < strpos($current, 'RewriteRule ^ index.php [END]'),
    'template refuses hidden paths before the front controller rewrite'
);

preg_match('~^RewriteRule (\S+) - \[F\]$~m', substr($current, strpos($current, $rule)), $match);
$pattern = '~' . $match[1] . '~';
foreach (['.env', 'backend/.env', '.git/config', 'a/b/.aws/credentials', '.well-knownx'] as $path) {
    htaccess_hidden_assert(preg_match($pattern, $path) === 1, "refuses {$path}");
}
foreach (['.well-known/acme-challenge/x', 'img/photo.jpg', 'nl/stories/a.b', 'app.css'] as $path) {
    htaccess_hidden_assert(preg_match($pattern, $path) === 0, "keeps {$path}");
}

file_put_contents($fixture . '/.htaccess', str_replace(
    "RewriteCond %{REQUEST_URI} !/api/v1/\\.\n" . $rule,
    'RewriteRule ^\.(?!well-known/) - [F]',
    $current
));
htaccess_hidden_assert(
    repair_htaccess_state(BASE_DIR)['action'] === 'write',
    'an .htaccess with only the root-level rule is rewritten'
);
file_put_contents($fixture . '/.htaccess', $current);
htaccess_hidden_assert(
    repair_htaccess_state(BASE_DIR)['action'] === 'ok',
    'an .htaccess from the current template needs no repair'
);

unlink($fixture . '/.htaccess');
unlink($fixture . '/core');
rmdir($fixture);
echo "Htaccess hidden path tests passed\n";
