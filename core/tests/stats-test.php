<?php

$stats_test_root = sys_get_temp_dir() . '/nimbly-stats-test-' . bin2hex(random_bytes(6));
mkdir($stats_test_root, 0700, true);
$GLOBALS['SYSTEM']['file_base'] = $stats_test_root . '/';

function load_library($name): void {}
function env($name, $default = '') { return $GLOBALS['stats_test_env'][$name] ?? $default; }

require __DIR__ . '/../lib/stats.php';

function stats_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function stats_test_entry(array $overrides = []): array
{
    return array_replace([
        't' => '2026-09-23T10:00:00+02:00', 'm' => 'GET', 'h' => 'example.test', 'p' => '/', 's' => 200,
        'ct' => 'text/html', 'ms' => 20, 'ip' => '203.0.113.1',
        'ua' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
        'ref' => '', 'al' => 'nl', 'u' => 0, 'src' => 'app',
    ], $overrides);
}

function stats_test_remove(string $path): void
{
    foreach (glob($path . '/{,.}*[!.]*', GLOB_BRACE) ?: [] as $item) {
        is_dir($item) ? stats_test_remove($item) : unlink($item);
    }
    rmdir($path);
}

// Enabling follows the environment unless explicitly overridden.
$GLOBALS['stats_test_env'] = ['APP_ENV' => 'prod'];
stats_assert(stats_enabled(), 'enabled on prod');
$GLOBALS['stats_test_env'] = ['APP_ENV' => 'dev'];
stats_assert(!stats_enabled(), 'disabled on dev by default');
$GLOBALS['stats_test_env'] = ['APP_ENV' => 'dev', 'STATS_ENABLED' => '1'];
stats_assert(stats_enabled(), 'override enables dev');
$GLOBALS['stats_test_env'] = ['APP_ENV' => 'prod'];

// Request entries keep what the request provides.
$entry = stats_request_entry([
    'REQUEST_METHOD' => 'GET', 'HTTP_HOST' => 'Example.test', 'REQUEST_URI' => '/news?page=2',
    'REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => 'Agent', 'HTTP_ACCEPT_LANGUAGE' => 'nl-NL,nl;q=0.9,en;q=0.8',
    'REQUEST_TIME_FLOAT' => 1000.0,
], ['Content-Type: application/json; charset=utf-8'], 404, 1000.25, 1);
stats_assert($entry['p'] === '/news?page=2' && $entry['s'] === 404 && $entry['ms'] === 250, 'path, status and duration recorded');
stats_assert($entry['ct'] === 'application/json' && $entry['al'] === 'nl-nl' && $entry['h'] === 'example.test', 'content type, language and host normalised');
stats_assert($entry['u'] === 1 && $entry['ip'] === '203.0.113.9', 'user flag and ip recorded');

