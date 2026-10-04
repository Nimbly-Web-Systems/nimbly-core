<?php

/**
 * Directory guards written by system:setup.
 *
 * Everything under ext/ and core/ is denied; only the static folders, which
 * the root .htaccess rewrites into, are granted.
 */

function setup_guard_dirs(): array {
    return [
        'ext'                 => 'deny',
        'core'                => 'deny',
        'ext/data'            => 'deny',
        'ext/data/.tmp/cache' => 'deny',
        'ext/static'          => 'allow',
        'core/static'         => 'allow',
    ];
}

// Guards written by earlier setup versions: <files *.*> with allow/deny from all.
function setup_guard_is_legacy(string $content): bool {
    $normalized = strtolower(preg_replace('/\s+/', ' ', trim($content)));
    return (bool) preg_match('#^<files \*\.\*> (allow|deny) from all </files>$#', $normalized);
}

/**
 * Creates missing guards and replaces legacy ones. A guard with any other
 * content is site-specific and is left alone.
 *
 * Returns a list of ['dir' => ..., 'action' => created|updated|custom].
 */
function setup_sync_guards(string $base_dir, string $template_dir): array {
    $result = [];
    foreach (setup_guard_dirs() as $dir => $type) {
        if (!is_dir($base_dir . $dir)) {
            continue;
        }
        $dst = $base_dir . $dir . '/.htaccess';
        $expected = file_get_contents($template_dir . $type . '.htaccess');
        if (!file_exists($dst)) {
            file_put_contents($dst, $expected);
            $result[] = ['dir' => $dir, 'action' => 'created'];
            continue;
        }
        $current = file_get_contents($dst);
        if (trim($current) === trim($expected)) {
            continue;
        }
        if (setup_guard_is_legacy($current)) {
            file_put_contents($dst, $expected);
            $result[] = ['dir' => $dir, 'action' => 'updated'];
            continue;
        }
        $result[] = ['dir' => $dir, 'action' => 'custom'];
    }
    return $result;
}
