<?php

/**
 * Runs due scheduled commands.
 *
 * Usage:
 *   php core/cli/nimbly.php schedule:run
 *   php core/cli/nimbly.php schedule:run --dry-run
 */

if (php_sapi_name() !== 'cli') {
    die("schedule.php must be run from the command line.\n");
}

if (!defined('BASE_DIR')) {
    define('BASE_DIR', realpath(__DIR__ . '/../..') . '/');
}

$GLOBALS['SYSTEM'] = [
    'file_base' => BASE_DIR,
    'env_paths' => ['ext', 'core'],
    'modules'   => ['root' => '/'],
    'variables' => [],
    'uri'       => '',
];

require_once BASE_DIR . 'core/lib/find.php';

$env_file = BASE_DIR . '.env';
if (file_exists($env_file)) {
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        [$key, $val] = array_map('trim', explode('=', $line, 2) + [1 => '']);
        $_SERVER[$key] = $val;
    }
}

load_library('data');

$dry_run = in_array('--dry-run', $argv, true);
$schedule = schedule_load();
if (empty($schedule)) {
    echo "No scheduled commands configured.\n";
    exit(0);
}

$lock_file = sys_get_temp_dir() . '/nimbly-schedule-' . md5(BASE_DIR) . '.lock';
$lock = fopen($lock_file, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "Schedule already running.\n";
    exit(0);
}

$state = schedule_state_read();
$now = time();
$ran = 0;
$skipped = 0;
$failed = 0;

foreach ($schedule as $index => $task) {
    $task = schedule_normalize_task($task, $index);
    if ($task === null) {
        $skipped++;
        continue;
    }

    $task_state = $state['tasks'][$task['id']] ?? [];
    if (!schedule_task_due($task, $task_state, $now)) {
        $skipped++;
        printf("skip %-24s not due\n", $task['id']);
        continue;
    }

    if ($dry_run) {
        $ran++;
        printf("due  %-24s %s\n", $task['id'], $task['command']);
        continue;
    }

    printf("run  %-24s %s\n", $task['id'], $task['command']);
    $started_at = time();
    $exit_code = schedule_run_command($task['command']);
    $finished_at = time();

    $state['tasks'][$task['id']] = [
        'command' => $task['command'],
        'last_run_at' => $started_at,
        'last_finished_at' => $finished_at,
        'last_exit_code' => $exit_code,
    ];
    schedule_state_write($state);

    $ran++;
    if ($exit_code !== 0) {
        $failed++;
    }
}

printf("Scheduled commands: ran %d, skipped %d, failed %d\n", $ran, $skipped, $failed);
exit($failed > 0 ? 1 : 0);

function schedule_load()
{
    $schedule_file = schedule_file_path();
    if ($schedule_file === '') {
        return [];
    }

    $schedule = require $schedule_file;
    return is_array($schedule) ? $schedule : [];
}

function schedule_file_path()
{
    $override = trim((string)schedule_env_value('SCHEDULE_FILE'));
    if ($override !== '') {
        $override_file = str_starts_with($override, '/') ? $override : BASE_DIR . ltrim($override, '/');
        return file_exists($override_file) ? $override_file : '';
    }

    $env = schedule_env_name();
    if ($env !== '') {
        $env_file = BASE_DIR . 'ext/cli/schedule.' . $env . '.inc';
        if (file_exists($env_file)) {
            return $env_file;
        }
    }

    foreach ([
        BASE_DIR . 'ext/cli/schedule.inc',
        BASE_DIR . 'core/cli/schedule.inc',
    ] as $schedule_file) {
        if (file_exists($schedule_file)) {
            return $schedule_file;
        }
    }

    return '';
}

function schedule_env_name()
{
    $env = schedule_env_value('SCHEDULE_ENV');
    if ($env === '') {
        $env = schedule_env_value('APP_ENV');
    }
    if ($env === '') {
        $env = schedule_env_value('NIMBLY_ENV');
    }
    $env = strtolower(trim((string)$env));

    if ($env === '') {
        return '';
    }

    $aliases = [
        'production' => 'prod',
        'staging' => 'stage',
        'development' => 'dev',
        'local' => 'dev',
    ];
    $env = $aliases[$env] ?? $env;

    if (!preg_match('/^[a-z0-9_-]+$/', $env)) {
        return '';
    }

    return $env;
}

function schedule_env_value($key)
{
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }

    return '';
}

function schedule_state_read()
{
    if (!data_exists('.config', 'schedule')) {
        return ['tasks' => []];
    }

    $state = data_read('.config', 'schedule');
    if (!is_array($state)) {
        return ['tasks' => []];
    }

    if (!isset($state['tasks']) || !is_array($state['tasks'])) {
        $state['tasks'] = [];
    }

    return $state;
}

function schedule_state_write($state)
{
    if (!data_exists('.config')) {
        data_create('.config', '', []);
    }

    $state['_modified'] = time();
    return data_create('.config', 'schedule', $state);
}

function schedule_normalize_task($task, $index)
{
    if (!is_array($task) || empty($task['command'])) {
        return null;
    }

    $command = trim((string)$task['command']);
    if ($command === '') {
        return null;
    }

    $task['command'] = $command;
    $task['id'] = trim((string)($task['id'] ?? ''));
    if ($task['id'] === '') {
        $task['id'] = 'task-' . $index . '-' . substr(md5($command), 0, 8);
    }

    return $task;
}

function schedule_task_due($task, $task_state, $now)
{
    $last_run = (int)($task_state['last_run_at'] ?? 0);

    if (($task['every'] ?? '') === 'minute') {
        return date('YmdHi', $last_run) !== date('YmdHi', $now);
    }

    if (($task['every'] ?? '') === 'hour') {
        return date('YmdH', $last_run) !== date('YmdH', $now);
    }

    if (($task['every'] ?? '') === 'day') {
        return date('Ymd', $last_run) !== date('Ymd', $now);
    }

    if (!empty($task['daily_at'])) {
        return schedule_due_today_at((string)$task['daily_at'], $last_run, $now);
    }

    if (!empty($task['weekly_at']) && !empty($task['day'])) {
        return schedule_due_weekly_at((string)$task['day'], (string)$task['weekly_at'], $last_run, $now);
    }

    return false;
}

function schedule_due_today_at($time, $last_run, $now)
{
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
        return false;
    }

    if (date('Ymd', $last_run) === date('Ymd', $now)) {
        return false;
    }

    return date('H:i', $now) >= $time;
}

function schedule_due_weekly_at($day, $time, $last_run, $now)
{
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
        return false;
    }

    $day = strtolower($day);
    $today = strtolower(date('l', $now));
    if ($day !== $today) {
        return false;
    }

    if (date('oW', $last_run) === date('oW', $now)) {
        return false;
    }

    return date('H:i', $now) >= $time;
}

function schedule_run_command($command)
{
    $parts = schedule_command_parts($command);
    if (empty($parts)) {
        return 1;
    }

    $args = array_map('escapeshellarg', array_merge([
        PHP_BINARY,
        BASE_DIR . 'core/cli/nimbly.php',
    ], $parts));

    passthru(implode(' ', $args), $exit_code);
    return (int)$exit_code;
}

function schedule_command_parts($command)
{
    $parts = str_getcsv($command, ' ');
    $parts = array_values(array_filter(array_map('trim', $parts), fn($part) => $part !== ''));
    if (in_array('schedule:run', $parts, true)) {
        return [];
    }
    return $parts;
}
