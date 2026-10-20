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
// The host scheduler starts the chat worker and jobs:run itself, only when they have work.
$host = getenv('NIMBLY_SCHEDULER_HOST') === '1';
require_once BASE_DIR . 'core/lib/maintenance.php';
$schedule = schedule_all_tasks($host);

// In the site's own data, so containers that share it share the lock.
$lock_dir = BASE_DIR . 'ext/data/.state';
if (!is_dir($lock_dir)) {
    @mkdir($lock_dir, 0775, true);
}
$lock_file = $lock_dir . '/schedule.lock';
$lock = fopen($lock_file, 'ce'); // close-on-exec: detached children must not inherit the lock
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "Schedule already running.\n";
    exit(0);
}

$state = schedule_state_read();
$now = time();
$ran = 0;
$skipped = 0;
$failed = 0;
$tasks = [];

// The health route reports what this PHP lacks; it can differ from the web PHP.
load_library('health');
$php_missing = health_missing_extensions();
if (!$dry_run && ($php_missing !== ($state['php_missing'] ?? []) || $host !== !empty($state['host']))) {
    $state['php_missing'] = $php_missing;
    $state['host'] = $host;
    schedule_state_write($state);
}
if ($php_missing !== []) {
    printf("PHP is missing: %s\n", implode(', ', $php_missing));
    $failed++;
}

foreach ($schedule as $index => $task) {
    $task = schedule_normalize_task($task, $index);
    if ($task === null) {
        $skipped++;
        continue;
    }
    $tasks[] = $task;

    $task_state = $state['tasks'][$task['id']] ?? [];
    if (!schedule_task_due($task, $task_state, $now, schedule_site_phase())) {
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
        'last_success_at' => $exit_code === 0 ? $finished_at : ($task_state['last_success_at'] ?? 0),
    ];
    schedule_state_write($state);

    $ran++;
    if ($exit_code !== 0) {
        $failed++;
    }
}

if (!$dry_run) {
    file_put_contents($lock_dir . '/schedule-due', (string)schedule_next_due($tasks, $state, $now, schedule_site_phase()), LOCK_EX);
}

printf("Scheduled commands: ran %d, skipped %d, failed %d\n", $ran, $skipped, $failed);
exit($failed > 0 ? 1 : 0);

function schedule_all_tasks(bool $host = false): Generator
{
    $mandatory = maintenance_tasks();
    foreach ($mandatory as $task) {
        if (!$host || !in_array($task['id'], ['agent-chat', 'jobs-run'], true)) {
            yield $task;
        }
    }
    $ids = array_column($mandatory, 'id');
    $commands = array_map(fn($task) => explode(' ', $task['command'])[0], $mandatory);
    foreach (schedule_load() as $task) {
        if (is_array($task) && (in_array($task['id'] ?? '', $ids, true)
            || in_array(schedule_command_parts($task['command'] ?? '')[0] ?? '', $commands, true))) {
            continue;
        }
        yield $task;
    }
}

/**
 * First minute a task is due. Without one, the site's next quarter-hour mark on its own phase,
 * so a changed schedule is read again soon after a deploy and sites do not recheck together.
 */
function schedule_next_due(array $tasks, array $state, int $now, int $phase = 0): int
{
    $minute = $now - $now % 60;
    for ($at = $minute + 60; ; $at += 60) {
        if ((intdiv($at, 60) - $phase) % 15 === 0) {
            return $at;
        }
        foreach ($tasks as $task) {
            if (schedule_task_due($task, $state['tasks'][$task['id']] ?? [], $at, $phase)) {
                return $at;
            }
        }
    }
}

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
    if (data_exists('.state', 'schedule')) {
        $state = data_read('.state', 'schedule');
    } elseif (data_exists('.config', 'schedule')) {
        $state = data_read('.config', 'schedule');
    } else {
        $state = ['tasks' => []];
    }

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
    if (!data_exists('.state')) {
        data_create('.state', '', []);
    }

    $state['_modified'] = time();
    return data_create('.state', 'schedule', $state);
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
    if (!empty($task['timezone'])) {
        try {
            new DateTimeZone((string)$task['timezone']);
        } catch (Throwable) {
            return null;
        }
    }
    $task['id'] = trim((string)($task['id'] ?? ''));
    if ($task['id'] === '') {
        $task['id'] = 'task-' . $index . '-' . substr(md5($command), 0, 8);
    }

    return $task;
}

