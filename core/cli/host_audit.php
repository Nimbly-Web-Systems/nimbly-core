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
const HOST_AUDIT_VERSION = '1.3.0';

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
    ];
    $project_result = host_audit_projects($context, $findings);
    $context['registered_projects'] = $project_result['aliases'];
    $context['registered_hosts'] = $project_result['hosts'];
    $context['known_hosts'] = $project_result['known_hosts'];
    $context['access_log_projects'] = $project_result['access_log_projects'];
    $context['error_log_projects'] = $project_result['error_log_projects'];
    $checks['apache'] = host_audit_apache($context, $findings);
    $project_result['checks'] = host_audit_merge_project_metrics(
        $project_result['checks'],
        (array)($checks['apache']['projects'] ?? [])
    );
    $checks['projects'] = $project_result['checks'];
    $checks['scheduler'] = host_audit_scheduler($context, $findings);
    foreach ($checks['projects'] as $name => &$project_check) {
        $project_check['status'] = host_audit_project_status((string)$name, $findings);
    }
    unset($project_check);

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
        'apache_sites_enabled' => '/etc/apache2/sites-enabled',
        'project_inventory' => '/var/www/nimbly-site/ext/data/projects',
        'project_alias_overrides' => [],
        'runtime_policy' => [
            'ubuntu_release' => 'current-lts',
            'php_line' => 'ubuntu-default',
            'php_handler' => 'php-fpm',
        ],
        'known_routes' => [],
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
    $platform = host_audit_platform();
    $php = host_audit_php_runtime();
    foreach (host_audit_runtime_policy_findings($platform, $php, (array)$config['runtime_policy']) as $finding) {
        $findings[] = $finding;
    }
    if (!empty($platform['release_upgrade']['available'])) {
        $target = (string)($platform['release_upgrade']['target'] ?? 'new release');
        $findings[] = host_audit_finding(
            'system:release-upgrade',
            'warning',
            'host',
            'Operating system release upgrade is available',
            $target . ' · planned maintenance required'
        );
    }
    if (!empty($platform['reboot_required'])) {
        $findings[] = host_audit_finding(
            'system:reboot-required',
            'warning',
            'host',
            'Server reboot is required',
            'Planned maintenance required'
        );
    }
    if (($platform['security_updates'] ?? 0) > 0) {
        $findings[] = host_audit_finding(
            'system:security-updates',
            'warning',
            'host',
            'Security updates are pending',
            (int)$platform['security_updates'] . ' package(s) · planned maintenance required'
        );
    }

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
        'cpu_count' => host_audit_cpu_count(),
        'load_average' => function_exists('sys_getloadavg') ? sys_getloadavg() : null,
        'disk_root_used_percent' => $disk_used_percent,
        'memory' => $memory,
        'failed_units' => $failed_units,
        'services' => $services,
        'config_tests' => $config_test_status,
        'platform' => $platform,
        'php' => $php,
        'runtime_policy' => (array)$config['runtime_policy'],
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
    $fail2ban_activity = host_audit_fail2ban_activity($context['since']);

    return [
        'ssh' => array_intersect_key($ssh, $expected),
        'fail2ban_jails' => $jail_status,
        'new_bans' => $fail2ban_activity['new_bans'],
        'ssh_failures' => $fail2ban_activity['ssh_failures'],
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

    $status_counts = ['2xx' => 0, '3xx' => 0, '4xx' => 0, '5xx' => 0];
    $requests = 0;
    $project_metrics = [];
    $route_5xx = [];
    $rejected_method_probes = 0;
    $not_found_total = 0;
    $not_found_routes = [];
    foreach (array_unique($access_files) as $file) {
        $file_project = host_audit_log_project(
            $file,
            (array)($context['access_log_projects'] ?? [])
        );
        host_audit_each_line($file, function (string $line) use (
            $context,
            $file_project,
            &$findings,
            &$requests,
            &$status_counts,
            &$project_metrics,
            &$route_5xx,
            &$rejected_method_probes,
            &$not_found_total,
            &$not_found_routes
        ): void {
            $entry = host_audit_parse_access_line($line);
            if ($entry === null) {
                return;
            }
            if ($entry['timestamp'] < $context['since']) {
                return;
            }
            $requests++;
            if (host_audit_501_is_rejected_method_probe($entry)) {
                $rejected_method_probes++;
                return;
            }
            $bucket = (int)floor($entry['status'] / 100) . 'xx';
            if (isset($status_counts[$bucket])) {
                $status_counts[$bucket]++;
            }
            $attribution = host_audit_request_attribution(
                $entry,
                (array)($context['registered_projects'] ?? []),
                (array)($context['registered_hosts'] ?? []),
                (array)($context['known_hosts'] ?? [])
            );
            if ($file_project !== null) {
                $attribution = [
                    'project' => $file_project,
                    'type' => 'project',
                    'label' => $file_project,
                ];
            }
            $project = $attribution['project'];
            if ($project !== null) {
                $project_metrics[$project] ??= host_audit_empty_project_metrics();
                $project_metrics[$project]['requests']++;
                if (isset($project_metrics[$project]['status_counts'][$bucket])) {
                    $project_metrics[$project]['status_counts'][$bucket]++;
                }
                if ($bucket === '5xx') {
                    $project_metrics[$project]['http_5xx']++;
                }
            }
            if ($entry['status'] === 404) {
                $not_found_total++;
                $route = host_audit_normalize_route_pattern(
                    $entry['path'],
                    host_audit_known_routes_for_target(
                        (array)($context['config']['known_routes'] ?? []),
                        $attribution
                    )
                );
                $route_key = $attribution['label'] . "\n" . $route['pattern'];
                $not_found_routes[$route_key] ??= [
                    'project' => $project,
                    'target' => $attribution['label'],
                    'target_type' => $attribution['type'],
                    'host' => $entry['vhost'],
                    'path' => $route['pattern'],
                    'route_type' => $route['type'],
                    'count' => 0,
                    'first_seen' => $entry['timestamp'],
                    'last_seen' => $entry['timestamp'],
                ];
                $not_found_routes[$route_key]['count']++;
                $not_found_routes[$route_key]['first_seen'] = min(
                    $not_found_routes[$route_key]['first_seen'],
                    $entry['timestamp']
                );
                $not_found_routes[$route_key]['last_seen'] = max(
                    $not_found_routes[$route_key]['last_seen'],
                    $entry['timestamp']
                );
            }
            if ($entry['status'] < 500) {
                return;
            }
            $route_key = $entry['method'] . ' ' . $entry['path'];
            $route_5xx[$route_key] = ($route_5xx[$route_key] ?? 0) + 1;
            $findings[] = host_audit_finding(
                'apache:5xx:' . $entry['status'] . ':' . host_audit_id($entry['path']),
                $entry['status'] >= 503 ? 'critical' : 'warning',
                'host',
                "HTTP {$entry['status']} response",
                $route_key,
                $entry['timestamp'],
                $project
            );
        });
    }

    $php_counts = ['fatal' => 0, 'warning' => 0];
    $php_summaries = ['fatal' => [], 'warning' => []];
    foreach (array_unique($error_files) as $file) {
        $file_project = host_audit_log_project(
            $file,
            (array)($context['error_log_projects'] ?? [])
        );
        host_audit_each_line($file, function (string $line) use (
            $context,
            $file_project,
            &$findings,
            &$php_counts,
            &$php_summaries,
            &$project_metrics
        ): void {
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
                $php_summaries['fatal'][$message] = true;
                host_audit_record_project_php_event(
                    $project_metrics,
                    $file_project,
                    $line,
                    (array)($context['registered_projects'] ?? [])
                );
                $findings[] = host_audit_finding(
                    'php:fatal:' . host_audit_id($message),
                    'critical',
                    'host',
                    'PHP fatal error',
                    $message,
                    $timestamp,
                    $file_project
                );
            } elseif (preg_match('/PHP Warning:\s*(.+)$/i', $line, $match)) {
                $php_counts['warning']++;
                $message = host_audit_normalize_message($match[1]);
                $php_summaries['warning'][$message] = true;
                host_audit_record_project_php_event(
                    $project_metrics,
                    $file_project,
                    $line,
                    (array)($context['registered_projects'] ?? [])
                );
                $findings[] = host_audit_finding(
                    'php:warning:' . host_audit_id($message),
                    'warning',
                    'host',
                    'PHP warning',
                    $message,
                    $timestamp,
                    $file_project
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
    arsort($route_5xx);
    $not_found_routes = array_values($not_found_routes);
    usort($not_found_routes, function (array $left, array $right): int {
        return ($right['count'] <=> $left['count'])
            ?: strcmp((string)$left['path'], (string)$right['path']);
    });
    $known_route_404_count = array_sum(array_map(
        fn(array $route): int => $route['route_type'] === 'known' ? (int)$route['count'] : 0,
        $not_found_routes
    ));
    foreach ($not_found_routes as $route) {
        // A request proves that a URL was tried, not that the application owns it.
        // Only authoritative route knowledge may turn a 404 into a health finding.
        if (!host_audit_404_creates_finding($route)) {
            continue;
        }
        $finding = host_audit_finding(
            'apache:404:' . host_audit_id((string)$route['target']) . ':' . host_audit_id((string)$route['path']),
            'warning',
            $route['project'] === null ? 'host' : 'project',
            'Known route returned 404',
            (string)$route['path'],
            (int)$route['first_seen'],
            $route['project']
        );
        $finding['count'] = (int)$route['count'];
        $finding['last_seen'] = gmdate('c', (int)$route['last_seen']);
        $finding['host'] = (string)($route['host'] ?? '');
        $finding['expected'] = 'non-404 response';
        $finding['observed'] = '404';
        $findings[] = $finding;
    }
    foreach ($not_found_routes as &$route) {
        $route['first_seen'] = gmdate('c', $route['first_seen']);
        $route['last_seen'] = gmdate('c', $route['last_seen']);
    }
    unset($route);
    ksort($project_metrics);
    $service = host_audit_service_status('apache2');

    return [
        'service' => $service['status'],
        'uptime_seconds' => $service['uptime_seconds'],
        'access_logs' => array_values(array_unique($access_files)),
        'error_logs' => array_values(array_unique($error_files)),
        'requests' => $requests,
        'status_counts' => $status_counts,
        'http_5xx' => $status_counts['5xx'],
        'top_problem_route' => array_key_first($route_5xx),
        'rejected_method_probes' => $rejected_method_probes,
        'not_found' => [
            'total' => $not_found_total,
            'known_route_total' => $known_route_404_count,
            'unclassified_total' => $not_found_total - $known_route_404_count,
            'unique' => count($not_found_routes),
            'routes' => array_slice($not_found_routes, 0, 50),
        ],
        'php_events' => $php_counts,
        'php_event_summaries' => [
            'fatal' => array_slice(array_keys($php_summaries['fatal']), 0, 5),
            'warning' => array_slice(array_keys($php_summaries['warning']), 0, 5),
        ],
        'worker_exhaustion_or_crashes' => count(array_filter(
            $findings,
            fn(array $finding): bool => str_starts_with(
                (string)($finding['id'] ?? ''),
                'apache:capacity-or-crash:'
            )
        )),
        'projects' => $project_metrics,
    ];
}

function host_audit_404_creates_finding(array $route): bool
{
    return ($route['route_type'] ?? '') === 'known' && (int)($route['count'] ?? 0) > 0;
}

function host_audit_normalize_route_pattern(string $path, array $known_routes = []): array
{
    $normalized = '/' . trim(rawurldecode(parse_url($path, PHP_URL_PATH) ?: $path), '/');
    if ($normalized === '//') {
        $normalized = '/';
    }
    if (preg_match('~^/password-reset/[^/]+/[^/]+/?$~', $normalized)) {
        return ['pattern' => '/password-reset/(uuid)/(key)', 'type' => 'dynamic'];
    }
    $known = array_map(
        fn(string $route): string => '/' . trim($route, '/'),
        array_map('strval', $known_routes)
    );
    if (in_array($normalized, $known, true)) {
        return ['pattern' => $normalized, 'type' => 'known'];
    }
    $pattern = preg_replace('~/[0-9a-f]{16,}(?=/|$)~i', '/(id)', $normalized) ?? $normalized;
    $pattern = preg_replace('~/\d+(?=/|$)~', '/(id)', $pattern) ?? $pattern;
    return ['pattern' => $pattern, 'type' => 'dynamic'];
}

function host_audit_known_routes_for_target(array $configured_routes, array $attribution): array
{
    if (array_is_list($configured_routes)) {
        return [];
    }
    foreach (['project', 'label'] as $field) {
        $target = (string)($attribution[$field] ?? '');
        if ($target !== '' && is_array($configured_routes[$target] ?? null)) {
            return array_values(array_map('strval', $configured_routes[$target]));
        }
    }
    return [];
}

function host_audit_501_is_rejected_method_probe(array $entry): bool
{
    if ((int)($entry['status'] ?? 0) !== 501) {
        return false;
    }

    $method = strtoupper(trim((string)($entry['method'] ?? '')));
    $known_methods = [
        'GET',
        'HEAD',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'CONNECT',
        'OPTIONS',
        'TRACE',
        'PROPFIND',
        'PROPPATCH',
        'MKCOL',
        'COPY',
        'MOVE',
        'LOCK',
        'UNLOCK',
        'SEARCH',
    ];

    return $method !== '' && !in_array($method, $known_methods, true);
}

function host_audit_projects(array $context, array &$findings): array
{
    $inventory_path = (string)$context['config']['project_inventory'];
    if (!is_dir($inventory_path)) {
        $findings[] = host_audit_finding(
            'projects:master-inventory',
            'critical',
            'host',
            'Master project inventory is unavailable',
            $inventory_path
        );
        return [
            'checks' => [],
            'environments' => [],
            'aliases' => [],
            'hosts' => [],
            'known_hosts' => [],
            'access_log_projects' => [],
            'error_log_projects' => [],
        ];
    }

    $scheduler_paths = host_audit_scheduler_project_paths(
        (string)$context['config']['scheduler_config'],
        $findings
    );
    $apache_aliases = host_audit_apache_aliases(
        (string)$context['config']['apache_sites_enabled']
    );
    $apache_vhosts = host_audit_apache_vhosts(
        (string)$context['config']['apache_sites_enabled']
    );
    $apache_logs = host_audit_apache_log_roots(
        (string)$context['config']['apache_sites_enabled'],
        (string)$context['config']['apache_log_dir']
    );
    $overrides = (array)($context['config']['project_alias_overrides'] ?? []);
    $checks = [];
    $environments = [];
    $aliases = [];
    $project_paths = [];
    foreach (glob(rtrim($inventory_path, '/') . '/*') ?: [] as $record_path) {
        if (!is_file($record_path) || basename($record_path) === '.meta') {
            continue;
        }
        $project = json_decode((string)file_get_contents($record_path), true);
        if (!is_array($project)
            || empty($project['is_active'])
            || empty($project['has_hosting'])) {
            continue;
        }
        $name = trim((string)($project['name'] ?? ''));
        $slug = host_audit_id((string)($project['name_slug'] ?? $name));
        if ($name === '' || $slug === '') {
            $findings[] = host_audit_finding(
                'projects:invalid-record:' . host_audit_id(basename($record_path)),
                'critical',
                'host',
                'Active hosted project has no usable name',
                basename($record_path)
            );
            continue;
        }
        $apache_project = host_audit_apache_project(
            $slug,
            $apache_aliases,
            $apache_vhosts,
            $overrides
        );
        $path = $apache_project['path'];
        $check = host_audit_project($name, $path, $context, $findings);
        $check['scheduler'] = isset($scheduler_paths[$path])
            ? 'Active'
            : 'Not scheduled';
        $checks[$name] = $check;
        if ($path !== '') {
            $project_paths[rtrim($path, '/')] = $name;
        }
        if ($apache_project['alias'] !== '') {
            $aliases[host_audit_id($apache_project['alias'])] = $name;
        }
        if (!empty($check['environment'])) {
            $environments[] = $check['environment'];
        }
    }
    foreach ($checks as $name => &$check) {
        $check['status'] = host_audit_project_status((string)$name, $findings);
    }
    unset($check);
    $hosts = [];
    foreach ($apache_vhosts as $host => $document_root) {
        if (str_starts_with((string)$document_root, '@redirect:')) {
            continue;
        }
        $project_path = rtrim($document_root, '/');
        if (isset($project_paths[$project_path])) {
            $hosts[$host] = $project_paths[$project_path];
        }
    }
    $access_log_projects = [];
    foreach ($apache_logs['access'] as $log_path => $document_root) {
        if (isset($project_paths[$document_root])) {
            $access_log_projects[$log_path] = $project_paths[$document_root];
        }
    }
    $error_log_projects = [];
    foreach ($apache_logs['error'] as $log_path => $document_root) {
        if (isset($project_paths[$document_root])) {
            $error_log_projects[$log_path] = $project_paths[$document_root];
        }
    }
    foreach ($apache_vhosts as $host => $document_root) {
        if (!str_starts_with((string)$document_root, '@redirect:')) {
            continue;
        }
        $target_host = substr((string)$document_root, strlen('@redirect:'));
        $target_project = host_audit_project_from_host($target_host, $hosts);
        if ($target_project !== null) {
            $hosts[$host] = $target_project;
        }
    }
    ksort($checks);
    return [
        'checks' => $checks,
        'environments' => array_values(array_unique($environments)),
        'aliases' => $aliases,
        'hosts' => $hosts,
        'known_hosts' => array_fill_keys(array_keys($apache_vhosts), true),
        'access_log_projects' => $access_log_projects,
        'error_log_projects' => $error_log_projects,
    ];
}

function host_audit_scheduler_project_paths(string $config_path, array &$findings): array
{
    $scheduler_config = is_file($config_path)
        ? json_decode((string)file_get_contents($config_path), true)
        : null;
    if (!is_array($scheduler_config) || !isset($scheduler_config['projects'])
        || !is_array($scheduler_config['projects'])) {
        $findings[] = host_audit_finding(
            'scheduler:project-registry',
            'warning',
            'host',
            'Scheduler project registry is missing or invalid',
            $config_path
        );
        return [];
    }
    $paths = [];
    foreach ($scheduler_config['projects'] as $project) {
        if (!is_array($project) || ($project['enabled'] ?? true) === false) {
            continue;
        }
        $path = rtrim((string)($project['path'] ?? ''), '/');
        if ($path !== '') {
            $paths[$path] = true;
        }
    }
    return $paths;
}

function host_audit_apache_aliases(string $sites_enabled): array
{
    $aliases = [];
    foreach (glob(rtrim($sites_enabled, '/') . '/*') ?: [] as $config_path) {
        if (!is_file($config_path)) {
            continue;
        }
        foreach (file($config_path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (!preg_match(
                '~^\s*Alias\s+/([^/\s]+)/?\s+["\']?([^"\']+)["\']?\s*$~i',
                $line,
                $match
            )) {
                continue;
            }
            $alias = host_audit_id($match[1]);
            $path = rtrim(trim($match[2]), '/');
            if ($alias !== '' && str_starts_with($path, '/var/www/')) {
                $aliases[$alias] = $path;
            }
        }
    }
    return $aliases;
}

function host_audit_apache_vhosts(string $sites_enabled): array
{
    $vhosts = [];
    foreach (glob(rtrim($sites_enabled, '/') . '/*') ?: [] as $config_path) {
        if (!is_file($config_path)) {
            continue;
        }
        $current = null;
        foreach (file($config_path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim(preg_replace('/\s+#.*$/', '', $line) ?? $line);
            if (preg_match('/^<VirtualHost\b/i', $line)) {
                $current = ['hosts' => [], 'document_root' => '', 'redirect_host' => ''];
                continue;
            }
            if ($current === null) {
                continue;
            }
            if (preg_match('/^<\/VirtualHost>/i', $line)) {
                foreach ($current['hosts'] as $host) {
                    if ($current['document_root'] !== '') {
                        $vhosts[$host] = $current['document_root'];
                    } elseif ($current['redirect_host'] !== '') {
                        $vhosts[$host] = '@redirect:' . $current['redirect_host'];
                    }
                }
                $current = null;
                continue;
            }
            if (preg_match('/^Server(?:Name|Alias)\s+(.+)$/i', $line, $match)) {
                foreach (preg_split('/\s+/', trim($match[1])) ?: [] as $host) {
                    $host = host_audit_normalize_vhost($host, true);
                    if ($host !== null) {
                        $current['hosts'][] = $host;
                    }
                }
            } elseif (preg_match('/^DocumentRoot\s+["\']?([^"\']+)["\']?$/i', $line, $match)) {
                $current['document_root'] = rtrim(trim($match[1]), '/');
            } elseif (preg_match(
                '~^Redirect(?:\s+\w+)?\s+/\s+https?://([^/\s]+)~i',
                $line,
                $match
            )) {
                $current['redirect_host'] = host_audit_normalize_vhost($match[1]) ?? '';
            }
        }
    }
    ksort($vhosts);
    return $vhosts;
}

function host_audit_apache_log_roots(string $sites_enabled, string $log_dir): array
{
    $logs = ['access' => [], 'error' => []];
    foreach (glob(rtrim($sites_enabled, '/') . '/*') ?: [] as $config_path) {
        if (!is_file($config_path)) {
            continue;
        }
        $current = null;
        foreach (file($config_path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim(preg_replace('/\s+#.*$/', '', $line) ?? $line);
            if (preg_match('/^<VirtualHost\b/i', $line)) {
                $current = ['document_root' => '', 'access' => [], 'error' => []];
                continue;
            }
            if ($current === null) {
                continue;
            }
            if (preg_match('/^<\/VirtualHost>/i', $line)) {
                if ($current['document_root'] !== '') {
                    foreach (['access', 'error'] as $type) {
                        foreach ($current[$type] as $path) {
                            $logs[$type][$path] = $current['document_root'];
                        }
                    }
                }
                $current = null;
                continue;
            }
            if (preg_match('/^DocumentRoot\s+["\']?([^"\']+)["\']?$/i', $line, $match)) {
                $current['document_root'] = rtrim(trim($match[1]), '/');
                continue;
            }
            if (preg_match('/^CustomLog\s+["\']?([^"\'\s]+)["\']?/i', $line, $match)) {
                $current['access'][] = host_audit_apache_log_path($match[1], $log_dir);
            } elseif (preg_match('/^ErrorLog\s+["\']?([^"\'\s]+)["\']?/i', $line, $match)) {
                $current['error'][] = host_audit_apache_log_path($match[1], $log_dir);
            }
        }
    }
    return $logs;
}

function host_audit_apache_log_path(string $path, string $log_dir): string
{
    $path = str_replace('${APACHE_LOG_DIR}', rtrim($log_dir, '/'), trim($path));
    return str_starts_with($path, '/') ? $path : rtrim($log_dir, '/') . '/' . $path;
}

function host_audit_apache_project(
    string $slug,
    array $aliases,
    array $vhosts,
    array $overrides
): array
{
    $alias = isset($overrides[$slug]) && is_string($overrides[$slug])
        ? host_audit_id($overrides[$slug])
        : '';
    if ($alias === '') {
        $compact = str_replace('-', '', $slug);
        $trimmed = preg_replace('/-(?:site|blog|app)$/', '', $slug) ?: $slug;
        foreach ($aliases as $candidate => $path) {
            if ($candidate === $slug
                || str_replace('-', '', $candidate) === $compact
                || $candidate === $trimmed) {
                $alias = $candidate;
                break;
            }
        }
    }
    if ($alias !== '') {
        return [
            'alias' => $alias,
            'path' => (string)($aliases[$alias] ?? ''),
        ];
    }

    $compact = str_replace('-', '', $slug);
    $trimmed = preg_replace('/-(?:site|blog|app)$/', '', $slug) ?: $slug;
    foreach (array_unique(array_values($vhosts)) as $path) {
        if (str_starts_with((string)$path, '@redirect:')) {
            continue;
        }
        $candidate = host_audit_id(basename(rtrim((string)$path, '/')));
        if ($candidate === $slug
            || str_replace('-', '', $candidate) === $compact
            || $candidate === $trimmed) {
            return [
                'alias' => '',
                'path' => (string)$path,
            ];
        }
    }
    return ['alias' => '', 'path' => ''];
}

function host_audit_project_status(string $name, array $findings): string
{
    $status = 'healthy';
    foreach ($findings as $finding) {
        if (($finding['project'] ?? null) !== $name) {
            continue;
        }
        if (($finding['severity'] ?? '') === 'critical') {
            return 'critical';
        }
        if (($finding['severity'] ?? '') === 'warning') {
            $status = 'warning';
        }
    }
    return $status;
}

function host_audit_merge_project_metrics(array $projects, array $metrics): array
{
    foreach ($projects as $name => &$project) {
        $key = host_audit_id((string)$name);
        $values = $metrics[$name] ?? $metrics[$key] ?? host_audit_empty_project_metrics();
        $project['requests'] = $values['requests'];
        $project['status_counts'] = $values['status_counts'];
        $project['http_5xx'] = $values['http_5xx'];
        $project['php_errors'] = $values['php_errors'];
    }
    unset($project);
    return $projects;
}

function host_audit_project(string $name, string $path, array $context, array &$findings): array
{
    if ($path === '' || !is_dir($path . '/core') || !is_dir($path . '/ext')) {
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
            if (str_contains($message, 'event=request.validated_route_404')) {
                $system_log_events++;
                $finding = host_audit_finding(
                    'request:validated-reset-404:' . host_audit_id($name),
                    'warning',
                    'project',
                    'Validated password reset route returned 404',
                    '/password-reset/(uuid)/(key) · validated_reset_route_not_accepted',
                    $timestamp,
                    $name
                );
                $finding['expected'] = 'password reset form';
                $finding['observed'] = '404';
                $finding['host'] = gethostname() ?: php_uname('n');
                $findings[] = $finding;
                return;
            }
            if (host_audit_project_log_is_informational($message)) {
                return;
            }
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

function host_audit_project_log_is_informational(string $message): bool
{
    return preg_match(
        '/Nimbly:\s+(?:Error:\s+)?password reset requested for unknown email\b/i',
        $message
    ) === 1;
}

function host_audit_project_jobs(
    string $name,
    string $path,
    array $context,
    array &$findings
): array {
    $job_dir = $path . '/ext/data/.jobs';
    $counts = ['queued' => 0, 'running' => 0, 'done' => 0, 'failed' => 0, 'failed_recent' => 0, 'invalid' => 0];
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
            $failed_at = (int)($job['_modified'] ?? 0);
            if ($failed_at <= 0) {
                $failed_at = filemtime($file) ?: $context['now'];
            }
            if ($failed_at < $context['since']) {
                continue;
            }
            $counts['failed_recent']++;
            $message = (string)($job['last_error'] ?? $job['error'] ?? basename($file));
            $findings[] = host_audit_finding(
                'job:failed:' . host_audit_id($name) . ':' . host_audit_id(basename($file)),
                'critical',
                'project',
                'Nimbly job failed',
                host_audit_normalize_message($message),
                $failed_at,
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
    $latest_by_project = [];
    $last_success = 0;
    host_audit_each_line($log_path, function (string $line) use (
        $context,
        &$last_run,
        &$latest_by_project,
        &$last_success
    ): void {
        if (!preg_match('/^(?<date>\S+).*project=(?<project>\S+).*exit_code=(?<exit>\d+)/', $line, $match)) {
            return;
        }
        $timestamp = strtotime($match['date']);
        if ($timestamp === false) {
            return;
        }
        $last_run = max($last_run ?? 0, $timestamp);
        if ((int)$match['exit'] === 0) {
            $last_success = max($last_success, $timestamp);
        }
        $latest_by_project[$match['project']] = [
            'timestamp' => $timestamp,
            'exit_code' => (int)$match['exit'],
            'line' => trim($line),
        ];
    });

    foreach ($latest_by_project as $project => $latest) {
        $resolved_orchestrator_failure = $project === 'orchestrator'
            && $last_success > $latest['timestamp'];
        if ($latest['timestamp'] < $context['since'] || $latest['exit_code'] === 0 || $resolved_orchestrator_failure) {
            continue;
        }
        $failures++;
        $findings[] = host_audit_finding(
            'scheduler:failure:' . host_audit_id($project),
            'critical',
            'project',
            'Project scheduler failed',
            $latest['line'],
            $latest['timestamp'],
            $project
        );
    }

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
        if (str_starts_with((string)$id, 'request:validated-reset-404:')
            && $grouped[$id]['count'] >= 2) {
            $grouped[$id]['severity'] = 'critical';
        }
    }
    return array_values($grouped);
}

function host_audit_compare_findings(array $a, array $b): int
{
    $weights = ['critical' => 0, 'warning' => 1, 'notice' => 2, 'ok' => 3, 'unknown' => 4];
    $severity = ($weights[$a['severity']] ?? 9) <=> ($weights[$b['severity']] ?? 9);
    return $severity !== 0 ? $severity : strcmp($a['id'], $b['id']);
}

function host_audit_summary(array $findings): array
{
    $summary = ['critical' => 0, 'warning' => 0, 'notice' => 0, 'ok' => 0, 'unknown' => 0];
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

function host_audit_platform(): array
{
    $release = host_audit_parse_key_values(
        is_readable('/etc/os-release') ? (string)file_get_contents('/etc/os-release') : ''
    );
    $notice = '';
    foreach ([
        '/var/lib/ubuntu-release-upgrader/release-upgrade-available',
        '/var/lib/update-notifier/release-upgrade-available',
    ] as $path) {
        if (is_readable($path)) {
            $notice = trim((string)file_get_contents($path));
            if ($notice !== '') {
                break;
            }
        }
    }
    if ($notice === '' && is_file('/usr/bin/do-release-upgrade')) {
        $upgrade_check = host_audit_run_command(['do-release-upgrade', '-c'], 30);
        $notice = trim($upgrade_check['stdout'] . "\n" . $upgrade_check['stderr']);
    }

    $updates_notice = is_readable('/var/lib/update-notifier/updates-available')
        ? (string)file_get_contents('/var/lib/update-notifier/updates-available')
        : '';
    $security_updates = null;
    if (preg_match('/(\d+)\s+(?:of these updates are|update(?:s)? (?:is|are)) standard security updates/i', $updates_notice, $match)) {
        $security_updates = (int)$match[1];
    }
    if ($security_updates === null && is_file('/usr/bin/pro')) {
        $security_status = host_audit_run_command(
            ['pro', 'security-status', '--format', 'json'],
            20
        );
        $security_updates = host_audit_security_updates_from_pro($security_status['stdout']);
    }

    return [
        'name' => trim((string)($release['PRETTY_NAME'] ?? $release['NAME'] ?? ''), '"'),
        'version_id' => trim((string)($release['VERSION_ID'] ?? ''), '"'),
        'release_upgrade' => host_audit_release_upgrade($notice),
        'reboot_required' => is_file('/var/run/reboot-required'),
        'reboot_packages' => is_readable('/var/run/reboot-required.pkgs')
            ? array_values(array_filter(array_map('trim', file('/var/run/reboot-required.pkgs', FILE_IGNORE_NEW_LINES) ?: [])))
            : [],
        'security_updates' => $security_updates,
    ];
}

function host_audit_security_updates_from_pro(string $output): ?int
{
    $status = json_decode($output, true);
    $updates = is_array($status) ? ($status['summary']['num_standard_security_updates'] ?? null) : null;
    return is_numeric($updates) ? (int)$updates : null;
}

function host_audit_release_upgrade(string $notice): array
{
    $target = null;
    if (preg_match('/New release [\'"]([^\'"]+)[\'"] available/i', $notice, $match)
        || preg_match('/New release:\s*([^\s]+)/i', $notice, $match)) {
        $target = trim($match[1]);
    }
    return [
        'available' => $target !== null,
        'target' => $target,
    ];
}

function host_audit_php_runtime(): array
{
    $modules = host_audit_run_command(['apache2ctl', '-M'], 10);
    foreach (glob('/etc/apache2/mods-enabled/php*.load') ?: [] as $module_path) {
        $modules['stdout'] .= "\n" . basename($module_path);
    }
    $fpm_config = '';
    $config_paths = array_merge(
        glob('/etc/apache2/conf-enabled/*.conf') ?: [],
        glob('/etc/apache2/sites-enabled/*') ?: []
    );
    foreach ($config_paths as $path) {
        if (is_readable($path)) {
            $fpm_config .= "\n" . file_get_contents($path);
        }
    }
    $handler = host_audit_php_handler(
        $modules['stdout'] . "\n" . $modules['stderr'],
        $fpm_config
    );
    $web_version = null;
    if ($handler === 'php-fpm'
        && preg_match('/php(\d+\.\d+)-fpm(?:\.sock)?/i', $fpm_config, $match)) {
        $web_version = $match[1];
    }
    return [
        'version' => $web_version ?? PHP_VERSION,
        'cli_version' => PHP_VERSION,
        'sapi' => PHP_SAPI,
        'handler' => $handler,
        'cli_extensions' => array_values(array_map('strtolower', get_loaded_extensions())),
    ];
}

function host_audit_runtime_policy_findings(
    array $platform,
    array $php,
    array $policy,
    ?string $host = null,
    ?string $ubuntu_default_php = null
): array {
    $host = $host ?: (gethostname() ?: php_uname('n'));
    $ubuntu_target = host_audit_release_version((string)($platform['release_upgrade']['target'] ?? ''));
    $ubuntu_is_current_lts = str_contains(strtoupper((string)($platform['name'] ?? '')), 'LTS')
        && empty($platform['release_upgrade']['available']);
    $required_php = (string)($policy['php_line'] ?? '');
    if ($required_php === 'ubuntu-default') {
        $required_php = $ubuntu_default_php ?? host_audit_ubuntu_default_php_minor();
    }
    $web_php = host_audit_php_minor((string)($php['version'] ?? ''));
    $checks = [
        [
            'id' => 'runtime:ubuntu-version',
            'title' => 'Ubuntu release differs from infrastructure policy',
            'enabled' => ($policy['ubuntu_release'] ?? '') === 'current-lts',
            'aligned' => $ubuntu_is_current_lts,
            'expected' => $ubuntu_target !== '' ? $ubuntu_target . ' LTS' : 'current Ubuntu LTS',
            'observed' => (string)($platform['version_id'] ?? ''),
        ],
        [
            'id' => 'runtime:php-version',
            'title' => 'Web PHP version differs from infrastructure policy',
            'enabled' => $required_php !== '',
            'aligned' => $required_php !== 'unknown' && $web_php === $required_php,
            'expected' => $required_php === 'unknown'
                ? 'Ubuntu default PHP line'
                : (($policy['php_line'] ?? '') === 'ubuntu-default'
                    ? 'Ubuntu default PHP ' . $required_php
                    : 'PHP ' . $required_php),
            'observed' => $web_php,
        ],
        [
            'id' => 'runtime:php-handler',
            'title' => 'Web PHP handler differs from infrastructure policy',
            'enabled' => isset($policy['php_handler']),
            'aligned' => (string)($policy['php_handler'] ?? '') === (string)($php['handler'] ?? 'unknown'),
            'expected' => (string)($policy['php_handler'] ?? ''),
            'observed' => (string)($php['handler'] ?? 'unknown'),
        ],
    ];
    $cli_minor = host_audit_php_minor((string)($php['cli_version'] ?? ''));
    $web_minor = host_audit_php_minor((string)($php['version'] ?? ''));
    if ($cli_minor !== $web_minor) {
        $checks[] = [
            'id' => 'runtime:php-cli-web-mismatch',
            'title' => 'CLI and web PHP versions differ',
            'enabled' => true,
            'aligned' => false,
            'expected' => $web_minor,
            'observed' => $cli_minor,
        ];
    }
    $findings = [];
    foreach ($checks as $check) {
        if (!$check['enabled'] || $check['aligned']) {
            continue;
        }
        $finding = host_audit_finding(
            $check['id'],
            'notice',
            'host',
            $check['title'],
            'expected ' . $check['expected'] . ', observed ' . ($check['observed'] ?: 'unknown'),
            time()
        );
        $finding['expected'] = $check['expected'];
        $finding['observed'] = $check['observed'] ?: 'unknown';
        $finding['host'] = $host;
        $findings[] = $finding;
    }
    return $findings;
}

function host_audit_release_version(string $release): string
{
    return preg_match('/(\d+\.\d+)/', $release, $match) ? $match[1] : '';
}

function host_audit_ubuntu_default_php_minor(): string
{
    $result = host_audit_run_command(['apt-cache', 'show', 'php'], 10);
    if ($result['exit_code'] !== 0
        || !preg_match('/^Depends:\s*php(\d+\.\d+)\b/im', $result['stdout'], $match)) {
        return 'unknown';
    }
    return $match[1];
}

function host_audit_php_minor(string $version): string
{
    return preg_match('/^(\d+\.\d+)/', trim($version), $match) ? $match[1] : 'unknown';
}

function host_audit_php_handler(string $apache_modules, string $fpm_config): string
{
    if (preg_match('/SetHandler\s+"?proxy:unix:.*php.*fpm/i', $fpm_config)
        || preg_match('/php\d+(?:\.\d+)?-fpm\.sock/i', $fpm_config)) {
        return 'php-fpm';
    }
    if (preg_match('/\bphp(?:\d+)?_module\b/i', $apache_modules)
        || preg_match('/\bphp\d+(?:\.\d+)?\.load\b/i', $apache_modules)) {
        return 'apache-module';
    }
    return 'unknown';
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

function host_audit_fail2ban_activity(int $since): array
{
    $fail2ban = host_audit_run_command(
        ['journalctl', '-u', 'fail2ban', '--since', '@' . $since, '--no-pager', '-o', 'cat'],
        15
    );
    $ssh = host_audit_run_command(
        ['journalctl', '-u', 'ssh', '--since', '@' . $since, '--no-pager', '-o', 'cat'],
        15
    );
    return host_audit_parse_security_activity($fail2ban['stdout'], $ssh['stdout']);
}

function host_audit_parse_security_activity(string $fail2ban, string $ssh): array
{
    $ssh_failures = 0;
    foreach (preg_split('/\R/', $ssh) ?: [] as $line) {
        if (preg_match('/Failed password|Invalid user|authentication failure/i', $line)) {
            $ssh_failures++;
        }
    }
    return [
        'new_bans' => preg_match_all('/\bBan\s+\d{1,3}(?:\.\d{1,3}){3}\b/', $fail2ban),
        'ssh_failures' => $ssh_failures,
    ];
}

function host_audit_parse_access_line(string $line): ?array
{
    if (!preg_match(
        '/\[(?<date>[^\]]+)\]\s+"(?<method>[A-Z]+)\s+(?<target>\S+)[^"]*"\s+(?<status>\d{3})\s/',
        $line,
        $match
    )) {
        return null;
    }
    $timestamp = strtotime($match['date']);
    if ($timestamp === false) {
        return null;
    }
    $path = parse_url($match['target'], PHP_URL_PATH) ?: $match['target'];
    $prefix_end = strpos($line, '[');
    $prefix = $prefix_end === false ? '' : trim(substr($line, 0, $prefix_end));
    $prefix_parts = preg_split('/\s+/', $prefix) ?: [];
    $vhost = count($prefix_parts) >= 2
        ? host_audit_normalize_vhost((string)$prefix_parts[0])
        : null;
    return [
        'timestamp' => $timestamp,
        'method' => $match['method'],
        'path' => $path,
        'status' => (int)$match['status'],
        'vhost' => $vhost,
    ];
}

function host_audit_normalize_vhost(string $value, bool $allow_wildcard = false): ?string
{
    $host = strtolower(trim($value, " \t\n\r\0\x0B."));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    if ($host === ''
        || filter_var($host, FILTER_VALIDATE_IP)
        || (!$allow_wildcard && str_contains($host, '*'))
        || preg_match($allow_wildcard
            ? '/^(?:\*\.)?[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/'
            : '/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/', $host) !== 1) {
        return null;
    }
    return $host;
}

function host_audit_project_from_host(string $host, array $registered_hosts): ?string
{
    $host = host_audit_normalize_vhost($host) ?? '';
    if ($host === '') {
        return null;
    }
    if (isset($registered_hosts[$host])) {
        return (string)$registered_hosts[$host];
    }
    foreach ($registered_hosts as $pattern => $project) {
        if (str_starts_with((string)$pattern, '*.')
            && str_ends_with($host, substr((string)$pattern, 1))) {
            return (string)$project;
        }
    }
    return null;
}

function host_audit_request_attribution(
    array $entry,
    array $registered_projects,
    array $registered_hosts,
    array $known_hosts
): array {
    $host = (string)($entry['vhost'] ?? '');
    if ($host === '' && count($known_hosts) === 1) {
        $host = (string)array_key_first($known_hosts);
    }
    $project = host_audit_project_from_host($host, $registered_hosts);
    if ($project === null && $registered_projects !== []) {
        $project = host_audit_project_from_path(
            (string)($entry['path'] ?? ''),
            $registered_projects
        );
    }
    if ($project !== null) {
        return ['project' => $project, 'type' => 'project', 'label' => $project];
    }
    if ($host !== '' && host_audit_project_from_host($host, $known_hosts) !== null) {
        return ['project' => null, 'type' => 'server', 'label' => 'Server'];
    }
    return [
        'project' => null,
        'type' => 'unknown-host',
        'label' => $host === '' ? 'Unknown host' : 'Unknown host: ' . $host,
    ];
}

function host_audit_project_from_path(string $path, array $registered_projects = []): ?string
{
    if (!preg_match('~^/([^/?#]+)~', $path, $match)) {
        return null;
    }
    $segment = host_audit_id(rawurldecode($match[1]));
    if ($segment === '') {
        return null;
    }
    if ($registered_projects !== []) {
        if (array_is_list($registered_projects)) {
            return in_array($segment, $registered_projects, true) ? $segment : null;
        }
        return isset($registered_projects[$segment])
            ? (string)$registered_projects[$segment]
            : null;
    }
    return $segment;
}

function host_audit_empty_project_metrics(): array
{
    return [
        'requests' => 0,
        'status_counts' => ['2xx' => 0, '3xx' => 0, '4xx' => 0, '5xx' => 0],
        'http_5xx' => 0,
        'php_errors' => 0,
    ];
}

function host_audit_log_project(string $path, array $projects): ?string
{
    $base_path = preg_replace('/\.1$/', '', $path) ?: $path;
    return isset($projects[$base_path]) ? (string)$projects[$base_path] : null;
}

function host_audit_record_project_php_event(
    array &$metrics,
    ?string $file_project,
    string $line,
    array $registered_projects
): void {
    if ($file_project === null) {
        host_audit_add_project_php_event($metrics, $line, $registered_projects);
        return;
    }
    $metrics[$file_project] ??= host_audit_empty_project_metrics();
    $metrics[$file_project]['php_errors']++;
}

function host_audit_add_project_php_event(
    array &$metrics,
    string $line,
    array $registered_projects = []
): void
{
    if (!preg_match('~/var/www/([^/\s]+)/~', $line, $match)) {
        return;
    }
    $project = host_audit_id($match[1]);
    if ($registered_projects !== []) {
        if (array_is_list($registered_projects)) {
            if (!in_array($project, $registered_projects, true)) {
                return;
            }
        } elseif (isset($registered_projects[$project])) {
            $project = (string)$registered_projects[$project];
        } else {
            return;
        }
    }
    $metrics[$project] ??= host_audit_empty_project_metrics();
    $metrics[$project]['php_errors']++;
}

function host_audit_service_status(string $service): array
{
    $result = host_audit_run_command(
        [
            'systemctl',
            'show',
            $service,
            '-p',
            'ActiveState',
            '-p',
            'ActiveEnterTimestampMonotonic',
        ],
        5
    );
    $values = host_audit_parse_key_values($result['stdout']);
    $entered = (int)($values['ActiveEnterTimestampMonotonic'] ?? 0);
    $host_uptime = host_audit_uptime_seconds();
    $uptime = $entered > 0 && $host_uptime !== null
        ? max(0, $host_uptime - (int)floor($entered / 1000000))
        : null;
    return [
        'status' => $values['ActiveState'] ?? 'unknown',
        'uptime_seconds' => $uptime,
    ];
}

function host_audit_cpu_count(): ?int
{
    if (!is_file('/proc/cpuinfo')) {
        return null;
    }
    $count = preg_match_all('/^processor\s*:/m', (string)file_get_contents('/proc/cpuinfo'));
    return $count > 0 ? $count : null;
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
        return [
            'branch' => null,
            'revision' => null,
            'operation' => null,
            'pending_commits' => null,
        ];
    }
    $safe = ['git', '-c', 'safe.directory=' . $path, '-C', $path];
    $branch_result = host_audit_run_command(
        array_merge($safe, ['symbolic-ref', '--short', '-q', 'HEAD']),
        5
    );
    $revision_result = host_audit_run_command(
        array_merge($safe, ['rev-parse', '--verify', 'HEAD']),
        5
    );
    $branch = trim($branch_result['stdout']) ?: null;
    $revision = trim($revision_result['stdout']) ?: null;
    if ($branch === null || $revision === null) {
        $head = trim((string)@file_get_contents($git_path . '/HEAD'));
        $fallback_branch = str_starts_with($head, 'ref: refs/heads/')
            ? substr($head, strlen('ref: refs/heads/'))
            : null;
        $branch ??= $fallback_branch;
        if ($revision === null) {
            $revision = $fallback_branch !== null
                ? trim((string)@file_get_contents(
                    $git_path . '/refs/heads/' . $fallback_branch
                ))
                : $head;
            $revision = $revision !== '' ? $revision : null;
        }
    }
    $operation = null;
    if (is_dir($git_path . '/rebase-merge') || is_dir($git_path . '/rebase-apply')) {
        $operation = 'rebase';
    } elseif (is_file($git_path . '/MERGE_HEAD')) {
        $operation = 'merge';
    } elseif (is_file($git_path . '/CHERRY_PICK_HEAD')) {
        $operation = 'cherry-pick';
    }
    $pending_commits = null;
    $upstream = host_audit_run_command(
        array_merge($safe, ['rev-parse', '--abbrev-ref', '--symbolic-full-name', '@{upstream}']),
        5
    );
    if ($upstream['exit_code'] === 0 && trim($upstream['stdout']) !== '') {
        $pending = host_audit_run_command(
            array_merge($safe, ['rev-list', '--count', 'HEAD..@{upstream}']),
            10
        );
        if ($pending['exit_code'] === 0 && is_numeric(trim($pending['stdout']))) {
            $pending_commits = (int)trim($pending['stdout']);
        }
    }
    return [
        'branch' => $branch,
        'revision' => $revision,
        'operation' => $operation,
        'pending_commits' => $pending_commits,
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
