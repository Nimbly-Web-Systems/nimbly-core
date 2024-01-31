<?php

load_library('files-unused', 'api');
load_libraries(['data', 'system-messages']);

set_time_limit(0);

$unused = get_unused_files();

foreach ($unused as $file_uuid) {
    data_delete('.files', $file_uuid);
    data_delete('.files_meta', $file_uuid);
}

system_message('Deleted ' . count($unused) . ' unused media files');