<?php

load_library('util');
$path = $GLOBALS['SYSTEM']['file_base'] . 'ext/static/_thumb_';
_rmdirr($path);
@mkdir($path, 0750, true);
load_library('system-messages');
system_message('Thumnbails cleared');