<?php

// Job: an agent's check-back is due; it gets a new turn in the conversation. A turn still running waits.
function agent_check_back_job(array $job): array
{
    load_libraries(['agent', 'agent-chat']);
    $payload = (array)($job['payload'] ?? []);
    $result = agent_chat_occasion_turn((string)($payload['agent'] ?? ''), (string)($payload['conversation'] ?? ''),
        'Your own check-back, set earlier: ' . (string)($payload['note'] ?? ''), 'check-back');
    if (empty($result['queued'])) {
        throw new RuntimeException((string)($result['reason'] ?? 'Turn not started'));
    }
    return $result;
}
