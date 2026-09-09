<?php

require_once __DIR__ . '/cli_bootstrap.inc';
load_library('session');
$counts = session_prune(in_array('--dry-run', $argv, true));
echo json_encode($counts, JSON_THROW_ON_ERROR) . "\n";
exit($counts['failed'] > 0 ? 1 : 0);
