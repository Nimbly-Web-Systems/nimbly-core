<?php

load_library('env');

/*
 * Request statistics without cookies or JavaScript.
 *
 * Every request that reaches index.php appends one JSON line to a running log
 * in ext/data/.tmp/stats/. Once a day the running log is archived unchanged,
 * gzipped and encrypted with STATS_KEY, as
 * ext/data/.stats/<env>/raw/YYYY/YYYY-MM-DD.log.gz.enc and counted into
 * months/YYYY-MM.json (per day) and years/YYYY.json (per month). Those files
 * hold complete counts only;
 * rankings are for the interface. IP addresses exist only in the encrypted raw
 * archive. Counts can always be rebuilt from raw/, so classification rules may
 * improve without losing history.
 */

const STATS_DEFAULT_MAX_DAY_BYTES = 200000000;
const STATS_BURST_PER_MINUTE = 120;
const STATS_SCANNER_PROBES = 3;
const STATS_ARCHIVE_MAGIC = 'NBS1';

function stats_register(): void
{
    if (PHP_SAPI === 'cli' || !stats_enabled()) {
        return;
    }
    register_shutdown_function('stats_shutdown');
}

function stats_enabled(): bool
{
    $override = strtolower((string)env('STATS_ENABLED', ''));
    if ($override !== '') {
        return in_array($override, ['1', 'true', 'yes', 'on'], true);
    }
    return in_array(stats_environment(), ['prod', 'stage'], true);
}

function stats_environment(): string
{
    $environment = preg_replace('/[^a-z0-9_-]/', '', strtolower((string)env('APP_ENV', 'dev')));
    return $environment !== '' ? $environment : 'dev';
}

function stats_tmp_dir(): string
{
    return $GLOBALS['SYSTEM']['file_base'] . 'ext/data/.tmp/stats';
}

/** 32-byte key from the hex STATS_KEY; archiving waits while it is missing. */
function stats_key(): string
{
    $hex = trim((string)env('STATS_KEY', ''));
    if (strlen($hex) !== 64 || !ctype_xdigit($hex)) {
        throw new RuntimeException('STATS_KEY must be 64 hexadecimal characters');
    }
    return hex2bin($hex);
}

function stats_dir(): string
{
    return $GLOBALS['SYSTEM']['file_base'] . 'ext/data/.stats/' . stats_environment();
}

