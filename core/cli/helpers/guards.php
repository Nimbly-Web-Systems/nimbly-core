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
 * With $apply false nothing is written and the list says what would happen.
 */
function setup_sync_guards(string $base_dir, string $template_dir, bool $apply = true): array {
    $result = [];
    foreach (setup_guard_dirs() as $dir => $type) {
        if (!is_dir($base_dir . $dir)) {
            continue;
        }
        $dst = $base_dir . $dir . '/.htaccess';
        $expected = file_get_contents($template_dir . $type . '.htaccess');
        if (!file_exists($dst)) {
            if ($apply) {
                file_put_contents($dst, $expected);
            }
            $result[] = ['dir' => $dir, 'action' => 'created'];
            continue;
        }
        $current = file_get_contents($dst);
        if (trim($current) === trim($expected)) {
            continue;
        }
        if (setup_guard_is_legacy($current)) {
            if ($apply) {
                file_put_contents($dst, $expected);
            }
            $result[] = ['dir' => $dir, 'action' => 'updated'];
            continue;
        }
        $result[] = ['dir' => $dir, 'action' => 'custom'];
    }
    return $result;
}

/**
 * Copies the starter pages into a site that has no route yet, and returns
 * the files it wrote. A site with anything under ext/uri is left alone, and
 * so is a resource the site already has.
 */
function setup_starter_copy(string $starter_dir, string $ext_dir): array
{
    $starter_dir = rtrim($starter_dir, '/');
    $ext_dir = rtrim($ext_dir, '/');
    $uri_dir = $ext_dir . '/uri';
    if (!is_dir($uri_dir) || count(array_diff(scandir($uri_dir) ?: [], ['.', '..', '.htaccess'])) > 0) {
        return [];
    }
    $written = [];
    foreach (glob($starter_dir . '/uri/*.{tpl,json}', GLOB_BRACE) ?: [] as $source) {
        $name = 'uri/' . basename($source);
        if (copy($source, $ext_dir . '/' . $name)) {
            $written[] = $name;
        }
    }
    foreach (glob($starter_dir . '/data/*/.meta') ?: [] as $source) {
        $name = 'data/' . basename(dirname($source));
        if (file_exists($ext_dir . '/' . $name)) {
            continue;
        }
        if (mkdir($ext_dir . '/' . $name, 0755, true) && copy($source, $ext_dir . '/' . $name . '/.meta')) {
            $written[] = $name . '/.meta';
        }
    }
    return $written;
}
