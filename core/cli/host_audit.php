<?php

/**
 * Read-only server and Nimbly project health audit.
 *
 * Usage:
 *   php core/cli/nimbly.php host:audit [--format=json|text] [--since=24h]
 *   php core/cli/nimbly.php host:audit:install [--user=<ssh-user>]
 */

if (php_sapi_name() !== 'cli') {
    die("host_audit.php must be run from the command line.\n");
}

if (!defined('BASE_DIR')) {
    define('BASE_DIR', realpath(__DIR__ . '/../..') . '/');
}

const HOST_AUDIT_SCHEMA_VERSION = 1;
const HOST_AUDIT_VERSION = '1.0.0';

if (!defined('NIMBLY_HOST_AUDIT_LIBRARY')) {
    $host_audit_command = $argv[1] ?? 'host:audit';
    if ($host_audit_command === 'host:audit:install') {
        host_audit_install($argv);
    } elseif ($host_audit_command === 'host:audit') {
        host_audit_main($argv);
    } else {
        fwrite(STDERR, "Unknown host audit command: {$host_audit_command}\n");
        exit(3);
    }
}

function host_audit_main(array $argv): void
{
    $started_at = microtime(true);
    $format = host_audit_option($argv, '--format') ?: 'json';
    if (!in_array($format, ['json', 'text'], true)) {
        fwrite(STDERR, "--format must be json or text\n");
        exit(3);
    }

    $since_value = host_audit_option($argv, '--since') ?: '24h';
    $since_seconds = host_audit_duration_seconds($since_value);
    if ($since_seconds === null || $since_seconds < 60) {
        fwrite(STDERR, "--since must be a duration such as 24h, 2d, or 90m\n");
        exit(3);
    }

    $config = host_audit_read_config();
    $context = [
        'now' => time(),
        'since' => time() - $since_seconds,
        'since_seconds' => $since_seconds,
        'config' => $config,
    ];

    $findings = [];
    $checks = [
        'system' => host_audit_system($context, $findings),
        'certificates' => host_audit_certificates($context, $findings),
        'security' => host_audit_security($context, $findings),
        'apache' => host_audit_apache($context, $findings),
    ];
    $project_result = host_audit_projects($context, $findings);
    $checks['projects'] = $project_result['checks'];
    $checks['scheduler'] = host_audit_scheduler($context, $findings);

    $findings = host_audit_group_findings($findings);
    usort($findings, 'host_audit_compare_findings');
    $summary = host_audit_summary($findings);
    $overall = host_audit_overall($summary);
    $result = [
        'schema_version' => HOST_AUDIT_SCHEMA_VERSION,
        'audit_version' => HOST_AUDIT_VERSION,
        'generated_at' => gmdate('c', $context['now']),
        'hostname' => gethostname() ?: php_uname('n'),
        'environment' => host_audit_environment($project_result['environments']),
        'window_seconds' => $since_seconds,
        'duration_ms' => (int)round((microtime(true) - $started_at) * 1000),
        'overall' => $overall,
        'summary' => $summary,
        'findings' => array_values($findings),
        'checks' => $checks,
    ];

    if ($format === 'text') {
        echo host_audit_text($result);
    } else {
        $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            fwrite(STDERR, "Could not encode audit result\n");
            exit(3);
        }
        echo $json . "\n";
    }

    exit(host_audit_exit_code($overall));
}

function host_audit_default_config(): array
{
    return [
        'certificate_warning_days' => 30,
        'certificate_critical_days' => 14,
        'disk_warning_percent' => 80,
        'disk_critical_percent' => 90,
        'memory_warning_percent' => 90,
        'scheduler_max_age_minutes' => 10,
        'job_running_stale_minutes' => 30,
        'required_services' => ['apache2', 'ssh', 'fail2ban'],
        'required_fail2ban_jails' => ['sshd', 'recidive', 'apache-php-scan'],
        'scheduler_config' => '/etc/nimbly/scheduler-projects.json',
        'scheduler_log' => '/var/log/nimbly-scheduler.log',
        'apache_log_dir' => '/var/log/apache2',
    ];
}

function host_audit_read_config(): array
{
    $config = host_audit_default_config();
    $path = getenv('NIMBLY_HOST_AUDIT_CONFIG') ?: '/etc/nimbly/host-audit.json';
    if (!is_file($path)) {
        return $config;
    }

    $custom = json_decode((string)file_get_contents($path), true);
    if (!is_array($custom)) {
        fwrite(STDERR, "Invalid host audit config: {$path}\n");
        exit(3);
    }
    return array_replace($config, $custom);
}

