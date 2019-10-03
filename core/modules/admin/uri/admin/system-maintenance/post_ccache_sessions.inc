<?php

load_library('cache');
cache_clear('sessions');

load_library('system-messages');
system_message('Cache cleared');