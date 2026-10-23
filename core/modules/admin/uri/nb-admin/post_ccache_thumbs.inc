<?php

load_library('util');
$path = $GLOBALS['SYSTEM']['file_base'] . 'ext/static/_thumb_';
_rmdirr($path);
@mkdir($path, 0750, true);
// thumbnails of private files, see thumbnail_create()
_rmdirr($GLOBALS['SYSTEM']['data_base'] . '/.tmp/cache/_thumb_');
load_library('system-messages');
system_message('Thumnbails cleared');