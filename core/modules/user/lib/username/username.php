<?php

load_library('session', 'user');

function username_sc() {
    return username_get();
}

function username_get() {
    if (session_resume() && !empty($_SESSION['username'])) {
        return $_SESSION['username'];
    } else {
        return 'anonymous';
    }
}
