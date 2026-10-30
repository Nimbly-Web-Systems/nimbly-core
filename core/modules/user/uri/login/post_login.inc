<?php

load_library("redirect");
load_library("get");
load_library("data");
$config = data_read('.config', 'site');
$url = get_variable('login_url') ?? $config['login_url'] ?? '';
redirect($url);