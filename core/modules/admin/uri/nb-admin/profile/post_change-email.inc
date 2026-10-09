<?php

load_libraries(['get-user', 'change-email']);
$email = trim((string)filter_input(INPUT_POST, 'new_email'));
if (!change_email_request(get_user(), $email)) {
    $GLOBALS['SYSTEM']['validation_errors']['_global'][] = '[#text email_save_failed#]';
    return;
}
load_library('system-messages');
system_message('Follow the link we sent to ' . $email . ' to confirm your new email address.');
load_library('redirect');
redirect('nb-admin/profile');
