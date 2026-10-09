<?php

load_libraries(['get-user', 'access']);
$user = get_user();
if (empty($user['uuid'])
    || user_password_check($user, (string)filter_input(INPUT_POST, 'current_password')) !== true) {
    $GLOBALS['SYSTEM']['validation_errors']['_global'][] = '[#text validate_wrong_current_password#]';
    return;
}
// The shared email type only screens characters; a new address has to be one.
if (filter_var(trim((string)filter_input(INPUT_POST, 'new_email')), FILTER_VALIDATE_EMAIL) === false) {
    $GLOBALS['SYSTEM']['validation_errors']['_global'][] = '[#text validate_invalid_email#]';
    return;
}
validate_field('email', 'new_email', '[#text validate_invalid_email#]');
