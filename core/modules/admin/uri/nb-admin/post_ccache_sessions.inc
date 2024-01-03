<?php

load_library('util');
$path = $GLOBALS['SYSTEM']['file_base'] . 'ext/data/.tmp/sessions';
_rmdirr($path);
@mkdir($path, 0750, true);
load_library('system-messages');
system_message('All sessions killed');