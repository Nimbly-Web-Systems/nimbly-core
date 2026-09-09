<?php

$root = dirname(__DIR__, 2) . '/';
$tmp = sys_get_temp_dir() . '/nimbly-session-test-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700);
$GLOBALS['SYSTEM'] = ['file_base' => $root, 'env_paths' => ['ext', 'core'], 'modules' => ['root' => '/'], 'session_path' => $tmp];
require $root . 'core/lib/find.php';
load_library('session');
$GLOBALS['SYSTEM']['session_path'] = $tmp;
require $root . 'core/modules/admin/uri/nb-admin/get-sessions.inc';
set_error_handler(function ($severity, $message) { throw new RuntimeException($message); });
function session_test_assert($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function session_test_encode(array $data): string {
    $raw = '';
    foreach ($data as $key => $value) {
        $raw .= $key . '|' . serialize($value);
    }
    return $raw;
}
$now = time();
$user = ['username' => 'test@example.test', 'user_uuid' => 'test-user', 'modified' => $now, 'created' => $now - 86400 * 90, 'roles' => ['user' => true], 'session_policy_version' => 1];
$admin = array_replace($user, ['roles' => ['admin' => true]]);
try {
    session_test_assert(session_idle_lifetime([]) === 14400, 'anonymous expiry');
    session_test_assert(session_idle_lifetime($user) === 259200, 'user expiry');
    session_test_assert(session_idle_lifetime($admin) === 604800, 'admin expiry');
    session_test_assert(!session_expired($admin, $now + 604799), 'active admin survives beyond 30 days of total age');
    session_test_assert(session_expired($admin, $now + 604800), 'exact admin idle boundary');
    session_test_assert(session_idle_lifetime(array_replace($user, ['assigned_roles' => ['admin' => true]])) === 604800, 'assigned roles survive role switching');
    $legacy = $admin; unset($legacy['session_policy_version']);
    session_test_assert(session_expired($legacy, $now + 86400), 'legacy sessions cannot be revived');
    $complex = $user + ['payload' => ['unicode' => 'héllo|there;"', 'nested' => [null, false, 1.25, -5]]];
    session_test_assert(session_file_decode(session_test_encode($complex)) === $complex, 'native php format');
    session_test_assert(session_file_decode(serialize($complex)) === $complex, 'php_serialize format');
    foreach (['O:8:"stdClass":0:{}', 'a:100:{', 'x|s:999:"x";', 'x|R:1;'] as $bad) {
        $rejected = false;
        try { session_file_decode($bad); } catch (Throwable $error) { $rejected = true; }
        session_test_assert($rejected, 'reject malformed/unsupported serialized data');
    }
    file_put_contents($tmp . '/sess_active', session_test_encode($admin));
    $expired = array_replace($user, ['modified' => $now - 604801]);
    file_put_contents($tmp . '/sess_expired', session_test_encode($expired));
    file_put_contents($tmp . '/sess_locked', session_test_encode($expired));
    file_put_contents($tmp . '/sess_malformed', 'broken');
    file_put_contents($tmp . '/sess_empty', '');
    touch($tmp . '/sess_empty', $now - 14401);
    symlink($tmp . '/sess_active', $tmp . '/sess_link');
    $locked = fopen($tmp . '/sess_locked', 'rb'); flock($locked, LOCK_EX);
    $before = hash_file('sha256', $tmp . '/sess_active');
    $dry = session_prune(true);
    session_test_assert($dry['expired'] === 2 && $dry['locked'] === 1 && $dry['failed'] === 1, 'dry-run classification');
    session_test_assert(is_file($tmp . '/sess_expired'), 'dry-run preserves files');
    $actual = session_prune();
    session_test_assert($actual['removed'] === 2 && is_file($tmp . '/sess_locked'), 'remove expired only, skip busy');
    session_test_assert(hash_file('sha256', $tmp . '/sess_active') === $before, 'active file unchanged');
    session_test_assert(is_link($tmp . '/sess_link'), 'symlinks untouched');
    session_test_assert(session_prune()['removed'] === 0, 'repeat cleanup harmless');
    fclose($locked);
    // Reproduce a directory larger than the incident without large decoded arrays.
    $anonymous = session_test_encode(['modified' => $now, 'roles' => ['anonymous' => true], 'session_policy_version' => 1]);
    for ($i = 0; $i < 100000; $i++) {
        file_put_contents($tmp . '/sess_fixture' . $i, $i % 2 ? $anonymous : session_test_encode($expired));
    }
    $_SESSION = $admin;
    $snapshot = $_SESSION;
    $before_memory = memory_get_usage(true);
    get_sessions_sc();
    session_test_assert(count($GLOBALS['SYSTEM']['variables']['logged_in']) === 1, 'only one distinct authenticated user');
    session_test_assert($_SESSION === $snapshot, 'inspection preserves current session');
    session_test_assert(memory_get_peak_usage(true) - $before_memory < 32 * 1024 * 1024, 'bounded inspection memory');
    echo "session tests passed (100,000 fixtures, peak " . memory_get_peak_usage(true) . " bytes)\n";
} finally {
    restore_error_handler();
    foreach (new DirectoryIterator($tmp) as $entry) {
        if (!$entry->isDot()) { unlink($entry->getPathname()); }
    }
    rmdir($tmp);
}
