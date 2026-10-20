<?php

$root = dirname(__DIR__, 2) . '/';
require $root . 'core/lib/maintenance.php';
function maintenance_test_assert($condition, $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
// Exercise the host registry without invoking any registered project.
$source = file_get_contents($root . 'core/cli/scheduler_orchestrator.php');
eval(substr($source, strpos($source, 'function scheduler_orchestrator_config_path')));
maintenance_test_assert(count(scheduler_orchestrator_projects(['projects' => [
    'site' => ['path' => '/fixture', 'enabled' => false],
]])) === 1, 'legacy host enabled flag cannot suppress registered project maintenance');
maintenance_test_assert(!isset(scheduler_orchestrator_default_config()['default_delay_after_seconds']), 'no inter-project delay setting');
$now = time();
$state = ['tasks' => []];
maintenance_test_assert(count(maintenance_health($state, $now)) === count(maintenance_tasks()), 'never-run tasks unhealthy');
foreach (maintenance_tasks() as $task) {
    $state['tasks'][$task['id']] = ['last_success_at' => $now, 'last_exit_code' => 0];
}
maintenance_test_assert(!maintenance_health($state, $now), 'successful empty work healthy');
$legacy_state = $state;
unset($legacy_state['tasks']['jobs-prune']['last_success_at']);
$legacy_state['tasks']['jobs-prune']['last_finished_at'] = $now;
maintenance_test_assert(!maintenance_health($legacy_state, $now), 'legacy successful state remains healthy');
maintenance_test_assert(maintenance_health($state, $now + 121) === ['agent-chat' => 'overdue', 'jobs-run' => 'overdue'], 'per-task freshness');
$state['tasks']['sessions-prune']['last_exit_code'] = 1;
maintenance_test_assert(maintenance_health($state, $now) === ['sessions-prune' => 'failed'], 'failed run not hidden by earlier success');

$tmp = sys_get_temp_dir() . '/nimbly-maintenance-host-' . bin2hex(random_bytes(6));
mkdir($tmp . '/ext/data/.state', 0700, true);
$state_dir = $tmp . '/ext/data/.state/';
try {
    maintenance_test_assert(scheduler_orchestrator_due_lanes($tmp, $now) === ['jobs', 'schedule'], 'older core keeps both lanes');
    file_put_contents($state_dir . 'schedule-due', (string)($now + 600));
    $chat = fopen($state_dir . 'chat-worker.lock', 'c');
    flock($chat, LOCK_EX);
    maintenance_test_assert(scheduler_orchestrator_due_lanes($tmp, $now) === [], 'nothing due starts nothing');
    file_put_contents($state_dir . 'jobs-due', (string)$now);
    maintenance_test_assert(scheduler_orchestrator_due_lanes($tmp, $now) === ['jobs'], 'due job starts the jobs lane');
    $host_state = ['host' => true, 'tasks' => $state['tasks']];
    $host_state['tasks']['sessions-prune']['last_exit_code'] = 0;
    maintenance_test_assert(maintenance_health($host_state, $now, $state_dir) === [], 'host: running worker and fresh job healthy');
    file_put_contents($state_dir . 'jobs-due', (string)($now - 180));
    maintenance_test_assert(array_keys(maintenance_health($host_state, $now, $state_dir)) === ['jobs-run'], 'host: waiting job overdue');
    unlink($state_dir . 'jobs-due');
    flock($chat, LOCK_UN);
    fclose($chat);
    touch($state_dir . 'chat-worker.lock', $now - 180);
    file_put_contents($state_dir . 'schedule-due', (string)$now);
    maintenance_test_assert(scheduler_orchestrator_due_lanes($tmp, $now) === ['jobs', 'schedule', 'chat'], 'due schedule and stopped worker start');
    maintenance_test_assert(array_keys(maintenance_health($host_state, $now, $state_dir)) === ['agent-chat'], 'host: stopped chat worker overdue');
} finally {
    array_map('unlink', glob($state_dir . '*'));
    rmdir($state_dir);
    rmdir($tmp . '/ext/data');
    rmdir($tmp . '/ext');
    rmdir($tmp);
}

$tmp = sys_get_temp_dir() . '/nimbly-maintenance-test-' . bin2hex(random_bytes(6));
foreach (['core/cli/helpers', 'ext/cli', 'ext/data/.state', 'ext/data/.config'] as $dir) { mkdir($tmp . '/' . $dir, 0700, true); }
copy($root . 'core/cli/nimbly.php', $tmp . '/core/cli/nimbly.php');
symlink($root . 'core/lib', $tmp . '/core/lib');
symlink($root . 'core/modules', $tmp . '/core/modules');
foreach (['schedule.php', 'schedule_status.php', 'cli_bootstrap.inc'] as $file) { symlink($root . 'core/cli/' . $file, $tmp . '/core/cli/' . $file); }
symlink($root . 'core/cli/helpers/output.php', $tmp . '/core/cli/helpers/output.php');
file_put_contents($tmp . '/.env', "APP_ENV=stage\n");
$commands = [];
foreach (['agent:chat', 'sessions:prune', 'jobs:run', 'jobs:prune', 'history:prune', 'agent:prune', 'stats:rollup', 'app:test'] as $command) {
    $commands[$command] = ['file' => 'ext/cli/task.php', 'desc' => 'fixture'];
}
file_put_contents($tmp . '/ext/cli/commands.php', '<?php return ' . var_export($commands, true) . ';');
file_put_contents($tmp . '/ext/cli/task.php', '<?php file_put_contents(BASE_DIR . "calls", $argv[1] . "\n", FILE_APPEND); exit(is_file(BASE_DIR . "fail") ? 1 : 0);');
function maintenance_test_run(string $tmp, string $args, string $env = ''): array {
    $output = [];
    exec($env . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp . '/core/cli/nimbly.php') . ' ' . $args . ' 2>&1', $output, $code);
    return [$code, implode("\n", $output)];
}
try {
    file_put_contents($tmp . '/ext/cli/schedule.stage.inc', '<?php return [];');
    [$code, $output] = maintenance_test_run($tmp, 'schedule:run --dry-run');
    maintenance_test_assert($code === 0 && substr_count($output, 'due  ') === count(maintenance_tasks()), 'empty ext schedule still includes maintenance');
    maintenance_test_assert(!file_exists($tmp . '/calls') && !file_exists($tmp . '/ext/data/.state/schedule'), 'dry-run no commands or state');
    [$code, $output] = maintenance_test_run($tmp, 'schedule:run');
    maintenance_test_assert($code === 0, 'overridden implementations succeed: ' . $output);
    maintenance_test_assert(file($tmp . '/calls', FILE_IGNORE_NEW_LINES) === ['agent:chat','sessions:prune','jobs:run','jobs:prune','history:prune','agent:prune','stats:rollup'], 'each ext implementation runs once');
    maintenance_test_assert(maintenance_test_run($tmp, 'schedule:status')[0] === 0, 'status observes successful completion');
    maintenance_test_assert((int)file_get_contents($tmp . '/ext/data/.state/schedule-due') > time(), 'next due time written');
    file_put_contents($tmp . '/calls', '');
    unlink($tmp . '/ext/data/.state/schedule');
    [$code, $output] = maintenance_test_run($tmp, 'schedule:run', 'NIMBLY_SCHEDULER_HOST=1 ');
    maintenance_test_assert($code === 0 && !array_intersect(file($tmp . '/calls', FILE_IGNORE_NEW_LINES), ['agent:chat', 'jobs:run']), 'host leaves chat and jobs to the host scheduler');
    unlink($tmp . '/ext/data/.state/schedule');
    file_put_contents($tmp . '/ext/cli/schedule.stage.inc', '<?php return ' . var_export([
        ['id' => 'replacement', 'command' => 'jobs:run 500', 'every' => 'minute'],
        ['id' => 'sessions-prune', 'command' => 'app:test', 'every' => 'minute'],
        ['id' => 'app-task', 'command' => 'app:test', 'every' => 'minute'],
    ], true) . ';');
    file_put_contents($tmp . '/calls', '');
    [$code, $output] = maintenance_test_run($tmp, 'schedule:run');
    maintenance_test_assert($code === 0 && count(file($tmp . '/calls')) === count(maintenance_tasks()) + 1, 'duplicates removed, app task preserved');
    unlink($tmp . '/ext/data/.state/schedule');
    file_put_contents($tmp . '/ext/cli/schedule.stage.inc', '<?php syntax error');
    file_put_contents($tmp . '/calls', '');
    [$code, $output] = maintenance_test_run($tmp, 'schedule:run');
    maintenance_test_assert($code !== 0 && count(file($tmp . '/calls')) === count(maintenance_tasks()), 'malformed app schedule cannot prevent mandatory tasks');
    unlink($tmp . '/ext/data/.state/schedule');
    file_put_contents($tmp . '/ext/cli/schedule.stage.inc', '<?php return [];');
    touch($tmp . '/fail');
    maintenance_test_assert(maintenance_test_run($tmp, 'schedule:run')[0] !== 0, 'failed implementations return failure');
    maintenance_test_assert(maintenance_test_run($tmp, 'schedule:status')[0] !== 0, 'failed implementations unhealthy');
    echo "maintenance integration tests passed\n";
} finally {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
        if ($entry->isDir() && !$entry->isLink()) { rmdir($entry->getPathname()); } else { unlink($entry->getPathname()); }
    }
    rmdir($tmp);
}