function host_audit_system(array $context, array &$findings): array
{
    $config = $context['config'];
    $disk_total = @disk_total_space('/');
    $disk_free = @disk_free_space('/');
    $disk_used_percent = null;
    if (is_float($disk_total) && $disk_total > 0 && is_float($disk_free)) {
        $disk_used_percent = round((1 - ($disk_free / $disk_total)) * 100, 1);
        $severity = host_audit_threshold_severity(
            $disk_used_percent,
            (float)$config['disk_warning_percent'],
            (float)$config['disk_critical_percent']
        );
        if ($severity !== 'ok') {
            $findings[] = host_audit_finding(
                'system:disk-root',
                $severity,
                'host',
                'Root filesystem usage is high',
                "{$disk_used_percent}% used"
            );
        }
    }

    $memory = host_audit_memory();
    if ($memory['used_percent'] !== null
        && $memory['used_percent'] >= (float)$config['memory_warning_percent']) {
        $findings[] = host_audit_finding(
            'system:memory',
            'warning',
            'host',
            'Memory usage is high',
            $memory['used_percent'] . '% used'
        );
    }

    $failed_units = host_audit_failed_units();
    foreach ($failed_units as $unit) {
        $findings[] = host_audit_finding(
            'systemd:failed:' . host_audit_id($unit),
            'warning',
            'host',
            'Systemd unit is failed',
            $unit
        );
    }

    $services = [];
    foreach ((array)$config['required_services'] as $service) {
        $result = host_audit_run_command(['systemctl', 'is-active', (string)$service], 5);
        $active = trim($result['stdout']) === 'active';
        $services[$service] = $active ? 'active' : trim($result['stdout'] . ' ' . $result['stderr']);
        if (!$active) {
            $findings[] = host_audit_finding(
                'service:' . host_audit_id((string)$service),
                'critical',
                'host',
                'Required service is not active',
                (string)$service
            );
        }
    }

    $config_tests = [
        'apache' => host_audit_run_command(['apache2ctl', 'configtest'], 10),
        'ssh' => host_audit_run_command(['sshd', '-t'], 10),
        'fail2ban' => host_audit_run_command(['fail2ban-client', '-t'], 15),
    ];
    $config_test_status = [];
    foreach ($config_tests as $name => $result) {
        $config_test_status[$name] = $result['exit_code'] === 0 ? 'ok' : 'failed';
        if ($result['exit_code'] !== 0) {
            $findings[] = host_audit_finding(
                'config:' . $name,
                'critical',
                'host',
                ucfirst($name) . ' configuration test failed',
                host_audit_first_line($result['stderr'] . "\n" . $result['stdout'])
            );
        }
    }

    return [
        'uptime_seconds' => host_audit_uptime_seconds(),
        'load_average' => function_exists('sys_getloadavg') ? sys_getloadavg() : null,
        'disk_root_used_percent' => $disk_used_percent,
        'memory' => $memory,
        'failed_units' => $failed_units,
        'services' => $services,
        'config_tests' => $config_test_status,
    ];
}

function host_audit_certificates(array $context, array &$findings): array
{
    $certificates = [];
    $warning_days = (int)$context['config']['certificate_warning_days'];
    $critical_days = (int)$context['config']['certificate_critical_days'];
    $cert_paths = glob('/etc/letsencrypt/live/*/cert.pem') ?: [];

    foreach ($cert_paths as $path) {
        $pem = @file_get_contents($path);
        $parsed = is_string($pem) ? @openssl_x509_parse($pem) : false;
        if (!is_array($parsed) || empty($parsed['validTo_time_t'])) {
            $findings[] = host_audit_finding(
                'certificate:unreadable:' . host_audit_id(basename(dirname($path))),
                'critical',
                'host',
                'Certificate could not be read',
                $path
            );
            continue;
        }

        $name = basename(dirname($path));
        $expires_at = (int)$parsed['validTo_time_t'];
        $days_remaining = (int)floor(($expires_at - $context['now']) / 86400);
        $status = 'ok';
        if ($days_remaining < $critical_days) {
            $status = 'critical';
        } elseif ($days_remaining < $warning_days) {
            $status = 'warning';
        }
        if ($status !== 'ok') {
            $findings[] = host_audit_finding(
                'certificate:expiry:' . host_audit_id($name),
                $status,
                'host',
                'Certificate expires soon',
                "{$name}: {$days_remaining} days remaining"
            );
        }
        $certificates[$name] = [
            'expires_at' => gmdate('c', $expires_at),
            'days_remaining' => $days_remaining,
            'status' => $status,
        ];
    }

    ksort($certificates);
    $renew_unit = host_audit_run_command(
        ['systemctl', 'show', 'snap.certbot.renew.service', '-p', 'Result', '-p', 'ExecMainStatus'],
        5
    );
    $renewal_status = host_audit_parse_key_values($renew_unit['stdout']);
    if (($renewal_status['Result'] ?? '') === 'failed'
        || (int)($renewal_status['ExecMainStatus'] ?? 0) !== 0) {
        $findings[] = host_audit_finding(
            'certificate:renewal-service',
            'critical',
            'host',
            'Certificate renewal service failed',
            trim(str_replace("\n", ', ', $renew_unit['stdout']))
        );
    }

    return [
        'count' => count($certificates),
        'renewal_service' => $renewal_status,
        'items' => $certificates,
    ];
}

