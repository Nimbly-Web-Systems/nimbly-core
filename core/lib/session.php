<?php

$GLOBALS['SYSTEM']['session_regentime'] = 24 * 60; //24 minutes
$GLOBALS['SYSTEM']['session_path'] = $GLOBALS['SYSTEM']['file_base'] . 'ext/data/.tmp/sessions';

/**
 * Implements session sc
 * Start and validate a session
 * @return void
 */
function session_sc() {

    //1. initialize session
    static $session_started = false;
    if ($session_started) {
        return;
    } else {
        $session_started = true;
    }

    ini_set('session.gc_probability', '0');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(session_cookie_options());
    session_save_path($GLOBALS['SYSTEM']['session_path']);
    session_name("nb_session_id");
    @session_start();

    //2. if it's a new session, just create and initialize it and done.
    if (empty($_SESSION)) { //a new session
        session_initialize();
        return;
    }

    //3. remove and restart session if it does not validate
    if (empty($_SESSION['modified']) 
        || empty($_SESSION['created']) 
        || empty($_SESSION['pepper'])
        || $_SESSION['pepper'] != ($_SERVER['PEPPER'] ?? '(none)')
        || session_expired($_SESSION, time())) {
        session_unset();
        session_destroy();
        session_start();
        session_initialize();
        return;
    }

    //4. regen session id
    if ($GLOBALS['SYSTEM']['request_time'] >= ($_SESSION['rotated'] ?? $_SESSION['created']) + $GLOBALS['SYSTEM']['session_regentime']) {
        session_regenerate_id(true);
        $_SESSION['rotated'] = time();
    }

    //5. update session
    $_SESSION['session_policy_version'] = 1;
    $_SESSION['modified'] = time();
    session_refresh_cookie();
}

function session_exists() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return true;
    }
    return isset($_COOKIE['nb_session_id'])
        && is_string($_COOKIE['nb_session_id'])
        && preg_match('/^[a-zA-Z0-9,-]+$/D', $_COOKIE['nb_session_id'])
        && file_exists($GLOBALS['SYSTEM']['session_path'] . '/sess_' . $_COOKIE['nb_session_id']);
}

function session_resume() {
    if (session_exists()) {
        session_sc();
    }
    return !empty($_SESSION);
}

function session_initialize() {
    session_regenerate_id(true);
    $_SESSION = [
        'created' => time(),
        'modified' => time(),
        'rotated' => time(),
        'session_policy_version' => 1,
        'key' => bin2hex(random_bytes(16)),
        'roles' => ['anonymous' => true],
        'features' => [],
        'pepper' => $_SERVER['PEPPER'] ?? '(none)',
    ];
    session_refresh_cookie();
}