/**
 * Minute of the day this site starts its periodic tasks, so the sites on one server do not all
 * start at :00. Interval tasks run that many minutes into their interval (modulo its length);
 * daily_at and weekly_at keep their exact time.
 */
function schedule_site_phase(): int
{
    return crc32(BASE_DIR) % 1440;
}

function schedule_task_due($task, $task_state, $now, int $phase = 0)
{
    $last_run = (int)($task_state['last_run_at'] ?? 0);
    $timezone = schedule_task_timezone($task);

    if (array_key_exists('every_minutes', $task)) {
        $minutes = filter_var($task['every_minutes'], FILTER_VALIDATE_INT);
        if ($minutes === false || $minutes < 1) {
            return false;
        }
        if ($last_run <= 0) {
            return true;
        }
        $shift = ($phase % $minutes) * 60;
        return intdiv($last_run - $shift, $minutes * 60) !== intdiv((int)$now - $shift, $minutes * 60);
    }

    if (($task['every'] ?? '') === 'minute') {
        return schedule_date('YmdHi', $last_run, $timezone) !== schedule_date('YmdHi', $now, $timezone);
    }

    if (($task['every'] ?? '') === 'hour') {
        $shift = ($phase % 60) * 60;
        return schedule_date('YmdH', $last_run - $shift, $timezone) !== schedule_date('YmdH', $now - $shift, $timezone);
    }

    if (($task['every'] ?? '') === 'day') {
        $shift = $phase * 60;
        return schedule_date('Ymd', $last_run - $shift, $timezone) !== schedule_date('Ymd', $now - $shift, $timezone);
    }

    if (!empty($task['daily_at'])) {
        return schedule_due_today_at((string)$task['daily_at'], $last_run, $now, $timezone);
    }

    if (!empty($task['weekly_at']) && !empty($task['day'])) {
        return schedule_due_weekly_at((string)$task['day'], (string)$task['weekly_at'], $last_run, $now, $timezone);
    }

    return false;
}

function schedule_due_today_at($time, $last_run, $now, ?DateTimeZone $timezone = null)
{
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
        return false;
    }

    if (schedule_date('Ymd', $last_run, $timezone) === schedule_date('Ymd', $now, $timezone)) {
        return false;
    }

    return schedule_date('H:i', $now, $timezone) >= $time;
}

function schedule_due_weekly_at($day, $time, $last_run, $now, ?DateTimeZone $timezone = null)
{
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
        return false;
    }

    $day = strtolower($day);
    $today = strtolower(schedule_date('l', $now, $timezone));
    if ($day !== $today) {
        return false;
    }

    if (schedule_date('oW', $last_run, $timezone) === schedule_date('oW', $now, $timezone)) {
        return false;
    }

    return schedule_date('H:i', $now, $timezone) >= $time;
}

function schedule_task_timezone(array $task): ?DateTimeZone
{
    $name = trim((string)($task['timezone'] ?? ''));
    if ($name === '') {
        return null;
    }
    try {
        return new DateTimeZone($name);
    } catch (Throwable) {
        return null;
    }
}

function schedule_date(string $format, int $timestamp, ?DateTimeZone $timezone = null): string
{
    if ($timestamp <= 0) {
        return '';
    }
    $date = new DateTimeImmutable('@' . $timestamp);
    if ($timezone !== null) {
        $date = $date->setTimezone($timezone);
    }
    return $date->format($format);
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
    $parts = str_getcsv($command, ' ', escape: "\\");
    $parts = array_values(array_filter(array_map('trim', $parts), fn($part) => $part !== ''));
    if (in_array('schedule:run', $parts, true)) {
        return [];
    }
    if (($parts[0] ?? '') === 'agent:enqueue') {
        $has_trigger = count(array_filter($parts, fn($part) => $part === '--scheduled'
            || str_starts_with($part, '--manual=') || str_starts_with($part, '--operator='))) > 0;
        if (!$has_trigger) {
            $parts[] = '--scheduled';
        }
    }
    return $parts;
}