function host_audit_security(array $context, array &$findings): array
{
    $ssh_result = host_audit_run_command(['sshd', '-T'], 10);
    $ssh = host_audit_parse_space_values($ssh_result['stdout']);
    $expected = [
        'passwordauthentication' => 'no',
        'kbdinteractiveauthentication' => 'no',
        'permitrootlogin' => 'no',
        'pubkeyauthentication' => 'yes',
    ];
    foreach ($expected as $key => $value) {
        if (($ssh[$key] ?? null) !== $value) {
            $findings[] = host_audit_finding(
                'ssh:' . $key,
                'critical',
                'host',
                'SSH authentication policy differs from expected',
                "{$key}=" . ($ssh[$key] ?? 'unknown') . ", expected {$value}"
            );
        }
    }

    $fail2ban_result = host_audit_run_command(['fail2ban-client', 'status'], 10);
    $jails = host_audit_fail2ban_jails($fail2ban_result['stdout']);
    foreach ((array)$context['config']['required_fail2ban_jails'] as $jail) {
        if (!in_array($jail, $jails, true)) {
            $findings[] = host_audit_finding(
                'fail2ban:missing:' . host_audit_id((string)$jail),
                'warning',
                'host',
                'Expected fail2ban jail is not active',
                (string)$jail
            );
        }
    }

    $jail_status = [];
    foreach ($jails as $jail) {
        $status = host_audit_run_command(['fail2ban-client', 'status', $jail], 10);
        $jail_status[$jail] = host_audit_fail2ban_counts($status['stdout']);
    }
    ksort($jail_status);

    return [
        'ssh' => array_intersect_key($ssh, $expected),
        'fail2ban_jails' => $jail_status,
    ];
}

function host_audit_apache(array $context, array &$findings): array
{
    $log_dir = rtrim((string)$context['config']['apache_log_dir'], '/');
    $access_files = array_merge(
        glob($log_dir . '/*access.log') ?: [],
        glob($log_dir . '/*access.log.1') ?: []
    );
    $error_files = array_merge(
        glob($log_dir . '/*error.log') ?: [],
        glob($log_dir . '/*error.log.1') ?: []
    );

    $http_5xx = 0;
    foreach (array_unique($access_files) as $file) {
        host_audit_each_line($file, function (string $line) use ($context, &$findings, &$http_5xx): void {
            if (!preg_match('/\[(?<date>[^\]]+)\]\s+"(?<method>[A-Z]+)\s+(?<path>\S+)[^"]*"\s+(?<status>\d{3})\s/', $line, $match)) {
                return;
            }
            $timestamp = strtotime($match['date']);
            $status = (int)$match['status'];
            if ($timestamp === false || $timestamp < $context['since'] || $status < 500) {
                return;
            }
            $http_5xx++;
            $path = parse_url($match['path'], PHP_URL_PATH) ?: $match['path'];
            $findings[] = host_audit_finding(
                'apache:5xx:' . $status . ':' . host_audit_id($path),
                $status >= 503 ? 'critical' : 'warning',
                'host',
                "HTTP {$status} response",
                "{$match['method']} {$path}",
                $timestamp
            );
        });
    }

    $php_counts = ['fatal' => 0, 'warning' => 0];
    foreach (array_unique($error_files) as $file) {
        host_audit_each_line($file, function (string $line) use ($context, &$findings, &$php_counts): void {
            $timestamp = host_audit_apache_error_timestamp($line);
            if ($timestamp === null || $timestamp < $context['since']) {
                return;
            }
            if (preg_match('/script .* not found or unable to stat|AH01630: client denied by server configuration/', $line)) {
                return;
            }
            if (preg_match('/PHP (Fatal error|Parse error):\s*(.+)$/i', $line, $match)) {
                $php_counts['fatal']++;
                $message = host_audit_normalize_message($match[2]);
                $findings[] = host_audit_finding(
                    'php:fatal:' . host_audit_id($message),
                    'critical',
                    'host',
                    'PHP fatal error',
                    $message,
                    $timestamp
                );
            } elseif (preg_match('/PHP Warning:\s*(.+)$/i', $line, $match)) {
                $php_counts['warning']++;
                $message = host_audit_normalize_message($match[1]);
                $findings[] = host_audit_finding(
                    'php:warning:' . host_audit_id($message),
                    'warning',
                    'host',
                    'PHP warning',
                    $message,
                    $timestamp
                );
            } elseif (preg_match('/MaxRequestWorkers|segfault|panic/i', $line)) {
                $findings[] = host_audit_finding(
                    'apache:capacity-or-crash:' . host_audit_id($line),
                    'critical',
                    'host',
                    'Apache capacity or crash event',
                    host_audit_normalize_message($line),
                    $timestamp
                );
            } elseif (preg_match('/\[ssl:error\].*AH\d+:\s*(.+)$/', $line, $match)) {
                $findings[] = host_audit_finding(
                    'apache:ssl:' . host_audit_id($match[1]),
                    'warning',
                    'host',
                    'Apache SSL error',
                    host_audit_normalize_message($match[1]),
                    $timestamp
                );
            }
        });
    }

    return [
        'access_logs' => array_values(array_unique($access_files)),
        'error_logs' => array_values(array_unique($error_files)),
        'http_5xx' => $http_5xx,
        'php_events' => $php_counts,
    ];
}

