<?php

load_library("redirect");
load_library("get");
load_library("data");
$config = data_read('.config', 'site');
$url = $config['login_url'] ?? '';
redirect($url);