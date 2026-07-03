<?php

load_library('job');
$result = job_run_queued(10);

load_library('system-messages');
system_message('Processed ' . $result['processed'] . ' job(s): ' . $result['done'] . ' done, ' . $result['failed'] . ' failed');
