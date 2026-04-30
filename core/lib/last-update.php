<?php

// @doc gets timestamp of last modified source file
function last_update_sc($params) {

    load_library('base-path');
    $bdir = base_path_sc();
    
    return max(
        find_latest_time($bdir . 'ext/lib'), 
        find_latest_time($bdir . 'ext/modules'), 
        find_latest_time($bdir . 'ext/tpl'), 
        find_latest_time($bdir . 'ext/uri'),
        find_latest_time($bdir . 'core/lib'), 
        find_latest_time($bdir . 'core/modules'), 
        find_latest_time($bdir . 'core/tpl'), 
        find_latest_time($bdir . 'core/uri')
    );
}

function find_latest_time(string $dir): ?string {
    $result = 0;
    foreach (scandir($dir) as $path) { 
        if (in_array($path, ['.', '..'], true)) {
            continue;
        }

        $f = $dir . DIRECTORY_SEPARATOR . $path;
        $f_max = is_dir($f)? find_latest_time($f) : filemtime($f);

        if ($f_max > $result) {
            $result = $f_max;
        }
    }
    return $result;
} 