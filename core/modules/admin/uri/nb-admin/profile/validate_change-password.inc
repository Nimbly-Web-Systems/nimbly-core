<?php

load_libraries(['get-user', 'access']);
$user = get_user();
if (empty($user['uuid'])) {
    $GLOBALS['SYSTEM']['validation_errors']['_global'][] = '[#text validate_wrong_current_password#]';
    return;
}
if (user_password_check($user, (string)filter_input(INPUT_POST, 'current_password')) !== true) {
    $GLOBALS['SYSTEM']['validation_errors']['_global'][] = '[#text validate_wrong_current_password#]';
    return;
}
validate_field('new_password', 'new_password', '[#text validate_invalid_password#]');