// Classification.
stats_assert(stats_classify(stats_test_entry())['group'] === 'human', 'browser with language is human');
stats_assert(stats_classify(stats_test_entry(['ua' => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)', 'al' => '']))['name'] === 'Bingbot', 'named bot');
stats_assert(stats_classify(stats_test_entry(['ua' => 'curl/8.7.1', 'al' => '']))['name'] === 'curl', 'tool');
stats_assert(stats_classify(stats_test_entry(['ua' => 'curl/8.7.1', 'p' => '/v1/.env']))['group'] === 'scanner', 'probing tool is scanner');
stats_assert(stats_classify(stats_test_entry(['p' => '/.aws/.env']))['group'] === 'scanner', 'probe path with browser agent is scanner');
stats_assert(stats_classify(stats_test_entry(), ['probes' => 5])['group'] === 'scanner', 'every request of a probing ip is scanner');
stats_assert(stats_classify(stats_test_entry(['al' => '']))['group'] === 'suspect', 'browser agent without language is suspect');
stats_assert(stats_classify(stats_test_entry(['al' => '', 'src' => 'apache']))['group'] === 'human', 'apache lines not judged on language');
stats_assert(stats_classify(stats_test_entry(), ['minutes' => ['x' => 500]])['group'] === 'suspect', 'burst rate is suspect');
stats_assert(stats_classify(stats_test_entry(['u' => 1, 'p' => '/.env']))['group'] === 'editor', 'editors counted separately');
stats_assert(stats_classify(stats_test_entry(['ua' => 'Mozilla/5.0+(compatible; UptimeRobot/2.0)']))['group'] === 'monitor', 'monitor');
stats_assert(stats_device('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148 Safari/604.1') === 'mobile', 'iphone mobile');
stats_assert(stats_device('Mozilla/5.0 (Linux; Android 14; SM-X710) Chrome/128.0 Safari/537.36') === 'tablet', 'android without Mobile is tablet');
stats_assert(stats_browser('Mozilla/5.0 (Windows NT 10.0) Chrome/128.0 Safari/537.36 Edg/128.0') === 'edge', 'edge before chrome');
stats_assert(stats_route_type('/api/news', 'application/json', 200) === 'api', 'api route');
stats_assert(stats_route_type('/nb-admin/pages', 'text/html', 200) === 'admin', 'admin route');
stats_assert(stats_route_type('/files/report.pdf', 'application/pdf', 200) === 'file', 'file route');

// Recording, overflow and rollup.
$tmp = $stats_test_root . '/tmp';
$out = $stats_test_root . '/stats/prod';
$lines = [
    stats_test_entry(),
    stats_test_entry(['p' => '/news?page=2', 'ref' => 'https://www.google.com/search']),
    stats_test_entry(['ip' => '203.0.113.2', 'ua' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148 Safari/604.1', 'p' => '/news']),
    stats_test_entry(['ip' => '198.51.100.7', 'ua' => 'curl/8.7.1', 'al' => '', 'p' => '/v1/.env', 's' => 404]),
    stats_test_entry(['ip' => '66.249.66.1', 'ua' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'al' => '', 'p' => '/news']),
    stats_test_entry(['p' => '/api/news', 'ct' => 'application/json']),
    stats_test_entry(['u' => 1, 'p' => '/nb-admin']),
];
stats_assert(stats_record($lines[0], $tmp, 1000000) === true, 'first line starts a new day');
foreach (array_slice($lines, 1) as $line) {
    stats_assert(stats_record($line, $tmp, 1000000) === false, 'later lines do not');
}
stats_record(stats_test_entry(['t' => '2026-09-24T09:00:00+02:00']), $tmp, 1000000);
stats_record(stats_test_entry(['t' => '2026-09-22T10:00:00+02:00']), $tmp, 10);
stats_record(stats_test_entry(['t' => '2026-09-22T10:00:01+02:00']), $tmp, 10);
stats_record(stats_test_entry(['t' => '2026-09-22T10:00:02+02:00']), $tmp, 10);
stats_assert(filesize($tmp . '/overflow-2026-09-22.log') === 2, 'requests beyond the cap only counted');
file_put_contents($tmp . '/apache-2026-09-23.log', json_encode(stats_test_entry(['p' => '/app.css', 'ct' => 'text/css', 'al' => '', 'src' => 'apache'])) . "\n");

$key = random_bytes(32);
$archived = stats_rollup('2026-09-24', false, $tmp, $out, $key);
stats_assert($archived === ['2026-09-22', '2026-09-23'], 'finished days archived, today kept running');
stats_assert(is_file($tmp . '/running-2026-09-24.log') && !is_file($tmp . '/running-2026-09-23.log'), 'running logs moved');
$raw = $out . '/raw/2026/2026-09-23.log.gz.enc';
stats_assert(count(iterator_to_array(stats_read_lines($raw, $key), false)) === count($lines), 'raw archive holds every line');
stats_assert(str_starts_with(file_get_contents($raw), 'NBS1') && @gzdecode(file_get_contents($raw)) === false, 'raw archive is encrypted');
try {
    iterator_to_array(stats_read_lines($raw, random_bytes(32)));
    stats_assert(false, 'wrong key rejected');
} catch (RuntimeException $e) {
}
stats_assert(is_file($out . '/raw/2026/2026-09-23.apache.log.gz.enc'), 'apache enrichment archived separately');
foreach (glob($out . '/{months,years}/*.json', GLOB_BRACE) as $json) {
    stats_assert(!str_contains(file_get_contents($json), '203.0.113'), 'no ip addresses outside the encrypted archive');
}

$month = json_decode(file_get_contents($out . '/months/2026-09.json'), true);
$day = $month['days']['2026-09-23'];
stats_assert($day['requests'] === 8 && $day['enriched'] === true, 'app and apache requests counted');
stats_assert($day['pageviews'] === 3 && $day['visitors'] === 2, 'human pageviews and visitors');
stats_assert($day['paths']['/news']['views'] === 2 && $day['paths']['/news']['hits'] === 3, 'per-path views and hits without query');
stats_assert(($day['bots']['Googlebot'] ?? 0) === 1 && ($day['class']['scanner'] ?? 0) === 1, 'bots and scanners');
stats_assert(($day['class']['editor'] ?? 0) === 1 && ($day['route']['api'] ?? 0) === 1, 'editors and api');
stats_assert(!isset($day['paths']['/v1/.env']) && ($day['referrers']['www.google.com'] ?? 0) === 1, 'probe paths not listed, referrer host kept');
stats_assert($day['paths']['/news']['status'] === ['200' => 3] && !isset($day['ips']), 'status per path, no ip ranking');
stats_assert($day['device'] === ['desktop' => 2, 'mobile' => 1], 'devices of human pageviews');
stats_assert($month['days']['2026-09-22']['overflow'] === 2, 'overflow survives in the raw archive');

$year = json_decode(file_get_contents($out . '/years/2026.json'), true);
$september = $year['months']['2026-09'];
stats_assert($september['days'] === 2 && $september['requests'] === 9 && $september['visits'] === 3, 'month counts summed from days');
stats_assert($september['paths']['/news'] === ['hits' => 3, 'ms' => 60, 'status' => ['200' => 3], 'views' => 2], 'complete path counts per month');
stats_assert($september['bots']['Googlebot'] === 1 && $september['enriched_days'] === 1, 'bots and enriched days per month');
stats_assert($year['total']['requests'] === 9 && !is_file($out . '/summary.json'), 'year total, no summary file');

// Idempotence: a rebuild from raw produces the same files, and late lines are appended.
$before = file_get_contents($out . '/months/2026-09.json');
stats_assert(stats_rollup('2026-09-24', true, $tmp, $out, $key) === [], 'nothing left to archive');
stats_assert(file_get_contents($out . '/months/2026-09.json') === $before, 'rebuild is idempotent');
stats_record(stats_test_entry(['t' => '2026-09-23T23:59:59+02:00', 'p' => '/late']), $tmp, 1000000);
stats_rollup('2026-09-24', false, $tmp, $out, $key);
$month = json_decode(file_get_contents($out . '/months/2026-09.json'), true);
stats_assert($month['days']['2026-09-23']['requests'] === 9, 'late line appended to existing archive');
$year = json_decode(file_get_contents($out . '/years/2026.json'), true);
stats_assert($year['months']['2026-09']['requests'] === 10, 'year file follows late lines');

// A second rollup while one holds the lock does nothing.
$lock = fopen($tmp . '/rollup.lock', 'c');
flock($lock, LOCK_EX);
stats_assert(stats_rollup('2026-09-25', false, $tmp, $out, $key) === null, 'concurrent rollup skipped');
flock($lock, LOCK_UN);
stats_assert(stats_rollup('2026-09-24', false, $tmp, $out) === [], 'no key needed when nothing is due');
$GLOBALS['stats_test_env']['STATS_KEY'] = 'short';
try {
    stats_rollup('2026-09-25', false, $tmp, $out);
    stats_assert(false, 'missing key stops archiving');
} catch (RuntimeException $e) {
    stats_assert(is_file($tmp . '/running-2026-09-24.log'), 'running log kept while key is missing');
}
flock($lock, LOCK_EX);
flock($lock, LOCK_UN);
fclose($lock);

stats_test_remove($stats_test_root);
echo "stats tests passed\n";
