<?php

load_library('cache');
cache_clear('sessions');

@mkdir($GLOBALS['SYSTEM']['file_base'] . 'ext/data/.tmp/sessions', 0750, true);

load_library('system-messages');
system_message('All sessions killed');