function host_audit_projects(array $context, array &$findings): array
{
    $config_path = (string)$context['config']['scheduler_config'];
    $scheduler_config = is_file($config_path)
        ? json_decode((string)file_get_contents($config_path), true)
        : null;
    if (!is_array($scheduler_config) || !isset($scheduler_config['projects'])
        || !is_array($scheduler_config['projects'])) {
        $findings[] = host_audit_finding(
            'projects:scheduler-registry',
            'critical',
            'host',
            'Scheduler project registry is missing or invalid',
            $config_path
        );
        return ['checks' => [], 'environments' => []];
    }

    $checks = [];
    $environments = [];
    foreach ($scheduler_config['projects'] as $name => $project) {
        if (!is_array($project) || ($project['enabled'] ?? true) === false) {
            continue;
        }
        $path = rtrim((string)($project['path'] ?? ''), '/');
        $check = host_audit_project((string)$name, $path, $context, $findings);
        $checks[$name] = $check;
        if (!empty($check['environment'])) {
            $environments[] = $check['environment'];
        }
    }
    ksort($checks);
    return ['checks' => $checks, 'environments' => array_values(array_unique($environments))];
}

function host_audit_project(string $name, string $path, array $context, array &$findings): array
{
    if ($path === '' || !is_file($path . '/core/cli/nimbly.php')) {
        $findings[] = host_audit_finding(
            'project:missing:' . host_audit_id($name),
            'critical',
            'project',
            'Registered project path is unavailable',
            $path,
            null,
            $name
        );
        return ['path' => $path, 'available' => false];
    }

    $environment = host_audit_project_environment($path);
    $system_log = $path . '/ext/data/.tmp/logs/system.log';
    $system_log_events = 0;
    if (is_file($system_log)) {
        host_audit_each_line($system_log, function (string $line) use (
            $name,
            $context,
            &$findings,
            &$system_log_events
        ): void {
            if (!preg_match('/^\[(?<date>[^\]]+)\]\s+(?<message>.+)$/', $line, $match)) {
                return;
            }
            $timestamp = strtotime($match['date']);
            if ($timestamp === false || $timestamp < $context['since']) {
                return;
            }
            $message = $match['message'];
            if (!preg_match('/PHP (Warning|Fatal|Parse)|Nimbly:.*(?:error|failed|exception)/i', $message)) {
                return;
            }
            $system_log_events++;
            $critical = preg_match('/PHP (Fatal|Parse)|exception/i', $message) === 1;
            $normalized = host_audit_normalize_message($message);
            $findings[] = host_audit_finding(
                'project-log:' . host_audit_id($name) . ':' . host_audit_id($normalized),
                $critical ? 'critical' : 'warning',
                'project',
                $critical ? 'Project fatal error' : 'Project warning',
                $normalized,
                $timestamp,
                $name
            );
        });
    }

    $jobs = host_audit_project_jobs($name, $path, $context, $findings);
    $git = [
        'core' => host_audit_git_state($path),
        'ext' => host_audit_git_state($path . '/ext'),
    ];
    foreach ($git as $repo => $state) {
        if ($state['operation'] !== null) {
            $findings[] = host_audit_finding(
                'git:' . host_audit_id($name) . ':' . $repo . ':' . $state['operation'],
                'critical',
                'project',
                'Git operation is interrupted',
                "{$repo}: {$state['operation']}",
                null,
                $name
            );
        }
    }

    return [
        'path' => $path,
        'available' => true,
        'environment' => $environment,
        'system_log_events' => $system_log_events,
        'jobs' => $jobs,
        'git' => $git,
    ];
}

