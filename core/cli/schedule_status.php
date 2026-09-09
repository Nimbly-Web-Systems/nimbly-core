<?php

require_once __DIR__ . '/cli_bootstrap.inc';
require_once BASE_DIR . 'core/lib/maintenance.php';
$path = BASE_DIR . 'ext/data/.state/schedule';
$state = is_file($path) ? json_decode(file_get_contents($path), true) : [];
$issues = maintenance_health(is_array($state) ? $state : []);
echo json_encode(['healthy' => !$issues, 'tasks' => $issues], JSON_THROW_ON_ERROR) . "\n";
exit($issues ? 1 : 0);
