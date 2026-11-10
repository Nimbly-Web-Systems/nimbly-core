<?php

load_library('password-wall');

if (password_wall_enter(get_variable('password_wall_name'), (string)filter_input(INPUT_POST, 'password')) !== true) {
    $GLOBALS['SYSTEM']['validation_errors']['_global'][] = '[#text Wrong password#]';
}
