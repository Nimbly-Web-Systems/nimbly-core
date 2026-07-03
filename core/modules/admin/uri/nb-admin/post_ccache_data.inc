<?php

load_library('util');
$path = $GLOBALS['SYSTEM']['file_base'] . 'ext/data/.tmp/cache/_data';
_rmdirr($path);
@mkdir($path, 0750, true);
load_library('system-messages');
system_message('Data cache cleared');
