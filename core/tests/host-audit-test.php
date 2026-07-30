<?php

define('NIMBLY_HOST_AUDIT_LIBRARY', true);
require_once __DIR__ . '/../cli/host_audit.php';

function audit_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function audit_remove_fixture(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $items = scandir($path) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $item_path = $path . '/' . $item;
        if (is_dir($item_path)) {
            audit_remove_fixture($item_path);
        } else {
            unlink($item_path);
        }
    }
    rmdir($path);
}

audit_assert(host_audit_duration_seconds('24h') === 86400, 'parses hour duration');
audit_assert(host_audit_duration_seconds('2d') === 172800, 'parses day duration');
audit_assert(host_audit_duration_seconds('tomorrow') === null, 'rejects invalid duration');
audit_assert(
    host_audit_project_log_is_informational(
        'Nimbly: Error: password reset requested for unknown email example@example.com'
    ),
    'ignores legacy unknown-email password reset events'
);
audit_assert(
    host_audit_project_log_is_informational(
        'Nimbly: Password reset requested for unknown email example@example.com'
    ),
    'ignores informational unknown-email password reset events'
);

$release_upgrade = host_audit_release_upgrade(
    "New release '26.04 LTS' available.\nRun 'do-release-upgrade' to upgrade to it."
);
audit_assert($release_upgrade['available'] === true, 'detects available OS release upgrade');
audit_assert($release_upgrade['target'] === '26.04 LTS', 'extracts OS release target');
audit_assert(
    host_audit_release_upgrade('No new release found.')['available'] === false,
    'keeps current OS release healthy'
);
audit_assert(
    host_audit_php_handler(' php_module (shared)', '') === 'apache-module',
    'detects Apache PHP module'
);
audit_assert(
    host_audit_php_handler('php8.3.load', '') === 'apache-module',
    'detects enabled Apache PHP module file'
);
audit_assert(
    host_audit_php_handler(
        ' proxy_fcgi_module (shared)',
        'SetHandler "proxy:unix:/run/php/php8.4-fpm.sock|fcgi://localhost"'
    ) === 'php-fpm',
    'detects PHP-FPM handler'
);
audit_assert(
    host_audit_security_updates_from_pro(
        '{"summary":{"num_standard_security_updates":7}}'
    ) === 7,
    'parses Ubuntu Pro security updates'
);

$findings = [
    host_audit_finding(
        'php:warning:test',
        'warning',
        'project',
        'PHP warning',
        'first',
        100,
        'example'
    ),
    host_audit_finding(
        'php:warning:test',
        'warning',
        'project',
        'PHP warning',
        'second',
        200,
        'example'
    ),
];
$grouped = host_audit_group_findings($findings);
audit_assert(count($grouped) === 1, 'groups stable finding ids');
audit_assert($grouped[0]['count'] === 2, 'counts grouped findings');
audit_assert($grouped[0]['first_seen'] === gmdate('c', 100), 'keeps first timestamp');
audit_assert($grouped[0]['last_seen'] === gmdate('c', 200), 'keeps last timestamp');

$jails = host_audit_fail2ban_jails("Status\n`- Jail list:\tsshd, recidive, apache-php-scan\n");
audit_assert($jails === ['apache-php-scan', 'recidive', 'sshd'], 'parses jail list');

$counts = host_audit_fail2ban_counts(
    "Currently failed:\t4\nTotal failed:\t100\nCurrently banned:\t2\nTotal banned:\t50\n"
);
audit_assert($counts['currently_banned'] === 2, 'parses current bans');
audit_assert($counts['total_failed'] === 100, 'parses total failures');