function host_audit_project_jobs(
    string $name,
    string $path,
    array $context,
    array &$findings
): array {
    $job_dir = $path . '/ext/data/.jobs';
    $counts = ['queued' => 0, 'running' => 0, 'done' => 0, 'failed' => 0, 'invalid' => 0];
    if (!is_dir($job_dir)) {
        return $counts;
    }

    $files = glob($job_dir . '/*') ?: [];
    foreach ($files as $file) {
        if (!is_file($file) || basename($file) === '.meta') {
            continue;
        }
        $job = json_decode((string)file_get_contents($file), true);
        if (!is_array($job)) {
            $counts['invalid']++;
            $findings[] = host_audit_finding(
                'job:invalid:' . host_audit_id($name) . ':' . host_audit_id(basename($file)),
                'warning',
                'project',
                'Job record is invalid JSON',
                basename($file),
                null,
                $name
            );
            continue;
        }
        $status = strtolower((string)($job['status'] ?? 'queued'));
        if (!isset($counts[$status])) {
            $status = 'queued';
        }
        $counts[$status]++;
        if ($status === 'failed') {
            $message = (string)($job['last_error'] ?? $job['error'] ?? basename($file));
            $findings[] = host_audit_finding(
                'job:failed:' . host_audit_id($name) . ':' . host_audit_id(basename($file)),
                'critical',
                'project',
                'Nimbly job failed',
                host_audit_normalize_message($message),
                null,
                $name
            );
        }
        if ($status === 'running') {
            $modified = filemtime($file) ?: $context['now'];
            $max_age = (int)$context['config']['job_running_stale_minutes'] * 60;
            if ($modified < $context['now'] - $max_age) {
                $findings[] = host_audit_finding(
                    'job:stale:' . host_audit_id($name) . ':' . host_audit_id(basename($file)),
                    'warning',
                    'project',
                    'Nimbly job appears stuck',
                    basename($file),
                    $modified,
                    $name
                );
            }
        }
    }
    return $counts;
}

function host_audit_scheduler(array $context, array &$findings): array
{
    $log_path = (string)$context['config']['scheduler_log'];
    if (!is_file($log_path)) {
        $findings[] = host_audit_finding(
            'scheduler:log-missing',
            'critical',
            'host',
            'Scheduler log is missing',
            $log_path
        );
        return ['log' => $log_path, 'last_run_at' => null, 'failures' => 0];
    }

    $last_run = null;
    $failures = 0;
    host_audit_each_line($log_path, function (string $line) use (
        $context,
        &$findings,
        &$last_run,
        &$failures
    ): void {
        if (!preg_match('/^(?<date>\S+).*project=(?<project>\S+).*exit_code=(?<exit>\d+)/', $line, $match)) {
            return;
        }
        $timestamp = strtotime($match['date']);
        if ($timestamp === false) {
            return;
        }
        $last_run = max($last_run ?? 0, $timestamp);
        if ($timestamp >= $context['since'] && (int)$match['exit'] !== 0) {
            $failures++;
            $findings[] = host_audit_finding(
                'scheduler:failure:' . host_audit_id($match['project']),
                'critical',
                'project',
                'Project scheduler failed',
                trim($line),
                $timestamp,
                $match['project']
            );
        }
    });

    $max_age = (int)$context['config']['scheduler_max_age_minutes'] * 60;
    if ($last_run === null || $last_run < $context['now'] - $max_age) {
        $findings[] = host_audit_finding(
            'scheduler:stale',
            'critical',
            'host',
            'Server scheduler has not run recently',
            $last_run ? gmdate('c', $last_run) : 'no parseable run found'
        );
    }

    return [
        'log' => $log_path,
        'last_run_at' => $last_run ? gmdate('c', $last_run) : null,
        'failures' => $failures,
    ];
}

