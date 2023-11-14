<?php

load_library('set');

function get_sessions_sc() {
    $sessions = get_sessions();
    $all = [];
    $logged_in = [];
    foreach($sessions as $s) {
        if (empty($s['username'])) {
            continue;
        }
        $all[$s['username']] = $s;
        if (!empty($s['created']) && !empty($s['username'])) {
            $logged_in[$s['username']] = $s;
        }
    }
    set_variable('sessions', $all);
    set_variable('logged_in', $logged_in);
}

function get_sessions() {
    $result = [];
    $sessions = scandir(session_save_path());
    $current_session = session_id();  
    session_abort();
    foreach($sessions as $session) {
        if (strpos($session, '.') !== false) {
            continue;
        }
        $sid = str_replace("sess_", "", $session);
        session_id($sid);
        session_start();
        $result[$sid] = $_SESSION;
        session_abort();
    }
    session_id($current_session); 
    session_start();
    return $result;
}