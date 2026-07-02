<?php

/**
 * Server-level scheduler orchestrator.
 *
 * Usage:
 *   php core/cli/nimbly.php scheduler:install
 *   php core/cli/nimbly.php scheduler:add-project <name> [path]
 *   php core/cli/nimbly.php scheduler:remove-project <name>
 *   php core/cli/nimbly.php scheduler:list-projects
 *   php core/cli/nimbly.php scheduler:run [--dry-run]
 *   php core/cli/nimbly.php scheduler:cron:install --user=www-data
 *   php core/cli/nimbly.php scheduler:cron:remove
 *   php core/cli/nimbly.php scheduler:cron:status
 */

if (php_sapi_name() !== 'cli') {
    die("scheduler_orchestrator.php must be run from the command line.\n");
}

if (!defined('BASE_DIR')) {
    define('BASE_DIR', realpath(__DIR__ . '/../..') . '/');
}

$scheduler_command = $argv[1] ?? '';

switch ($scheduler_command) {
    case 'scheduler:install':
    case 'scheduler:orchestrator:install':
        scheduler_orchestrator_install();
        break;
    case 'scheduler:add-project':
    case 'scheduler:orchestrator:add':
        scheduler_orchestrator_add($argv);
        break;
    case 'scheduler:remove-project':
    case 'scheduler:orchestrator:remove':
        scheduler_orchestrator_remove($argv);
        break;
    case 'scheduler:list-projects':
    case 'scheduler:orchestrator:list':
        scheduler_orchestrator_list();
        break;
    case 'scheduler:run':
    case 'scheduler:orchestrator:run':
        scheduler_orchestrator_run($argv);
        break;
    case 'scheduler:cron:install':
    case 'scheduler:orchestrator:cron:install':
        scheduler_orchestrator_cron_install($argv);
        break;
    case 'scheduler:cron:remove':
    case 'scheduler:orchestrator:cron:remove':
        scheduler_orchestrator_cron_remove();
        break;
    case 'scheduler:cron:status':
    case 'scheduler:orchestrator:cron:status':
        scheduler_orchestrator_cron_status();
        break;
    default:
        scheduler_orchestrator_usage();
        exit(1);
}

function scheduler_orchestrator_config_path(): string
{
    return getenv('NIMBLY_SCHEDULER_CONFIG') ?: '/etc/nimbly/scheduler-projects.json';
}

function scheduler_orchestrator_bin_path(): string
{
    return getenv('NIMBLY_SCHEDULER_BIN') ?: '/usr/local/bin/nimbly-scheduler-orchestrator';
}

function scheduler_orchestrator_cron_path(): string
{
    return getenv('NIMBLY_SCHEDULER_CRON') ?: '/etc/cron.d/nimbly-scheduler';
}

function scheduler_orchestrator_log_path(): string
{
    return getenv('NIMBLY_SCHEDULER_LOG') ?: '/var/log/nimbly-scheduler.log';
}

function scheduler_orchestrator_lock_path(): string
{
    return getenv('NIMBLY_SCHEDULER_LOCK') ?: sys_get_temp_dir() . '/nimbly-scheduler-orchestrator.lock';
}

function scheduler_orchestrator_default_config(): array
{
    return [
        'default_delay_after_seconds' => 10,
        'projects' => [],
    ];
}

function scheduler_orchestrator_read_config(): array
{
    $path = scheduler_orchestrator_config_path();
    if (!file_exists($path)) {
        return scheduler_orchestrator_default_config();
    }

    $json = file_get_contents($path);
    $config = json_decode($json ?: '', true);
    if (!is_array($config)) {
        fwrite(STDERR, "Invalid scheduler config: {$path}\n");
        exit(1);
    }

    if (!isset($config['projects']) || !is_array($config['projects'])) {
        $config['projects'] = [];
    }
    if (!isset($config['default_delay_after_seconds'])) {
        $config['default_delay_after_seconds'] = 10;
    }

    return $config;
}

function scheduler_orchestrator_write_config(array $config): void
{
    $path = scheduler_orchestrator_config_path();
    scheduler_orchestrator_ensure_dir(dirname($path));

    ksort($config['projects']);
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    $tmp_path = $path . '.tmp.' . getmypid();
    if (file_put_contents($tmp_path, $json, LOCK_EX) === false || !rename($tmp_path, $path)) {
        @unlink($tmp_path);
        fwrite(STDERR, "Could not write scheduler config: {$path}\n");
        exit(1);
    }
    @chmod($path, 0644);
}

function scheduler_orchestrator_ensure_dir(string $dir): void
{
    if ($dir === '' || $dir === '.' || is_dir($dir)) {
        return;
    }
    if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
        fwrite(STDERR, "Could not create directory: {$dir}\n");
        exit(1);
    }
}

