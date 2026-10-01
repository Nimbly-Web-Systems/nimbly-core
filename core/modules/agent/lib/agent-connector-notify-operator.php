<?php

// Tool: the agent asks a human for help. Emails the site's operator (the system alert address).
function agent_connector_notify_operator(array $source, array $_config, array $context): array
{
    require_once dirname(__DIR__, 2) . '/system/lib/system-alert.php';
    load_libraries(['data', 'email', 'env', 'get', 'lookup', 'set', 'text']);
    $arguments = (array)(agent_artifact_data($source)['arguments'] ?? []);
    $subject = trim((string)($arguments['subject'] ?? ''));
    $message = trim((string)($arguments['message'] ?? ''));
    if ($subject === '' || $message === '') {
        throw new RuntimeException('Subject and message are required');
    }
    $site_name = data_lookup('.config', 'site', 'name', env('MAIL_FROM_NAME', 'Nimbly'));
    if (is_array($site_name)) {
        $site_name = get_i18n_resolve($site_name, 'auto');
    }
    $agent_name = (string)($context['definition']['name'] ?? $context['run']['agent_id'] ?? 'Agent');
    $agent_id = (string)($context['run']['agent_id'] ?? $context['definition']['id'] ?? '');
    $conversation = (string)($context['run']['event_context']['conversation'] ?? '');
    set_variable('notification_heading', $conversation !== '' ? 'Client request' : 'Review requested');
    set_variable('agent_id', system_alert_html($agent_id));
    set_variable('conversation_id', system_alert_html($conversation));
    set_variable('site_name', system_alert_html($site_name));
    set_variable('environment', system_alert_html(env('APP_ENV', 'unknown')));
    set_variable('agent_name', system_alert_html($agent_name));
    set_variable('agent_message', nl2br(system_alert_html(mb_substr($message, 0, 8000))));
    // From a chat: say who asked, and let the developer reply to them directly.
    $asked_by = '';
    if ($conversation !== '') {
        load_library('agent-site');
        $asked_by = (string)agent_site_asker($context)['username'];
    }
    set_variable('asked_by', system_alert_html($asked_by));
    $sent = email([
        'service' => env('MAIL_SERVICE', 'resend'),
        'from' => env('MAIL_FROM'),
        'from_name' => env('MAIL_FROM_NAME', 'Nimbly'),
        'recipient' => agent_notify_operator_recipient((string)($arguments['about'] ?? '')),
        'subject' => '[' . $site_name . '] ' . $agent_name . ': ' . mb_substr($subject, 0, 150),
        'tpl' => 'email-agent-notify-operator',
    ] + (filter_var($asked_by, FILTER_VALIDATE_EMAIL) ? ['reply_to' => $asked_by] : []));
    if (!$sent) {
        throw new AgentTransientException('The email could not be sent');
    }
    return agent_artifact('agent.tool-result', 1, ['sent' => true]);
}

// Work on the site itself can go to its own developer; hosting and everything else to the operator.
function agent_notify_operator_recipient(string $about): string
{
    $developer = trim((string)env('DEVELOPER_EMAIL'));
    return $about === 'site' && $developer !== '' ? $developer : system_alert_require_recipient();
}
