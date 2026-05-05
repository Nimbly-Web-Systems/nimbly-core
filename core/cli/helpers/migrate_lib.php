<?php

function migrate_lib_roots()
{
    $roots = [
        BASE_DIR . 'core/lib',
        BASE_DIR . 'ext/lib',
    ];

    foreach (glob(BASE_DIR . 'core/modules/*/lib') ?: [] as $root) {
        $roots[] = $root;
    }
    foreach (glob(BASE_DIR . 'ext/modules/*/lib') ?: [] as $root) {
        $roots[] = $root;
    }

    return $roots;
}

function migrate_lib_collect()
{
    $moves = [];
    $skipped = [];

    foreach (migrate_lib_roots() as $root) {
        if (!is_dir($root)) {
            continue;
        }
        foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);
            $from = $dir . '/' . $name . '.php';
            $to = dirname($dir) . '/' . $name . '.php';
            $files = glob($dir . '/*') ?: [];

            if (!file_exists($from)) {
                continue;
            }
            if (count($files) !== 1) {
                $skipped[] = str_replace(BASE_DIR, '', $dir) . ' (support files present)';
                continue;
            }
            if (file_exists($to)) {
                $skipped[] = str_replace(BASE_DIR, '', $dir) . ' (target exists)';
                continue;
            }

            $moves[] = [$from, $to, $dir];
        }
    }

    return [$moves, $skipped];
}

function migrate_lib_apply($moves)
{
    $migrated = 0;

    foreach ($moves as [$from, $to, $dir]) {
        if (!rename($from, $to)) {
            echo "Failed: " . str_replace(BASE_DIR, '', $from) . "\n";
            continue;
        }
        @rmdir($dir);
        $migrated++;
    }

    return $migrated;
}
