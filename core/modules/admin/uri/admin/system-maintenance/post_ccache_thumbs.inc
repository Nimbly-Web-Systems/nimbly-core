<?php

load_library('cache');
cache_clear('thumb');

function _rmdirr($dir) {
    foreach (glob($dir . '/*') as $file) {
        if (is_dir($file)) {
            _rmdirr($file);
        } else {
            unlink($file);
        }
    }
    @rmdir($dir);
}

_rmdirr($GLOBALS['SYSTEM']['file_base'] . 'ext/static/_thumb_');

load_library('system-messages');
system_message('Cache cleared');