function scheduler_orchestrator_install(): void
{
    $bin_path = scheduler_orchestrator_bin_path();
    $log_path = scheduler_orchestrator_log_path();
    scheduler_orchestrator_ensure_dir(dirname($bin_path));
    scheduler_orchestrator_ensure_dir(dirname($log_path));

    $php_binary = PHP_BINARY;
    $cli_path = BASE_DIR . 'core/cli/nimbly.php';
    $env_args = [
        'NIMBLY_SCHEDULER_CONFIG=' . scheduler_orchestrator_config_path(),
        'NIMBLY_SCHEDULER_BIN=' . $bin_path,
        'NIMBLY_SCHEDULER_CRON=' . scheduler_orchestrator_cron_path(),
        'NIMBLY_SCHEDULER_LOG=' . $log_path,
        'NIMBLY_SCHEDULER_LOCK=' . scheduler_orchestrator_lock_path(),
    ];
    $script = "#!/bin/sh\n"
        . "exec env " . implode(' ', array_map('escapeshellarg', $env_args))
        . ' ' . escapeshellarg($php_binary) . ' ' . escapeshellarg($cli_path)
        . " scheduler:run >> " . escapeshellarg($log_path) . " 2>&1\n";

    if (file_put_contents($bin_path, $script) === false) {
        fwrite(STDERR, "Could not write orchestrator wrapper: {$bin_path}\n");
        exit(1);
    }
    chmod($bin_path, 0755);

    if (!file_exists(scheduler_orchestrator_config_path())) {
        scheduler_orchestrator_write_config(scheduler_orchestrator_default_config());
    }

    echo "Installed scheduler orchestrator wrapper: {$bin_path}\n";
}

function scheduler_orchestrator_add(array $argv): void
{
    $name = trim((string)($argv[2] ?? ''));
    if ($name === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $name)) {
        fwrite(STDERR, "Usage: php core/cli/nimbly.php scheduler:add-project <name> [path]\n");
        exit(1);
    }

    $path = trim((string)($argv[3] ?? BASE_DIR));
    $real_path = realpath($path);
    if ($real_path === false || !is_dir($real_path)) {
        fwrite(STDERR, "Project path does not exist: {$path}\n");
        exit(1);
    }

    $config = scheduler_orchestrator_read_config();
    $config['projects'][$name] = [
        'path' => rtrim($real_path, '/'),
        'enabled' => true,
    ];
    scheduler_orchestrator_write_config($config);

    echo "Registered scheduler project: {$name} ({$config['projects'][$name]['path']})\n";
}

function scheduler_orchestrator_remove(array $argv): void
{
    $name = trim((string)($argv[2] ?? ''));
    if ($name === '') {
        fwrite(STDERR, "Usage: php core/cli/nimbly.php scheduler:remove-project <name>\n");
        exit(1);
    }

    $config = scheduler_orchestrator_read_config();
    if (!isset($config['projects'][$name])) {
        fwrite(STDERR, "Scheduler project not found: {$name}\n");
        exit(1);
    }

    unset($config['projects'][$name]);
    scheduler_orchestrator_write_config($config);

    echo "Removed scheduler project: {$name}\n";
}

function scheduler_orchestrator_list(): void
{
    $config = scheduler_orchestrator_read_config();
    if (empty($config['projects'])) {
        echo "No scheduler projects registered.\n";
        return;
    }

    foreach ($config['projects'] as $name => $project) {
        $enabled = ($project['enabled'] ?? true) ? 'enabled' : 'disabled';
        $path = $project['path'] ?? '';
        printf("%-24s %-8s %s\n", $name, $enabled, $path);
    }
}

function scheduler_orchestrator_run(array $argv): void
{
    $dry_run = in_array('--dry-run', $argv, true);
    $lock = @fopen(scheduler_orchestrator_lock_path(), 'c');
    if (!$lock) {
        echo scheduler_orchestrator_log_line('orchestrator', '', 0.0, 1, 'lock unavailable');
        exit(1);
    }
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        echo scheduler_orchestrator_log_line('orchestrator', '', 0.0, 0, 'already running');
        exit(0);
    }

    $config = scheduler_orchestrator_read_config();
    $projects = scheduler_orchestrator_enabled_projects($config);
    if (empty($projects)) {
        echo scheduler_orchestrator_log_line('orchestrator', '', 0.0, 0, 'no enabled projects');
        exit(0);
    }

    $delay = max(0, (int)($config['default_delay_after_seconds'] ?? 10));
    $failed = 0;
    $index = 0;
    $total = count($projects);

    foreach ($projects as $name => $project) {
        $index++;
        $path = rtrim((string)($project['path'] ?? ''), '/');
        $started_at = microtime(true);
        $exit_code = $dry_run ? scheduler_orchestrator_check_project($path) : scheduler_orchestrator_run_project($path);
        $duration = microtime(true) - $started_at;
        if ($exit_code !== 0) {
            $failed++;
        }

        echo scheduler_orchestrator_log_line($name, $path, $duration, $exit_code, $dry_run ? 'dry-run' : '');

        if ($delay > 0 && $index < $total) {
            sleep($delay);
        }
    }

    exit($failed > 0 ? 1 : 0);
}