function host_audit_install(array $argv): void
{
    if (function_exists('posix_geteuid') && posix_geteuid() !== 0) {
        fwrite(STDERR, "host:audit:install must run as root\n");
        exit(1);
    }

    $bin_path = getenv('NIMBLY_HOST_AUDIT_BIN') ?: '/usr/local/bin/nimbly-host-audit';
    $lib_path = getenv('NIMBLY_HOST_AUDIT_LIB')
        ?: '/usr/local/lib/nimbly-host-audit.php';
    $config_path = getenv('NIMBLY_HOST_AUDIT_CONFIG') ?: '/etc/nimbly/host-audit.json';
    $user = host_audit_option($argv, '--user');
    host_audit_ensure_dir(dirname($bin_path));
    host_audit_ensure_dir(dirname($lib_path));
    host_audit_ensure_dir(dirname($config_path));

    $source = file_get_contents(__FILE__);
    if ($source === false) {
        fwrite(STDERR, "Could not read host audit source: " . __FILE__ . "\n");
        exit(1);
    }
    host_audit_write_file($lib_path, $source, 0644);

    $script = "#!/bin/sh\n"
        . 'exec env NIMBLY_HOST_AUDIT_CONFIG=' . escapeshellarg($config_path)
        . ' ' . escapeshellarg(PHP_BINARY)
        . ' ' . escapeshellarg($lib_path)
        . " host:audit \"\$@\"\n";
    host_audit_write_file($bin_path, $script, 0755);
    if (!is_file($config_path)) {
        $json = json_encode(
            host_audit_default_config(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . "\n";
        host_audit_write_file($config_path, $json, 0644);
    }

    echo "Installed host audit wrapper: {$bin_path}\n";
    echo "Installed host audit library: {$lib_path}\n";
    echo "Installed host audit config:  {$config_path}\n";

    if ($user !== '') {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_-]*$/', $user)
            || !function_exists('posix_getpwnam')
            || posix_getpwnam($user) === false) {
            fwrite(STDERR, "Unknown or invalid sudo user: {$user}\n");
            exit(1);
        }
        $sudoers_path = getenv('NIMBLY_HOST_AUDIT_SUDOERS')
            ?: '/etc/sudoers.d/nimbly-host-audit';
        $sudoers = "{$user} ALL=(root) NOPASSWD: {$bin_path} --format=json\n";
        host_audit_write_file($sudoers_path, $sudoers, 0440);
        $test = host_audit_run_command(['visudo', '-cf', $sudoers_path], 10);
        if ($test['exit_code'] !== 0) {
            @unlink($sudoers_path);
            fwrite(STDERR, "Invalid sudoers rule: " . $test['stderr'] . "\n");
            exit(1);
        }
        echo "Installed host audit sudo rule: {$sudoers_path}\n";
    }
}

function host_audit_finding(
    string $id,
    string $severity,
    string $scope,
    string $title,
    string $evidence,
    ?int $timestamp = null,
    ?string $project = null
): array {
    $finding = [
        'id' => $id,
        'severity' => $severity,
        'scope' => $scope,
        'title' => $title,
        'count' => 1,
        'evidence' => host_audit_redact($evidence),
    ];
    if ($project !== null && $project !== '') {
        $finding['project'] = $project;
    }
    if ($timestamp !== null) {
        $finding['first_seen'] = gmdate('c', $timestamp);
        $finding['last_seen'] = gmdate('c', $timestamp);
    }
    return $finding;
}

function host_audit_group_findings(array $findings): array
{
    $grouped = [];
    foreach ($findings as $finding) {
        $id = $finding['id'];
        if (!isset($grouped[$id])) {
            $grouped[$id] = $finding;
            continue;
        }
        $grouped[$id]['count'] += $finding['count'] ?? 1;
        if (isset($finding['first_seen'])
            && (!isset($grouped[$id]['first_seen'])
                || $finding['first_seen'] < $grouped[$id]['first_seen'])) {
            $grouped[$id]['first_seen'] = $finding['first_seen'];
        }
        if (isset($finding['last_seen'])
            && (!isset($grouped[$id]['last_seen'])
                || $finding['last_seen'] > $grouped[$id]['last_seen'])) {
            $grouped[$id]['last_seen'] = $finding['last_seen'];
        }
    }
    return array_values($grouped);
}

function host_audit_compare_findings(array $a, array $b): int
{
    $weights = ['critical' => 0, 'warning' => 1, 'ok' => 2, 'unknown' => 3];
    $severity = ($weights[$a['severity']] ?? 9) <=> ($weights[$b['severity']] ?? 9);
    return $severity !== 0 ? $severity : strcmp($a['id'], $b['id']);
}

function host_audit_summary(array $findings): array
{
    $summary = ['critical' => 0, 'warning' => 0, 'ok' => 0, 'unknown' => 0];
    foreach ($findings as $finding) {
        $severity = $finding['severity'] ?? 'unknown';
        $summary[$severity] = ($summary[$severity] ?? 0) + 1;
    }
    return $summary;
}

function host_audit_overall(array $summary): string
{
    if (($summary['critical'] ?? 0) > 0) {
        return 'critical';
    }
    if (($summary['warning'] ?? 0) > 0) {
        return 'warning';
    }
    if (($summary['unknown'] ?? 0) > 0) {
        return 'unknown';
    }
    return 'ok';
}