function session_cookie_options(): array
{
    return [
        'lifetime' => 0,
        'path' => $GLOBALS['SYSTEM']['uri_base'] ?? '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function session_refresh_cookie(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    $options = session_cookie_options();
    unset($options['lifetime']);
    $options['expires'] = session_authenticated($_SESSION) ? time() + session_idle_lifetime($_SESSION) : 0;
    setcookie(session_name(), session_id(), $options);
}

function session_login_completed(): void
{
    session_regenerate_id(true);
    $_SESSION['assigned_roles'] = $_SESSION['roles'];
    $_SESSION['rotated'] = time();
    $_SESSION['modified'] = time();
    $_SESSION['session_policy_version'] = 1;
    session_refresh_cookie();
}

function session_cleanup_files() {
    return session_prune();
}

function session_count_files() {
    return iterator_count(session_files());
}

function session_anon() {
    return session_resume() === false || empty($_SESSION['roles']['anonymous']) === false;
}

function session_user() {
    return session_resume() && empty($_SESSION['roles']['anonymous']);
}

/** Shared by HTTP requests and maintenance; durations are seconds. */
function session_policy(): array
{
    static $policy;
    if ($policy === null) {
        $path = $GLOBALS['SYSTEM']['file_base'] . 'ext/data/.config/site';
        $site = is_file($path) ? json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : [];
        $custom = $site['session'] ?? [];
        $policy = array_replace_recursive([
            'anonymous' => 14400,
            'authenticated' => 259200,
            'roles' => ['admin' => 604800],
        ], is_array($custom) ? $custom : []);
        foreach (array_merge([$policy['anonymous'], $policy['authenticated']], array_values($policy['roles'])) as $duration) {
            if (!is_int($duration) || $duration < 60) {
                throw new RuntimeException('Session durations must be integer seconds of at least 60.');
            }
        }
    }
    return $policy;
}

function session_authenticated(array $session): bool
{
    return !empty($session['username']) && $session['username'] !== 'anonymous'
        && empty($session['roles']['anonymous']);
}

function session_idle_lifetime(array $session): int
{
    $policy = session_policy();
    if (!session_authenticated($session)) {
        return $policy['anonymous'];
    }
    $durations = [];
    foreach ($session['assigned_roles'] ?? $session['roles'] ?? [] as $role => $enabled) {
        if ($enabled && isset($policy['roles'][$role])) {
            $durations[] = $policy['roles'][$role];
        }
    }
    return $durations ? max($durations) : $policy['authenticated'];
}

function session_expired(array $session, int $now): bool
{
    // Legacy sessions retain their original validity until their first live request.
    $lifetime = isset($session['session_policy_version']) ? session_idle_lifetime($session) : 86400;
    return empty($session['modified']) || $now >= (int)$session['modified'] + $lifetime;
}



/** Decode PHP's file format without starting, switching, or writing sessions. */
function session_file_decode(string $raw): array
{
    if ($raw === '') {
        return [];
    }
    $offset = 0;
    if (str_starts_with($raw, 'a:')) {
        session_serialized_end($raw, $offset);
        if ($offset !== strlen($raw)) {
            throw new RuntimeException('Trailing session data.');
        }
        $result = session_file_unserialize($raw);
        if (!is_array($result)) {
            throw new RuntimeException('Invalid session array.');
        }
        return $result;
    }
    $result = [];
    while ($offset < strlen($raw)) {
        $separator = strpos($raw, '|', $offset);
        if ($separator === false) {
            throw new RuntimeException('Unsupported or malformed session encoding.');
        }
        $key = substr($raw, $offset, $separator - $offset);
        $offset = $separator + 1;
        $start = $offset;
        session_serialized_end($raw, $offset);
        $result[$key] = session_file_unserialize(substr($raw, $start, $offset - $start));
    }
    return $result;
}

function session_file_unserialize(string $raw): mixed
{
    set_error_handler(function (int $severity, string $message) {
        throw new RuntimeException('Malformed serialized session value.');
    });
    try {
        return unserialize($raw, ['allowed_classes' => false]);
    } finally {
        restore_error_handler();
    }
}

/** Only scalar/array state is inspected; never instantiate serialized objects. */
function session_serialized_end(string $raw, int &$offset, int $depth = 0): void
{
    if ($depth > 64) {
        throw new RuntimeException('Session nesting exceeds inspection limit.');
    }
    if (preg_match('/\G(?:N;|[bid]:[^;]+;)/A', $raw, $match, 0, $offset)) {
        $offset += strlen($match[0]);
        return;
    }
    if (preg_match('/\Gs:(\d+):"/A', $raw, $match, 0, $offset)) {
        $offset += strlen($match[0]) + (int)$match[1];
        if (substr($raw, $offset, 2) !== '";') {
            throw new RuntimeException('Invalid session string.');
        }
        $offset += 2;
        return;
    }
    if (preg_match('/\Ga:(\d+):\{/A', $raw, $match, 0, $offset)) {
        $offset += strlen($match[0]);
        $count = (int)$match[1];
        if ($count > strlen($raw)) {
            throw new RuntimeException('Invalid session array length.');
        }
        for ($i = 0; $i < $count * 2; $i++) {
            session_serialized_end($raw, $offset, $depth + 1);
        }
        if (substr($raw, $offset++, 1) !== '}') {
            throw new RuntimeException('Invalid session array ending.');
        }
        return;
    }
    throw new RuntimeException('Unsupported or malformed session value.');
}

function session_files(): Generator
{
    $path = $GLOBALS['SYSTEM']['session_path'] ?? $GLOBALS['SYSTEM']['file_base'] . 'ext/data/.tmp/sessions';
    if (!is_dir($path)) {
        return;
    }
    foreach (new DirectoryIterator($path) as $entry) {
        if (!$entry->isLink() && $entry->isFile() && preg_match('/^sess_[a-zA-Z0-9,-]+$/D', $entry->getFilename())) {
            yield $entry->getPathname();
        }
    }
}

function session_file_read($handle): array
{
    $raw = stream_get_contents($handle, 1048577);
    if ($raw === false || strlen($raw) > 1048576) {
        throw new RuntimeException('Session exceeds inspection limit.');
    }
    return session_file_decode($raw);
}

function session_prune(bool $dry_run = false): array
{
    $counts = ['expired' => 0, 'removed' => 0, 'retained' => 0, 'locked' => 0, 'failed' => 0];
    $now = time();
    foreach (session_files() as $path) {
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            $counts['failed']++;
            continue;
        }
        try {
            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                $counts['locked']++;
                continue;
            }
            clearstatcache(true, $path);
            $stat = fstat($handle);
            $current = @lstat($path);
            if (!$current || $current['ino'] !== $stat['ino'] || $current['dev'] !== $stat['dev'] || ($current['mode'] & 0170000) !== 0100000) {
                $counts['failed']++;
                continue;
            }
            $session = session_file_read($handle);
            // An empty file may belong to a request which has just initialized it.
            $expired = $session ? session_expired($session, $now) : $now >= $stat['mtime'] + session_policy()['anonymous'];
            if (!$expired) {
                $counts['retained']++;
                continue;
            }
            $counts['expired']++;
            if (!$dry_run) {
                $counts[@unlink($path) ? 'removed' : 'failed']++;
            }
        } catch (Throwable $error) {
            $counts['failed']++;
        } finally {
            fclose($handle);
        }
    }
    return $counts;
}
