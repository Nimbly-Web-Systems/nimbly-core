<?php

load_library('set');
load_library('session');

function get_sessions_sc() {
    $users = [];
    $current = $_SESSION ?? [];
    if (session_authenticated($current) && !session_expired($current, time())) {
        $users[$current['user_uuid'] ?? $current['username']] = ['username' => $current['username']];
    }
    foreach (get_sessions() as $session) {
        $users[$session['user_uuid'] ?? $session['username']] = ['username' => $session['username']];
    }
    set_variable('sessions', $users);
    set_variable('logged_in', $users);
}

function get_sessions(): Generator {
    foreach (session_files() as $path) {
        if (basename($path) === 'sess_' . session_id()) {
            continue;
        }
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            continue;
        }
        try {
            if (!flock($handle, LOCK_SH | LOCK_NB)) {
                continue;
            }
            $session = session_file_read($handle);
            if (session_authenticated($session) && !session_expired($session, time())) {
                yield $session;
            }
        } catch (Throwable $error) {
            // Cleanup reports malformed files; dashboard inspection stays read-only.
            continue;
        } finally {
            fclose($handle);
        }
    }
}