function host_audit_exit_code(string $overall): int
{
    return match ($overall) {
        'ok' => 0,
        'warning' => 1,
        'critical' => 2,
        default => 3,
    };
}

function host_audit_text(array $result): string
{
    $lines = [
        'Nimbly host audit',
        'Host: ' . $result['hostname'],
        'Environment: ' . $result['environment'],
        'Generated: ' . $result['generated_at'],
        'Overall: ' . strtoupper($result['overall']),
        sprintf(
            'Findings: %d critical, %d warning',
            $result['summary']['critical'],
            $result['summary']['warning']
        ),
        '',
    ];
    if (empty($result['findings'])) {
        $lines[] = 'No actionable findings.';
    } else {
        foreach ($result['findings'] as $finding) {
            $project = isset($finding['project']) ? " · {$finding['project']}" : '';
            $count = ($finding['count'] ?? 1) > 1 ? " ×{$finding['count']}" : '';
            $lines[] = sprintf(
                '[%s]%s %s%s — %s',
                strtoupper($finding['severity']),
                $project,
                $finding['title'],
                $count,
                $finding['evidence']
            );
        }
    }
    return implode("\n", $lines) . "\n";
}

function host_audit_run_command(array $command, int $timeout_seconds): array
{
    $parts = array_map('escapeshellarg', $command);
    $shell_command = 'timeout --signal=TERM ' . max(1, $timeout_seconds) . 's '
        . implode(' ', $parts);
    $descriptors = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = @proc_open($shell_command, $descriptors, $pipes);
    if (!is_resource($process)) {
        return ['exit_code' => 127, 'stdout' => '', 'stderr' => 'command unavailable'];
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit_code = proc_close($process);
    return [
        'exit_code' => (int)$exit_code,
        'stdout' => substr((string)$stdout, 0, 100000),
        'stderr' => substr((string)$stderr, 0, 100000),
    ];
}

function host_audit_each_line(string $path, callable $callback): void
{
    $handle = @fopen($path, 'r');
    if (!$handle) {
        return;
    }
    while (($line = fgets($handle)) !== false) {
        $callback(rtrim($line, "\r\n"));
    }
    fclose($handle);
}

function host_audit_failed_units(): array
{
    $result = host_audit_run_command(
        ['systemctl', '--failed', '--no-legend', '--plain'],
        10
    );
    $units = [];
    foreach (preg_split('/\R/', trim($result['stdout'])) ?: [] as $line) {
        if (preg_match('/^\s*([^\s]+)/', $line, $match)) {
            $units[] = $match[1];
        }
    }
    return array_values(array_unique($units));
}

function host_audit_memory(): array
{
    $values = [];
    if (is_file('/proc/meminfo')) {
        foreach (file('/proc/meminfo', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^(\w+):\s+(\d+)/', $line, $match)) {
                $values[$match[1]] = (int)$match[2] * 1024;
            }
        }
    }
    $total = $values['MemTotal'] ?? null;
    $available = $values['MemAvailable'] ?? null;
    $used_percent = null;
    if ($total && $available !== null) {
        $used_percent = round((1 - ($available / $total)) * 100, 1);
    }
    return [
        'total_bytes' => $total,
        'available_bytes' => $available,
        'used_percent' => $used_percent,
    ];
}

function host_audit_uptime_seconds(): ?int
{
    if (!is_file('/proc/uptime')) {
        return null;
    }
    $parts = explode(' ', trim((string)file_get_contents('/proc/uptime')));
    return isset($parts[0]) ? (int)floor((float)$parts[0]) : null;
}

function host_audit_parse_key_values(string $output): array
{
    $values = [];
    foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim($value);
        }
    }
    return $values;
}

function host_audit_parse_space_values(string $output): array
{
    $values = [];
    foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
        if (preg_match('/^(\S+)\s+(.+)$/', trim($line), $match)) {
            $values[strtolower($match[1])] = trim($match[2]);
        }
    }
    return $values;
}

function host_audit_fail2ban_jails(string $output): array
{
    if (!preg_match('/Jail list:\s*(.+)$/m', $output, $match)) {
        return [];
    }
    $jails = array_filter(array_map('trim', explode(',', trim($match[1]))));
    sort($jails);
    return array_values($jails);
}

function host_audit_fail2ban_counts(string $output): array
{
    $counts = [];
    $labels = [
        'Currently failed' => 'currently_failed',
        'Total failed' => 'total_failed',
        'Currently banned' => 'currently_banned',
        'Total banned' => 'total_banned',
    ];
    foreach ($labels as $label => $key) {
        if (preg_match('/' . preg_quote($label, '/') . ':\s*(\d+)/', $output, $match)) {
            $counts[$key] = (int)$match[1];
        }
    }
    return $counts;
}

