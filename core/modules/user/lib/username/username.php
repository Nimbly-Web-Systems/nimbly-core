<?php

function username_sc() {
    return username_get();
}

function username_get() {
    load_library('userfield', 'user');
    return userfield('name');
}