/** Statistics must never break or noticeably delay a response. */
function stats_shutdown(): void
{
    try {
        $status = http_response_code();
        $entry = stats_request_entry($_SERVER, headers_list(), is_int($status) ? $status : 200,
            microtime(true), stats_request_user());
        if (stats_record($entry) && function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    } catch (Throwable $e) {
    }
}

function stats_request_user(): int
{
    $roles = $_SESSION['roles'] ?? null;
    return is_array($roles) && $roles !== [] && empty($roles['anonymous']) ? 1 : 0;
}

function stats_request_entry(array $server, array $headers, int $status, float $now, int $user): array
{
    $content_type = (string)ini_get('default_mimetype');
    foreach ($headers as $header) {
        if (stripos($header, 'content-type:') === 0) {
            $content_type = trim(substr($header, 13));
        }
    }
    $start = (float)($GLOBALS['SYSTEM']['request_time'] ?? $server['REQUEST_TIME_FLOAT'] ?? $now);
    preg_match('/^\s*([a-zA-Z]{1,8}(?:-[a-zA-Z0-9]{1,8})?)/', (string)($server['HTTP_ACCEPT_LANGUAGE'] ?? ''), $language);
    return [
        't' => date('c', (int)$now),
        'm' => substr((string)($server['REQUEST_METHOD'] ?? 'GET'), 0, 10),
        'h' => substr(strtolower((string)($server['HTTP_HOST'] ?? '')), 0, 255),
        'p' => substr((string)($server['REQUEST_URI'] ?? '/'), 0, 2000),
        's' => $status,
        'ct' => strtolower(trim(explode(';', $content_type)[0])),
        'ms' => max(0, (int)round(($now - $start) * 1000)),
        'ip' => substr((string)($server['REMOTE_ADDR'] ?? ''), 0, 45),
        'ua' => substr((string)($server['HTTP_USER_AGENT'] ?? ''), 0, 500),
        'ref' => substr((string)($server['HTTP_REFERER'] ?? ''), 0, 500),
        'al' => strtolower($language[1] ?? ''),
        'u' => $user,
        'src' => 'app',
    ];
}

/**
 * Appends one line. Returns true when this request started a new day, in which
 * case earlier days are rolled up after the response has been sent.
 */
function stats_record(array $entry, ?string $dir = null, ?int $max_bytes = null): bool
{
    $dir ??= stats_tmp_dir();
    $date = substr($entry['t'], 0, 10);
    $path = $dir . '/running-' . $date . '.log';
    $new_day = !is_file($path);
    if ($new_day && !is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $max_bytes ??= (int)env('STATS_MAX_DAY_BYTES', STATS_DEFAULT_MAX_DAY_BYTES);
    if (!$new_day && filesize($path) >= $max_bytes) {
        // One byte per skipped request keeps the overflow count an O(1) append.
        @file_put_contents($dir . '/overflow-' . $date . '.log', '.', FILE_APPEND | LOCK_EX);
        return false;
    }
    $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    @file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);
    if ($new_day) {
        register_shutdown_function('stats_rollup_quietly');
    }
    return $new_day;
}

function stats_rollup_quietly(): void
{
    try {
        stats_rollup();
    } catch (Throwable $e) {
    }
}

/**
 * Archives every finished day and refreshes the summaries of affected months.
 * Returns the processed dates, or null when another rollup holds the lock.
 */
function stats_rollup(?string $today = null, bool $rebuild = false, ?string $tmp_dir = null, ?string $stats_dir = null, ?string $key = null): ?array
{
    $tmp_dir ??= stats_tmp_dir();
    $stats_dir ??= stats_dir();
    $today ??= date('Y-m-d');
    @mkdir($tmp_dir, 0775, true);
    $lock = fopen($tmp_dir . '/rollup.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        return null;
    }
    try {
        $dates = [];
        foreach (glob($tmp_dir . '/*-*.log*') ?: [] as $file) {
            if (preg_match('#/(running|overflow|apache)-(\d{4}-\d{2}-\d{2})\.log(\.\d+\.pending)?$#', $file, $match)
                && $match[2] < $today) {
                $dates[$match[2]] = true;
            }
        }
        $dates = array_keys($dates);
        sort($dates);
        if ($dates === [] && !$rebuild) {
            return [];
        }
        $key ??= stats_key();
        foreach ($dates as $date) {
            stats_archive_day($date, $tmp_dir, $stats_dir, $key);
        }
        $months = $rebuild ? stats_raw_months($stats_dir) : array_unique(array_map(fn($date) => substr($date, 0, 7), $dates));
        foreach ($months as $month) {
            stats_write_month($month, $stats_dir, $key, $rebuild ? null : array_values(array_filter(
                $dates, fn($date) => str_starts_with($date, $month))));
        }
        foreach (array_unique(array_map(fn($month) => substr($month, 0, 4), $months)) as $year) {
            stats_write_year($year, $stats_dir);
        }
        return $dates;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function stats_raw_path(string $stats_dir, string $date, string $source): string
{
    $suffix = $source === 'apache' ? '.apache.log.gz.enc' : '.log.gz.enc';
    return $stats_dir . '/raw/' . substr($date, 0, 4) . '/' . $date . $suffix;
}

function stats_archive_day(string $date, string $tmp_dir, string $stats_dir, string $key): void
{
    $overflow = $tmp_dir . '/overflow-' . $date . '.log';
    $running = $tmp_dir . '/running-' . $date . '.log';
    if (is_file($overflow)) {
        // Keep the overflow in the raw archive so rebuilt summaries retain it.
        $count = (int)filesize($overflow);
        file_put_contents($running, json_encode(['t' => $date . 'T23:59:59', 'overflow' => $count]) . "\n", FILE_APPEND | LOCK_EX);
        unlink($overflow);
    }
    stats_append_archive($running, stats_raw_path($stats_dir, $date, 'app'), $key);
    @unlink($tmp_dir . '/cache-' . $date . '.json');
    stats_append_archive($tmp_dir . '/apache-' . $date . '.log', stats_raw_path($stats_dir, $date, 'apache'), $key);
}

/**
 * Appends a finished log to its encrypted archive and removes the log. Late
 * lines become an extra gzip member, which readers see as one stream.
 */
function stats_append_archive(string $source, string $target, string $key): void
{
    if (is_file($source)) {
        rename($source, $source . '.' . getmypid() . '.pending');
    }
    // A pending file left by an interrupted rollup is picked up again here.
    $pending = glob($source . '.*.pending') ?: [];
    if ($pending === []) {
        return;
    }
    $member = $pending[0] . '.gz';
    $out = gzopen($member, 'wb9');
    foreach ($pending as $input) {
        $in = fopen($input, 'rb');
        while (!feof($in)) {
            gzwrite($out, (string)fread($in, 1048576));
        }
        fclose($in);
    }
    gzclose($out);
    $gzip = (is_file($target) ? stats_decrypt((string)file_get_contents($target), $key) : '')
        . file_get_contents($member);
    @mkdir(dirname($target), 0775, true);
    file_put_contents($target . '.tmp', stats_encrypt($gzip, $key));
    rename($target . '.tmp', $target);
    unlink($member);
    array_map('unlink', $pending);
}

function stats_encrypt(string $plain, string $key): string
{
    $iv = random_bytes(12);
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        throw new RuntimeException('Stats archive could not be encrypted');
    }
    return STATS_ARCHIVE_MAGIC . $iv . $tag . $cipher;
}

function stats_decrypt(string $data, string $key): string
{
    $plain = str_starts_with($data, STATS_ARCHIVE_MAGIC) ? openssl_decrypt(
        substr($data, 32), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($data, 4, 12), substr($data, 16, 16)
    ) : false;
    if ($plain === false) {
        throw new RuntimeException('Stats archive could not be decrypted with this STATS_KEY');
    }
    return $plain;
}

/** @return iterable<array> */
function stats_read_lines(string $file, string $key): iterable
{
    if (!is_file($file)) {
        return;
    }
    $gzip = tempnam(sys_get_temp_dir(), 'nimbly-stats-');
    try {
        file_put_contents($gzip, stats_decrypt((string)file_get_contents($file), $key));
        $in = gzopen($gzip, 'rb');
        while (($line = gzgets($in)) !== false) {
            $entry = json_decode($line, true);
            if (is_array($entry)) {
                yield $entry;
            }
        }
        gzclose($in);
    } finally {
        @unlink($gzip);
    }
}

/** @return iterable<array> */
function stats_read_running_lines(string $file): iterable
{
    $in = @fopen($file, 'rb');
    if ($in === false) {
        return;
    }
    while (($line = fgets($in)) !== false) {
        $entry = json_decode($line, true);
        if (is_array($entry)) {
            yield $entry;
        }
    }
    fclose($in);
}

/**
 * Day counts for the last $count days up to today, oldest first; null for a
 * day without data. Days not yet archived are counted from their running log.
 */
function stats_recent_days(int $count, ?string $today = null, ?string $tmp_dir = null, ?string $stats_dir = null): array
{
    $today ??= date('Y-m-d');
    $tmp_dir ??= stats_tmp_dir();
    $stats_dir ??= stats_dir();
    $months = [];
    $days = [];
    for ($offset = $count - 1; $offset >= 0; $offset--) {
        $date = date('Y-m-d', strtotime($today . ' 12:00 -' . $offset . ' days'));
        $running = $tmp_dir . '/running-' . $date . '.log';
        if (is_file($running)) {
            $days[$date] = stats_running_day($running, $tmp_dir . '/cache-' . $date . '.json');
            continue;
        }
        $month = substr($date, 0, 7);
        if (!isset($months[$month])) {
            $file = $stats_dir . '/months/' . $month . '.json';
            $months[$month] = is_file($file) ? (array)(json_decode((string)file_get_contents($file), true)['days'] ?? []) : [];
        }
        $days[$date] = $months[$month][$date] ?? null;
    }
    return $days;
}

/** Counts of a running log, cached for a few minutes because it keeps growing. */
function stats_running_day(string $running, string $cache): array
{
    if (is_file($cache) && filemtime($cache) > time() - 300) {
        $day = json_decode((string)file_get_contents($cache), true);
        if (is_array($day)) {
            return $day;
        }
    }
    $day = stats_summarize_day([fn() => stats_read_running_lines($running)], false);
    @file_put_contents($cache, json_encode($day));
    return $day;
}

function stats_raw_months(string $stats_dir): array
{
    $months = [];
    foreach (glob($stats_dir . '/raw/*/*.log.gz.enc') ?: [] as $file) {
        if (preg_match('/(\d{4}-\d{2})-\d{2}(\.apache)?\.log\.gz\.enc$/', $file, $match)) {
            $months[$match[1]] = true;
        }
    }
    $months = array_keys($months);
    sort($months);
    return $months;
}

/** Refreshes the given days (all days of the month when null) in the month file. */
function stats_write_month(string $month, string $stats_dir, string $key, ?array $dates): void
{
    $path = $stats_dir . '/months/' . $month . '.json';
    $existing = is_file($path) ? json_decode((string)file_get_contents($path), true) : null;
    $days = $dates === null ? [] : (array)($existing['days'] ?? []);
    if ($dates === null) {
        $dates = [];
        foreach (glob($stats_dir . '/raw/' . substr($month, 0, 4) . '/' . $month . '-*.log.gz.enc') ?: [] as $file) {
            $dates[substr(basename($file), 0, 10)] = true;
        }
        $dates = array_keys($dates);
    }
    foreach ($dates as $date) {
        $app = stats_raw_path($stats_dir, $date, 'app');
        $apache = stats_raw_path($stats_dir, $date, 'apache');
        $days[$date] = stats_summarize_day(
            [fn() => stats_read_lines($app, $key), fn() => stats_read_lines($apache, $key)],
            is_file($apache)
        );
    }
    ksort($days);
    stats_write_json($path, ['month' => $month, 'environment' => basename($stats_dir), 'days' => $days]);
}

function stats_write_json(string $path, array $data): void
{
    @mkdir(dirname($path), 0775, true);
    $tmp = $path . '.tmp';
    file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    rename($tmp, $path);
}

/**
 * Two streaming passes: first per-IP behaviour, then classified totals.
 * @param callable[] $readers each returns an iterable of entries
 */
function stats_summarize_day(array $readers, bool $enriched): array
{
    $behaviour = [];
    foreach ($readers as $reader) {
        foreach ($reader() as $entry) {
            if (isset($entry['ip'], $entry['t'])) {
                stats_observe_behaviour($behaviour, $entry);
            }
        }
    }
    $day = [
        'requests' => 0, 'pageviews' => 0, 'visitors' => 0, 'enriched' => $enriched, 'overflow' => 0,
        'status' => [], 'route' => [], 'class' => [], 'device' => [], 'browser' => [], 'language' => [],
        'bots' => [], 'referrers' => [], 'paths' => [],
    ];
    $visitors = [];
    foreach ($readers as $reader) {
        foreach ($reader() as $entry) {
            if (isset($entry['overflow'])) {
                $day['overflow'] += (int)$entry['overflow'];
                continue;
            }
            stats_count_entry($day, $visitors, $entry, stats_classify($entry, $behaviour[$entry['ip'] ?? ''] ?? []));
        }
    }
    $day['visitors'] = count($visitors);
    foreach (['status', 'route', 'class', 'device', 'browser', 'language', 'bots', 'referrers', 'paths'] as $dimension) {
        ksort($day[$dimension]);
    }
    return $day;
}

function stats_observe_behaviour(array &$behaviour, array $entry): void
{
    $ip = (string)$entry['ip'];
    $minute = substr((string)$entry['t'], 0, 16);
    $behaviour[$ip]['minutes'][$minute] = ($behaviour[$ip]['minutes'][$minute] ?? 0) + 1;
    if (stats_is_probe_path((string)($entry['p'] ?? ''))) {
        $behaviour[$ip]['probes'] = ($behaviour[$ip]['probes'] ?? 0) + 1;
    }
}

function stats_count_entry(array &$day, array &$visitors, array $entry, array $class): void
{
    $status = (int)($entry['s'] ?? 0);
    $path = strtok((string)($entry['p'] ?? '/'), '?') ?: '/';
    $route = stats_route_type($path, (string)($entry['ct'] ?? ''), $status);
    $name = $class['name'];
    $group = $class['group'];
    $day['requests']++;
    stats_increment($day['status'], (string)$status);
    stats_increment($day['route'], $route);
    stats_increment($day['class'], $group);
    if ($group === 'bot') {
        stats_increment($day['bots'], $name);
    }
    // Probe paths are noise per path; they stay complete in the raw archive.
    if (in_array($group, ['scanner', 'suspect'], true)) {
        return;
    }
    $human_page = $group === 'human' && $route === 'page' && $status < 300 && ($entry['m'] ?? 'GET') === 'GET';
    $day['paths'][$path]['hits'] = ($day['paths'][$path]['hits'] ?? 0) + 1;
    $day['paths'][$path]['ms'] = ($day['paths'][$path]['ms'] ?? 0) + (int)($entry['ms'] ?? 0);
    $day['paths'][$path]['status'][(string)$status] = ($day['paths'][$path]['status'][(string)$status] ?? 0) + 1;
    if (!$human_page) {
        return;
    }
    $ua = (string)($entry['ua'] ?? '');
    $day['pageviews']++;
    $day['paths'][$path]['views'] = ($day['paths'][$path]['views'] ?? 0) + 1;
    $visitors[($entry['ip'] ?? '') . '|' . $ua] = true;
    stats_increment($day['device'], stats_device($ua));
    stats_increment($day['browser'], stats_browser($ua));
    stats_increment($day['language'], (string)($entry['al'] ?? '') ?: 'unknown');
    $referrer = strtolower((string)parse_url((string)($entry['ref'] ?? ''), PHP_URL_HOST));
    if ($referrer !== '' && $referrer !== ($entry['h'] ?? '')) {
        stats_increment($day['referrers'], $referrer);
    }
}

function stats_increment(array &$counts, string $key): void
{
    $counts[$key] = ($counts[$key] ?? 0) + 1;
}

/** @return array{group: string, name: string} */
function stats_classify(array $entry, array $behaviour = []): array
{
    if (!empty($entry['u'])) {
        return ['group' => 'editor', 'name' => 'editor'];
    }
    $ua = (string)($entry['ua'] ?? '');
    foreach (['monitor' => stats_monitor_agents(), 'bot' => stats_bot_agents()] as $group => $agents) {
        if (($name = stats_match_agent($ua, $agents)) !== null) {
            return ['group' => $group, 'name' => $name];
        }
    }
    if (($behaviour['probes'] ?? 0) >= STATS_SCANNER_PROBES || stats_is_probe_path((string)($entry['p'] ?? ''))) {
        return ['group' => 'scanner', 'name' => 'scanner'];
    }
    if (($name = stats_match_agent($ua, stats_tool_agents())) !== null) {
        return ['group' => 'tool', 'name' => $name];
    }
    if (preg_match('/bot\b|crawl|spider|slurp|fetcher|preview/i', $ua)) {
        return ['group' => 'bot', 'name' => 'other'];
    }
    if ($ua === '') {
        return ['group' => 'tool', 'name' => 'empty'];
    }
    // Apache lines carry no Accept-Language, so only the app source can use it.
    $no_language = ($entry['src'] ?? 'app') === 'app' && ($entry['al'] ?? '') === '';
    if ($no_language || max($behaviour['minutes'] ?? [0]) > STATS_BURST_PER_MINUTE) {
        return ['group' => 'suspect', 'name' => 'suspect'];
    }
    return ['group' => 'human', 'name' => 'human'];
}

function stats_match_agent(string $ua, array $agents): ?string
{
    foreach ($agents as $pattern => $name) {
        if (stripos($ua, $pattern) !== false) {
            return $name;
        }
    }
    return null;
}

function stats_monitor_agents(): array
{
    return [
        'UptimeRobot' => 'UptimeRobot', 'Pingdom' => 'Pingdom', 'StatusCake' => 'StatusCake',
        'Better Uptime' => 'Better Stack', 'BetterStack' => 'Better Stack', 'Site24x7' => 'Site24x7',
        'Uptime-Kuma' => 'Uptime Kuma', 'nimbly-host-audit' => 'Nimbly audit',
    ];
}

function stats_bot_agents(): array
{
    return [
        'Googlebot' => 'Googlebot', 'Google-InspectionTool' => 'Googlebot', 'GoogleOther' => 'GoogleOther',
        'AdsBot-Google' => 'Google Ads', 'Storebot-Google' => 'Googlebot', 'bingbot' => 'Bingbot',
        'Applebot' => 'Applebot', 'DuckDuckBot' => 'DuckDuckBot', 'YandexBot' => 'YandexBot',
        'Baiduspider' => 'Baiduspider', 'GPTBot' => 'GPTBot', 'ChatGPT-User' => 'ChatGPT',
        'OAI-SearchBot' => 'OpenAI search', 'ClaudeBot' => 'ClaudeBot', 'Claude-User' => 'Claude',
        'Claude-SearchBot' => 'Claude search', 'PerplexityBot' => 'PerplexityBot', 'Perplexity-User' => 'Perplexity',
        'Bytespider' => 'Bytespider', 'CCBot' => 'CCBot', 'Amazonbot' => 'Amazonbot',
        'meta-externalagent' => 'Meta AI', 'facebookexternalhit' => 'Facebook', 'AhrefsBot' => 'AhrefsBot',
        'SemrushBot' => 'SemrushBot', 'MJ12bot' => 'MJ12bot', 'DotBot' => 'DotBot', 'PetalBot' => 'PetalBot',
        'Twitterbot' => 'Twitter/X', 'LinkedInBot' => 'LinkedIn', 'Slackbot' => 'Slack',
        'WhatsApp' => 'WhatsApp', 'Discordbot' => 'Discord', 'TelegramBot' => 'Telegram',
    ];
}

function stats_tool_agents(): array
{
    return [
        'curl/' => 'curl', 'Wget' => 'wget', 'python-requests' => 'python', 'python-urllib' => 'python',
        'aiohttp' => 'python', 'httpx' => 'python', 'Scrapy' => 'scrapy', 'Go-http-client' => 'go',
        'okhttp' => 'java', 'Java/' => 'java', 'Apache-HttpClient' => 'java', 'libwww-perl' => 'perl',
        'PostmanRuntime' => 'postman', 'axios' => 'node', 'node-fetch' => 'node', 'undici' => 'node',
        'HeadlessChrome' => 'headless', 'PhantomJS' => 'headless', 'zgrab' => 'scanner', 'masscan' => 'scanner',
    ];
}

function stats_is_probe_path(string $path): bool
{
    return (bool)preg_match(
        '#(^|/)\.(env|git|aws|ssh|svn|hg|DS_Store|htpasswd|vscode)|wp-(login|admin|content|includes|config)'
        . '|xmlrpc|phpmyadmin|pma/|\.php\d?($|[?/])|cgi-bin|/vendor/|actuator|/\.well-known/[^a]|\.(bak|sql|old)($|\?)#i',
        $path
    );
}

function stats_route_type(string $path, string $content_type, int $status): string
{
    if (preg_match('#^/(nb-admin)(/|$)#', $path)) {
        return 'admin';
    }
    if (preg_match('#^/api(/|$)#', $path)) {
        return 'api';
    }
    if ($content_type === 'text/html') {
        return 'page';
    }
    return $status < 400 && $content_type !== '' ? 'file' : 'other';
}

function stats_device(string $ua): string
{
    if (preg_match('/iPad|Tablet|Kindle|Silk|Android(?!.*Mobile)/i', $ua)) {
        return 'tablet';
    }
    return preg_match('/Mobi|iPhone|iPod|Android/i', $ua) ? 'mobile' : 'desktop';
}

function stats_browser(string $ua): string
{
    foreach (['Edg' => 'edge', 'OPR/' => 'opera', 'SamsungBrowser' => 'samsung', 'Firefox' => 'firefox',
        'FxiOS' => 'firefox', 'CriOS' => 'chrome', 'Chrome' => 'chrome', 'Safari' => 'safari'] as $needle => $name) {
        if (str_contains($ua, $needle)) {
            return $name;
        }
    }
    return 'other';
}

/** Complete counts per month of a year, summed from the month files. */
function stats_write_year(string $year, string $stats_dir): void
{
    $months = [];
    $total = [];
    foreach (glob($stats_dir . '/months/' . $year . '-*.json') ?: [] as $file) {
        $month = json_decode((string)file_get_contents($file), true);
        $counts = [];
        foreach ((array)($month['days'] ?? []) as $day) {
            stats_add_counts($counts, stats_day_counts((array)$day));
        }
        $months[basename($file, '.json')] = $counts;
        stats_add_counts($total, $counts);
    }
    ksort($months);
    stats_write_json($stats_dir . '/years/' . $year . '.json',
        ['year' => $year, 'environment' => basename($stats_dir), 'months' => $months, 'total' => $total]);
}

/**
 * Day counts as they sum above day level: daily unique visitors add up to
 * visits, and enriched days are counted.
 */
function stats_day_counts(array $day): array
{
    $day['days'] = 1;
    $day['visits'] = (int)($day['visitors'] ?? 0);
    $day['enriched_days'] = empty($day['enriched']) ? 0 : 1;
    unset($day['visitors'], $day['enriched']);
    return $day;
}

function stats_add_counts(array &$total, array $counts): void
{
    foreach ($counts as $key => $value) {
        if (is_array($value)) {
            $total[$key] ??= [];
            stats_add_counts($total[$key], $value);
        } else {
            $total[$key] = ($total[$key] ?? 0) + (int)$value;
        }
    }
    ksort($total);
}