function host_audit_apache_error_timestamp(string $line): ?int
{
    if (!preg_match('/^\[([^\]]+)\]/', $line, $match)) {
        return null;
    }
    $timestamp = strtotime($match[1]);
    return $timestamp === false ? null : $timestamp;
}

function host_audit_project_environment(string $path): ?string
{
    $env_path = $path . '/.env';
    if (!is_file($env_path) || !is_readable($env_path)) {
        return null;
    }
    foreach (file($env_path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match('/^(?:APP_ENV|NIMBLY_ENV)=["\']?([^"\'\s]+)["\']?/', trim($line), $match)) {
            return strtolower($match[1]);
        }
    }
    return null;
}

function host_audit_git_state(string $path): array
{
    $git_path = $path . '/.git';
    if (!is_dir($git_path)) {
        return ['branch' => null, 'revision' => null, 'operation' => null];
    }
    $head = trim((string)@file_get_contents($git_path . '/HEAD'));
    $branch = str_starts_with($head, 'ref: refs/heads/')
        ? substr($head, strlen('ref: refs/heads/'))
        : null;
    $revision = $branch !== null
        ? trim((string)@file_get_contents($git_path . '/refs/heads/' . $branch))
        : $head;
    $operation = null;
    if (is_dir($git_path . '/rebase-merge') || is_dir($git_path . '/rebase-apply')) {
        $operation = 'rebase';
    } elseif (is_file($git_path . '/MERGE_HEAD')) {
        $operation = 'merge';
    } elseif (is_file($git_path . '/CHERRY_PICK_HEAD')) {
        $operation = 'cherry-pick';
    }
    return [
        'branch' => $branch,
        'revision' => $revision !== '' ? $revision : null,
        'operation' => $operation,
    ];
}

function host_audit_environment(array $environments): string
{
    $values = array_values(array_filter(array_unique($environments)));
    if (count($values) === 1) {
        return $values[0];
    }
    return empty($values) ? 'unknown' : 'mixed';
}

function host_audit_normalize_message(string $message): string
{
    $message = preg_replace('/\s+/', ' ', trim($message));
    $message = preg_replace('/\bpid\s+\d+\b/i', 'pid {n}', $message);
    $message = preg_replace('/\bline\s+\d+\b/i', 'line {n}', $message);
    $message = preg_replace('/\b[0-9a-f]{32,64}\b/i', '{id}', $message);
    return substr((string)$message, 0, 500);
}

function host_audit_redact(string $value): string
{
    $value = preg_replace(
        '/\b(password|passwd|token|secret|api[_-]?key)=\S+/i',
        '$1={redacted}',
        $value
    );
    $value = preg_replace(
        '/(["\']?(?:password|passwd|token|secret|api[_-]?key)["\']?\s*:\s*)["\']?[^"\'\s,}]+/i',
        '$1{redacted}',
        (string)$value
    );
    $value = preg_replace(
        '/\bAuthorization:\s*Bearer\s+\S+/i',
        'Authorization: Bearer {redacted}',
        (string)$value
    );
    return substr(trim((string)$value), 0, 1000);
}

function host_audit_id(string $value): string
{
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    return trim((string)$value, '-') ?: 'unknown';
}

function host_audit_first_line(string $value): string
{
    $lines = preg_split('/\R/', trim($value));
    return host_audit_redact((string)($lines[0] ?? 'unknown error'));
}

function host_audit_threshold_severity(float $value, float $warning, float $critical): string
{
    if ($value >= $critical) {
        return 'critical';
    }
    if ($value >= $warning) {
        return 'warning';
    }
    return 'ok';
}

function host_audit_duration_seconds(string $value): ?int
{
    if (!preg_match('/^(\d+)([smhd])$/', strtolower(trim($value)), $match)) {
        return null;
    }
    $multipliers = ['s' => 1, 'm' => 60, 'h' => 3600, 'd' => 86400];
    return (int)$match[1] * $multipliers[$match[2]];
}

function host_audit_option(array $argv, string $name): string
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

function host_audit_ensure_dir(string $path): void
{
    if (is_dir($path)) {
        return;
    }
    if (!mkdir($path, 0755, true) && !is_dir($path)) {
        fwrite(STDERR, "Could not create directory: {$path}\n");
        exit(1);
    }
}

function host_audit_write_file(string $path, string $contents, int $mode): void
{
    $tmp_path = $path . '.tmp.' . getmypid();
    if (file_put_contents($tmp_path, $contents, LOCK_EX) === false
        || !chmod($tmp_path, $mode)
        || !rename($tmp_path, $path)) {
        @unlink($tmp_path);
        fwrite(STDERR, "Could not write file: {$path}\n");
        exit(1);
    }
}
