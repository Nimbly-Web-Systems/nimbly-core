<?php

// Output step: after the agent's email went out, the short chat line it chose to write (if any)
// lands in the recipient's chat with the agent. `from` names the agent step and the delivery step.
function agent_connector_chat_note(array $source, array $config, array $context): array
{
    load_libraries(['env', 'agent-chat']);
    $steps = agent_artifact_data($source);
    $note = trim((string)(agent_artifact_data((array)($steps[(string)($config['note_from'] ?? '')] ?? []))[(string)($config['field'] ?? 'chat_note')] ?? ''));
    $deliveries = (array)(agent_artifact_data((array)($steps[(string)($config['delivery_from'] ?? '')] ?? []))['deliveries'] ?? []);
    $sent = in_array(true, array_column($deliveries, 'accepted'), true);
    $posted = false;
    if ($note !== '' && $sent) {
        try {
            agent_chat_ensure_resource();
            agent_chat_notice(trim((string)env((string)($config['recipient_env'] ?? ''), (string)($config['recipient_default'] ?? ''))),
                (string)$context['run']['agent_id'], mb_substr($note, 0, 500));
            $posted = true;
        } catch (Throwable $error) {
            // The email already went out; a chat line that cannot be posted does not undo that.
            error_log('agent chat note: ' . $error->getMessage());
        }
    }
    return agent_artifact('delivery.receipt', 1, ['success' => true, 'deliveries' => ['chat' => ['accepted' => $posted]]]);
}
