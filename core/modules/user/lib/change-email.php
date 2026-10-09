<?php

function change_email_job($job)
{
    $payload = $job['payload'] ?? [];
    $email = $payload['email'] ?? '';
    if ($email === '') {
        return false;
    }

    load_libraries(['email', 'env', 'set', 'log', 'data', 'lookup', 'get']);

    $site_name = data_lookup('.config', 'site', 'name', 'our site');
    if (is_array($site_name)) {
        $site_name = get_i18n_resolve($site_name, 'auto');
    }
    $subject = data_lookup('.config', 'site', 'change_email_subject', 'Confirm your new ' . $site_name . ' email address');

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

/**
 * Stores the address a user asks for and mails the link that confirms it.
 * The address in use stays until the link is followed.
 */
function change_email_request($user, $email)
{
    load_libraries(['data', 'util', 'url', 'job', 'log']);

    $email = trim((string)$email);
    $token = generate_uuid();
    $stored = data_update('users', $user['uuid'], [
        'new_email' => $email,
        'change_email_token' => $token,
        'change_email_token_at' => time(),
    ]);
    if (!is_array($stored) || ($stored['change_email_token'] ?? '') !== $token) {
        return false;
    }

    $queued = job_enqueue('change-email', [
        'email' => $email,
        'name' => $user['name'] ?? $user['email'],
        'change_email_url' => url_absolute('change-email/' . $user['uuid'] . '/' . md5($email) . '/' . $token),
    ]);
    if ($queued === false) {
        return false;
    }
    log_system_event('email.change_requested', ['user_uuid' => $user['uuid']]);
    return true;
}

/**
 * Makes the requested address the user's address when the link is the one
 * that was mailed, is a day old at most and the address is still free.
 *
 * @return array|false The user as stored, with old_email; false when the link does not hold.
 */
function change_email_confirm($uuid, $email_hash, $token)
{
    load_libraries(['data', 'get-user', 'log']);

    $user = data_exists('users', $uuid) ? data_read('users', $uuid) : null;
    if (empty($user['new_email']) || empty($user['change_email_token'])) {
        return false;
    }
    if (!hash_equals(md5($user['new_email']), (string)$email_hash)
        || !hash_equals((string)$user['change_email_token'], (string)$token)
        || time() - (int)($user['change_email_token_at'] ?? 0) > 86400) {
        return false;
    }
    $taken = find_user_by_email($user['new_email']);
    if (!empty($taken) && ($taken['uuid'] ?? '') !== $uuid) {
        return false;
    }

    $stored = data_update('users', $uuid, [
        'old_email' => $user['email'],
        'email' => $user['new_email'],
        'new_email' => '',
        'change_email_token' => '',
    ]);
    if (!is_array($stored) || ($stored['email'] ?? '') !== $user['new_email']) {
        return false;
    }
    log_system_event('email.changed', ['user_uuid' => $uuid]);
    return $stored;
}