function scheduler_orchestrator_enabled_projects(array $config): array
{
    $projects = [];
    foreach ($config['projects'] as $name => $project) {
        if (!is_array($project) || ($project['enabled'] ?? true) === false) {
            continue;
        }
        $projects[$name] = $project;
    }
    return $projects;
}

function scheduler_orchestrator_run_project(string $path): int
{
    $cli_path = $path . '/core/cli/nimbly.php';
    $check_exit_code = scheduler_orchestrator_check_project($path);
    if ($check_exit_code !== 0) {
        return $check_exit_code;
    }

    $command = array_map('escapeshellarg', [
        PHP_BINARY,
        $cli_path,
        'schedule:run',
    ]);
    passthru(implode(' ', $command), $exit_code);
    return (int)$exit_code;
}

function scheduler_orchestrator_check_project(string $path): int
{
    $cli_path = $path . '/core/cli/nimbly.php';
    if ($path === '' || !is_file($cli_path)) {
        return 127;
    }
    return 0;
}

function scheduler_orchestrator_log_line(string $name, string $path, float $duration, int $exit_code, string $message = ''): string
{
    $parts = [
        date('c'),
        'project=' . $name,
    ];
    if ($path !== '') {
        $parts[] = 'path=' . $path;
    }
    $parts[] = 'duration=' . number_format($duration, 3, '.', '') . 's';
    $parts[] = 'exit_code=' . $exit_code;
    if ($message !== '') {
        $parts[] = 'message=' . $message;
    }
    return implode(' ', $parts) . "\n";
}

function scheduler_orchestrator_cron_install(array $argv): void
{
    $user = scheduler_orchestrator_option($argv, '--user') ?: 'www-data';
    $cron_path = scheduler_orchestrator_cron_path();
    $bin_path = scheduler_orchestrator_bin_path();
    $log_path = scheduler_orchestrator_log_path();

    scheduler_orchestrator_ensure_dir(dirname($cron_path));
    scheduler_orchestrator_prepare_log($log_path, $user);

    $cron = "SHELL=/bin/sh\n"
        . "PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin\n"
        . "* * * * * {$user} {$bin_path}\n";

    if (file_put_contents($cron_path, $cron) === false) {
        fwrite(STDERR, "Could not write scheduler cron file: {$cron_path}\n");
        exit(1);
    }
    chmod($cron_path, 0644);

    echo "Installed scheduler cron: {$cron_path}\n";
}

function scheduler_orchestrator_prepare_log(string $log_path, string $user): void
{
    scheduler_orchestrator_ensure_dir(dirname($log_path));
    if (!file_exists($log_path) && @file_put_contents($log_path, '') === false) {
        fwrite(STDERR, "Could not create scheduler log file: {$log_path}\n");
        exit(1);
    }

    chmod($log_path, 0644);
    if (function_exists('posix_geteuid') && posix_geteuid() === 0 && function_exists('posix_getpwnam')) {
        $user_info = posix_getpwnam($user);
        if (is_array($user_info)) {
            chown($log_path, $user_info['uid']);
            chgrp($log_path, $user_info['gid']);
        }
    }
}

function scheduler_orchestrator_cron_remove(): void
{
    $cron_path = scheduler_orchestrator_cron_path();
    if (!file_exists($cron_path)) {
        echo "Scheduler cron is not installed: {$cron_path}\n";
        return;
    }
    if (!unlink($cron_path)) {
        fwrite(STDERR, "Could not remove scheduler cron file: {$cron_path}\n");
        exit(1);
    }
    echo "Removed scheduler cron: {$cron_path}\n";
}

function scheduler_orchestrator_cron_status(): void
{
    $cron_path = scheduler_orchestrator_cron_path();
    $bin_path = scheduler_orchestrator_bin_path();
    $config_path = scheduler_orchestrator_config_path();
    $log_path = scheduler_orchestrator_log_path();

    echo 'wrapper: ' . (is_executable($bin_path) ? 'installed' : 'missing') . " {$bin_path}\n";
    echo 'config:  ' . (file_exists($config_path) ? 'installed' : 'missing') . " {$config_path}\n";
    echo 'cron:    ' . (file_exists($cron_path) ? 'installed' : 'missing') . " {$cron_path}\n";
    echo 'log:     ' . (file_exists($log_path) ? 'present' : 'missing') . " {$log_path}\n";
}

function scheduler_orchestrator_option(array $argv, string $name): string
{
    foreach ($argv as $index => $arg) {
        if ($arg === $name) {
            return (string)($argv[$index + 1] ?? '');
        }
        if (str_starts_with($arg, $name . '=')) {
            return substr($arg, strlen($name) + 1);
        }
    }
    return '';
}

function scheduler_orchestrator_usage(): void
{
    echo "Usage: php core/cli/nimbly.php scheduler:<command>\n";
}
