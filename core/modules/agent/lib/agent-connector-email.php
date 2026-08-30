<?php

// Idempotent delivery connector; manual runs are shadowed by default.
function agent_connector_email(array $source, array $config, array $context): array
{
    $data = agent_artifact_data($source);
    $items = $data[(string)($config['items_key'] ?? 'items')] ?? null;
    if (!is_array($items)) {
        throw new RuntimeException('Email connector items are invalid');
    }
    $run_uuid = (string)$context['run_uuid'];
    if (in_array((string)($context['run']['trigger'] ?? ''), (array)($config['shadow_triggers'] ?? ['manual']), true)
        && empty($context['force_delivery'])) {
        $deliveries = [];
        foreach ($items as $item) {
            $deliveries[(string)($item[$config['item_key'] ?? 'id'] ?? '')] = [
                'accepted' => false, 'shadow' => true, 'provider_message_id' => '',
            ];
        }
        return agent_artifact('delivery.receipt', 1, ['success' => true, 'deliveries' => $deliveries]);
    }
    load_libraries(['email', 'env']);
    $recipient = env((string)($config['recipient_env'] ?? ''), (string)($config['recipient_default'] ?? ''));
    if ($recipient === '') {
        throw new RuntimeException('Email connector recipient is unavailable');
    }
    $deliveries = [];
    foreach ($items as $item) {
        $key = (string)($item[$config['item_key'] ?? 'id'] ?? '');
        $email = [
            'service' => (string)($config['service'] ?? 'resend'),
            'recipient' => $recipient,
            'subject' => (string)($item[$config['subject_field'] ?? 'subject'] ?? ''),
            'html' => (string)($item[$config['html_field'] ?? 'html'] ?? ''),
            'idempotency_key' => $run_uuid . ':' . $key,
        ];
        if (!empty($context['email_request'])) {
            $email['request'] = $context['email_request'];
        }
        $sent = email_result($email);
        $deliveries[$key] = [
            'accepted' => !empty($sent['success']), 'provider_message_id' => (string)($sent['id'] ?? ''),
        ];
        if (empty($sent['success']) || empty($sent['id'])) {
            throw new AgentTransientException('Email provider did not accept every item');
        }
    }
    return agent_artifact('delivery.receipt', 1, [
        'success' => count($deliveries) === count($items), 'deliveries' => $deliveries,
    ]);
}
