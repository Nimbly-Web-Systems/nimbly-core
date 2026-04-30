<?php

function change_email_job($job)
{
    $payload = $job['payload'] ?? [];
    $email = $payload['email'] ?? '';
    if ($email === '') {
        return false;
    }

    load_libraries(['email', 'env', 'set', 'log', 'data', 'lookup']);

    $site_name = lookup_data('.config', 'site', 'name', 'our site');
    $subject = lookup_data('.config', 'site', 'change_email_subject', 'Confirm your new ' . $site_name . ' email address');

    set_variable('name', $payload['name'] ?? $email);
    set_variable('change-email-url', $payload['change_email_url'] ?? '');

    $cfg = [
        'service'   => env('MAIL_SERVICE', 'resend'),
        'from'      => env('MAIL_FROM'),
        'from_name' => env('MAIL_FROM_NAME'),
        'recipient' => $email,
        'subject'   => $subject,
        'tpl'       => 'email-change-email',
    ];

    if (!email($cfg)) {
        log_system('Error: change email notification failed for ' . $email);
        return false;
    }

    log_system('Change email notification sent to ' . $email);
    return true;
}
