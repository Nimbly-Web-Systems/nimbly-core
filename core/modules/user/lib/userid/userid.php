<?php

load_library('session', 'user');

function userid_sc() {
    return userid_get();
}

function userid_get() {
    if (session_resume() && !empty($_SESSION['userid'])) {
        return $_SESSION['userid'];
    } 
    return 0;
}
