<?php

function repair_htaccess_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

require_once dirname(__DIR__) . '/cli/helpers/repair.php';

$fixture = sys_get_temp_dir() . '/nimbly-repair-htaccess-' . bin2hex(random_bytes(4));
mkdir($fixture, 0755, true);
symlink(dirname(__DIR__), $fixture . '/core');
$base_dir = $fixture . '/';
$file = $base_dir . '.htaccess';

// no file: nothing to repair
repair_htaccess_assert(repair_htaccess_state($base_dir)['action'] === 'skip', 'a missing file is skipped');

// an old file on a subfolder, base without a trailing slash; .env disagrees
file_put_contents($base_dir . '.env', "PEPPER=other\nBASE_PATH=/elsewhere\n");
file_put_contents($file, "SetEnv PEPPER site-pepper\nRewriteEngine on\nRewriteBase /site\nRewriteRule ^ index.php [END]\n");
chmod($file, 0640);

$state = repair_htaccess_state($base_dir);
repair_htaccess_assert($state['action'] === 'write', 'an old file is reported');
repair_htaccess_assert(file_get_contents($file) !== $state['content'], 'reporting does not write');
repair_htaccess_assert(repair_htaccess_apply($state), 'the new file is written');

$written = file_get_contents($file);
repair_htaccess_assert(str_contains($written, "SetEnv PEPPER site-pepper\n"), 'the pepper of the file is kept');
repair_htaccess_assert(str_contains($written, "RewriteBase /site/\n"), 'the rewrite base of the file is kept');
repair_htaccess_assert(str_contains($written, 'RewriteCond %{REQUEST_URI} ^/site/img/(.*)'), 'thumbnails are served from the cache');
repair_htaccess_assert(!str_contains($written, '%%'), 'no placeholder is left');
repair_htaccess_assert(!str_contains($written, 'other') && !str_contains($written, 'elsewhere'), '.env is not read');
repair_htaccess_assert((fileperms($file) & 0777) === 0640, 'the mode is kept');
repair_htaccess_assert(str_contains(file_get_contents($file . '.before-repair'), 'RewriteBase /site'), 'the previous file is kept');

// the thumbnail type is set on the rewritten path, the only one Apache sees by then
repair_htaccess_assert(
    str_contains($written, 'Header set Content-Type "image/webp" "expr=%{REQUEST_URI} =~ m#/ext/static/_thumb_/img/# && -z %{CONTENT_TYPE}"'),
    'cached thumbnails get a type'
);

repair_htaccess_assert(repair_htaccess_state($base_dir)['action'] === 'ok', 'a repaired file needs nothing');

// a site at the root
file_put_contents($file, "SetEnv PEPPER p\nRewriteBase /\n");
$state = repair_htaccess_state($base_dir);
repair_htaccess_assert(str_contains($state['content'], "RewriteBase /\n"), 'root base stays /');
repair_htaccess_assert(str_contains($state['content'], 'RewriteCond %{REQUEST_URI} ^/img/(.*)'), 'root thumbnails rule');

// without a pepper or a base the file is left alone
file_put_contents($file, "RewriteBase /\n");
repair_htaccess_assert(repair_htaccess_state($base_dir)['action'] === 'skip', 'no pepper line: left alone');
file_put_contents($file, "SetEnv PEPPER p\n");
repair_htaccess_assert(repair_htaccess_state($base_dir)['action'] === 'skip', 'no base line: left alone');

unlink($file);
unlink($file . '.before-repair');
unlink($base_dir . '.env');
unlink($fixture . '/core');
rmdir($fixture);
echo "Repair htaccess tests passed.\n";
