<?php

function system_alert_recipient()
{
    load_library('env');

    $recipient = trim((string)env('SYSTEM_ALERT_EMAIL'));
    if ($recipient === '') {
        $recipient = trim((string)env('ADMIN_EMAIL'));
    }

    return $recipient;
}

function system_alert_require_recipient()
{
    $recipient = system_alert_recipient();
    if ($recipient === '') {
        throw new Exception('SYSTEM_ALERT_EMAIL or ADMIN_EMAIL must be configured');
    }

    return $recipient;
}

function system_alert_html($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