$access = host_audit_parse_access_line(
    'stage.example:443 10.0.0.1 - - [24/Jul/2026:20:30:00 +0000] '
    . '"GET /nimbly-site/api HTTP/1.1" 503 123'
);
audit_assert($access !== null, 'parses vhost access line');
audit_assert($access['status'] === 503, 'parses HTTP status');
audit_assert($access['path'] === '/nimbly-site/api', 'parses request path');
audit_assert($access['vhost'] === 'stage.example', 'parses logged Apache vhost');
audit_assert(
    host_audit_project_from_path($access['path'], ['nimbly-site']) === 'nimbly-site',
    'attributes request path to project'
);
audit_assert(
    host_audit_project_from_path('/wp-admin/install.php', ['nimbly-site']) === null,
    'does not attribute scanner paths to projects'
);
audit_assert(host_audit_404_is_probe('/wp-login.php'), 'filters WordPress login probes');
audit_assert(host_audit_404_is_probe('/vendor/phpunit/phpunit/src/Util/PHP/eval-stdin.php'), 'filters PHPUnit probes');
audit_assert(!host_audit_404_is_probe('/nimbly-site/about-us'), 'keeps genuine application paths');
audit_assert(
    host_audit_501_is_rejected_method_probe([
        'status' => 501,
        'method' => 'UPWN',
    ]),
    'filters rejected probes using unknown HTTP methods'
);
audit_assert(
    !host_audit_501_is_rejected_method_probe([
        'status' => 501,
        'method' => 'POST',
    ]),
    'keeps genuine 501 application responses'
);
audit_assert(
    host_audit_project_from_path(
        '/harmony-playground-app/api',
        ['harmony-playground-app' => 'HP App']
    ) === 'HP App',
    'maps an Apache alias to its inventory project'
);
audit_assert(
    host_audit_project_from_host(
        'koen.staging.example:443',
        ['koen.staging.example' => 'Koen']
    ) === 'Koen',
    'maps a logged vhost to its project'
);
$host_attribution = host_audit_request_attribution(
    [
        'vhost' => 'koen.staging.example',
        'path' => '/missing-page',
    ],
    [],
    ['koen.staging.example' => 'Koen'],
    ['koen.staging.example' => true]
);
audit_assert($host_attribution['project'] === 'Koen', 'prefers hostname project attribution');
$server_attribution = host_audit_request_attribution(
    [
        'vhost' => 'staging.example',
        'path' => '/robots.txt',
    ],
    [],
    [],
    ['staging.example' => true]
);
audit_assert($server_attribution['type'] === 'server', 'labels a known generic vhost as server traffic');
$implicit_server_attribution = host_audit_request_attribution(
    [
        'vhost' => null,
        'path' => '/robots.txt',
    ],
    [],
    [],
    ['staging.example' => true]
);
audit_assert(
    $implicit_server_attribution['type'] === 'server',
    'labels hostless traffic as server traffic when Apache has one known vhost'
);
$unknown_attribution = host_audit_request_attribution(
    [
        'vhost' => 'scanner.invalid',
        'path' => '/missing-page',
    ],
    [],
    [],
    ['staging.example' => true]
);
audit_assert($unknown_attribution['type'] === 'unknown-host', 'labels unknown vhost traffic explicitly');

$apache_project = host_audit_apache_project(
    'groene-chemie',
    ['groenechemie' => '/var/www/nimbly-groenechemie'],
    [],
    []
);
audit_assert(
    $apache_project['path'] === '/var/www/nimbly-groenechemie',
    'matches compact Apache aliases'
);
$apache_project_override = host_audit_apache_project(
    'hp-app',
    ['harmony-playground-app' => '/var/www/harmony-playground-app'],
    [],
    ['hp-app' => 'harmony-playground-app']
);
audit_assert(
    $apache_project_override['path'] === '/var/www/harmony-playground-app',
    'uses explicit Apache alias overrides'
);
$apache_vhost_project = host_audit_apache_project(
    'f-14association',
    [],
    ['f14tomcatassociation.org' => '/var/www/f-14association'],
    []
);
audit_assert(
    $apache_vhost_project['path'] === '/var/www/f-14association',
    'matches Apache virtual-host document roots'
);

$security_activity = host_audit_parse_security_activity(
    "NOTICE [sshd] Ban 192.0.2.1\nNOTICE [recidive] Ban 192.0.2.2\n",
    "Failed password for invalid user test\nInvalid user scan from 192.0.2.3\n"
);
audit_assert($security_activity['new_bans'] === 2, 'counts window bans');
audit_assert($security_activity['ssh_failures'] === 2, 'counts SSH failures');

$metrics = [];
host_audit_add_project_php_event(
    $metrics,
    '[php:error] PHP Warning in /var/www/nimbly-site/core/lib/test.php on line 10',
    ['nimbly-site']
);
audit_assert($metrics['nimbly-site']['php_errors'] === 1, 'attributes PHP event');
host_audit_add_project_php_event(
    $metrics,
    '[php:error] PHP Warning in /var/www/unknown/core/lib/test.php on line 10',
    ['nimbly-site']
);
audit_assert(!isset($metrics['unknown']), 'ignores PHP events outside registered projects');

$project_checks = [
    'nimbly-site' => ['available' => true],
    'other' => ['available' => true],
];
$merged_metrics = host_audit_merge_project_metrics(
    $project_checks,
    ['nimbly-site' => [
        'requests' => 12,
        'status_counts' => ['2xx' => 9, '3xx' => 1, '4xx' => 1, '5xx' => 1],
        'http_5xx' => 1,
        'php_errors' => 2,
    ]]
);
audit_assert($merged_metrics['nimbly-site']['requests'] === 12, 'merges project traffic');
audit_assert(
    $merged_metrics['nimbly-site']['status_counts']['2xx'] === 9,
    'merges project status distribution'
);
audit_assert($merged_metrics['other']['requests'] === 0, 'defaults missing project traffic');

