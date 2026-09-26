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
    set_variable('site_name', system_alert_html($site_name));
    set_variable('environment', system_alert_html(env('APP_ENV', 'unknown')));
    set_variable('agent_name', system_alert_html($agent_name));
    set_variable('agent_message', nl2br(system_alert_html(mb_substr($message, 0, 8000))));
    $sent = email([
        'service' => env('MAIL_SERVICE', 'resend'),
        'from' => env('MAIL_FROM'),
        'from_name' => env('MAIL_FROM_NAME', 'Nimbly'),
        'recipient' => system_alert_require_recipient(),
        'subject' => '[' . $site_name . '] ' . $agent_name . ': ' . mb_substr($subject, 0, 150),
        'tpl' => 'email-agent-notify-operator',
    ]);
    if (!$sent) {
        throw new AgentTransientException('The email could not be sent');
    }
    return agent_artifact('agent.tool-result', 1, ['sent' => true]);
}
