<?php

$resource = post_get('resource');
load_library('data');
$count = data_empty($resource);
load_library('json', 'api');
return json_result(['items_deleted' => $count]);