$message = host_audit_normalize_message(
    'Undefined array key uuid in /var/www/site/data.php on line 127'
);
audit_assert(str_contains($message, 'line {n}'), 'normalizes line numbers');

$redacted = host_audit_redact(
    'token=secret-value password=hunter2 "api_key":"json-secret" Authorization: Bearer abc123'
);
audit_assert(!str_contains($redacted, 'secret-value'), 'redacts token');
audit_assert(!str_contains($redacted, 'hunter2'), 'redacts password');
audit_assert(!str_contains($redacted, 'json-secret'), 'redacts JSON secrets');
audit_assert(!str_contains($redacted, 'abc123'), 'redacts bearer tokens');

audit_assert(host_audit_exit_code('ok') === 0, 'healthy exit code');
audit_assert(host_audit_exit_code('warning') === 1, 'warning exit code');
audit_assert(host_audit_exit_code('critical') === 2, 'critical exit code');
audit_assert(host_audit_exit_code('unknown') === 3, 'unknown exit code');

$fixture = sys_get_temp_dir() . '/nimbly-host-audit-test-' . bin2hex(random_bytes(4));
mkdir($fixture . '/ext/data/.jobs', 0755, true);
mkdir($fixture . '/ext/.git/rebase-merge', 0755, true);
mkdir($fixture . '/apache-sites', 0755, true);
file_put_contents(
    $fixture . '/apache-sites/project.conf',
    "<VirtualHost *:443>\n"
    . "    ServerName koen.staging.example\n"
    . "    ServerAlias www.koen.staging.example\n"
    . "    DocumentRoot /var/www/Koen\n"
    . "    ErrorLog \${APACHE_LOG_DIR}/koen-error.log\n"
    . "    CustomLog \${APACHE_LOG_DIR}/koen-access.log combined\n"
    . "</VirtualHost>\n"
    . "<VirtualHost *:443>\n"
    . "    ServerName old-koen.example\n"
    . "    Redirect permanent / https://koen.staging.example/\n"
    . "</VirtualHost>\n"
);
file_put_contents(
    $fixture . '/ext/data/.jobs/failed-job',
    json_encode(['status' => 'failed', 'last_error' => 'Example failure'])
);
file_put_contents(
    $fixture . '/ext/data/.jobs/running-job',
    json_encode(['status' => 'running'])
);
touch($fixture . '/ext/data/.jobs/running-job', time() - 7200);
file_put_contents($fixture . '/ext/.git/HEAD', "ref: refs/heads/live\n");
mkdir($fixture . '/ext/.git/refs/heads', 0755, true);
file_put_contents($fixture . '/ext/.git/refs/heads/live', str_repeat('a', 40) . "\n");

$fixture_findings = [];
$fixture_context = [
    'now' => time(),
    'config' => ['job_running_stale_minutes' => 30],
];
$job_counts = host_audit_project_jobs(
    'fixture',
    $fixture,
    $fixture_context,
    $fixture_findings
);
audit_assert($job_counts['failed'] === 1, 'counts failed jobs');
audit_assert($job_counts['running'] === 1, 'counts running jobs');
audit_assert(
    count(array_filter(
        $fixture_findings,
        fn(array $finding): bool => str_starts_with($finding['id'], 'job:stale:')
    )) === 1,
    'reports stale running jobs'
);
$git_state = host_audit_git_state($fixture . '/ext');
audit_assert($git_state['branch'] === 'live', 'reads git branch');
audit_assert($git_state['operation'] === 'rebase', 'detects interrupted rebase');
$vhosts = host_audit_apache_vhosts($fixture . '/apache-sites');
audit_assert(
    ($vhosts['koen.staging.example'] ?? '') === '/var/www/Koen',
    'maps Apache ServerName to its document root'
);
audit_assert(
    ($vhosts['www.koen.staging.example'] ?? '') === '/var/www/Koen',
    'maps Apache ServerAlias to its document root'
);
audit_assert(
    ($vhosts['old-koen.example'] ?? '') === '@redirect:koen.staging.example',
    'maps redirect-only Apache hosts to their target host'
);
$apache_logs = host_audit_apache_log_roots(
    $fixture . '/apache-sites',
    '/var/log/apache2'
);
audit_assert(
    ($apache_logs['access']['/var/log/apache2/koen-access.log'] ?? '') === '/var/www/Koen',
    'maps project access logs to their document root'
);
audit_assert(
    ($apache_logs['error']['/var/log/apache2/koen-error.log'] ?? '') === '/var/www/Koen',
    'maps project error logs to their document root'
);
audit_assert(
    host_audit_log_project(
        '/var/log/apache2/koen-access.log.1',
        ['/var/log/apache2/koen-access.log' => 'Koen']
    ) === 'Koen',
    'attributes rotated project logs'
);
audit_remove_fixture($fixture);

echo "Host audit tests passed\n";
