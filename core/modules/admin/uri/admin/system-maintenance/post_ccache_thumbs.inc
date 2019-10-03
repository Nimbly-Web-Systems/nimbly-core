<?php

load_library('cache');
cache_clear('thumb');

load_library('system-messages');
system_message('Cache cleared');