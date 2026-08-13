<?php

function agent_append_event(string $run_uuid, string $type, array $payload): string
{
    $events = data_read('.agent_events') ?: [];
    $sequence = 1;
    foreach ($events as $event) {
        if (($event['run_uuid'] ?? '') === $run_uuid) {
            $sequence = max($sequence, (int)($event['sequence'] ?? 0) + 1);
        }
    }
    $uuid = substr(hash('sha256', $run_uuid . ':' . $sequence), 0, 16);
    data_create('.agent_events', $uuid, [
        'run_uuid' => $run_uuid,
        'sequence' => $sequence,
        'occurred_at' => time(),
        'type' => $type,
        'payload' => $payload,
    ]);
    return $uuid;
}

function agent_tool_result(string $run_uuid, string $tool_key): ?array
{
    foreach (data_read('.agent_events') ?: [] as $event) {
        if (($event['run_uuid'] ?? '') === $run_uuid
            && ($event['type'] ?? '') === 'tool_completed'
            && ($event['payload']['tool_key'] ?? '') === $tool_key) {
            return is_array($event['payload']['result'] ?? null) ? $event['payload']['result'] : [];
        }
    }
    return null;
}

function agent_update_run(string $run_uuid, array $changes): void
{
    $run = data_read('.agent_runs', $run_uuid);
    if (!is_array($run)) {
        throw new RuntimeException('Agent run not found');
    }
    if (in_array($run['status'] ?? '', AGENT_TERMINAL_STATUSES, true)) {
        throw new RuntimeException('Terminal agent runs are immutable');
    }
    if (!data_update('.agent_runs', $run_uuid, $changes)) {
        throw new RuntimeException('Could not update agent run');
    }
}

function agent_recover_expired_runs(?int $now = null): int
{
    agent_ensure_resources();
    $now = $now ?? time();
    $count = 0;
    load_library('job');
    foreach (data_read('.agent_runs') ?: [] as $uuid => $run) {
        if (($run['status'] ?? '') !== 'running' || (int)($run['lease_expires_at'] ?? 0) >= $now) {
            continue;
        }
        data_update('.agent_runs', $uuid, ['status' => 'scheduled', 'lease_expires_at' => 0]);
        agent_append_event($uuid, 'run_recovered', []);
        job_enqueue('agent-run', ['run_uuid' => $uuid], [
            'uuid' => substr(hash('sha256', 'agent-recovery:' . $uuid . ':' . $now), 0, 16),
            'max_attempts' => 3,
        ]);
        $count++;
    }
    return $count;
}

function agent_watchdog_status(string $agent_id, ?int $now = null): array
{
    $definition = agent_definition($agent_id);
    $now = $now ?? time();
    $timezone = new DateTimeZone($definition['timezone'] ?? 'UTC');
    $local = (new DateTimeImmutable('@' . $now))->setTimezone($timezone);
    $occurrence = $local->format('Y-m-d');
    $run = null;
    foreach (data_read('.agent_runs') ?: [] as $candidate) {
        if (($candidate['agent_id'] ?? '') === $agent_id
            && ($candidate['scheduled_occurrence'] ?? '') === $occurrence
            && in_array($candidate['trigger'] ?? '', ['scheduled', 'scheduled_retry'], true)
            && ($run === null || (int)($candidate['scheduled_at'] ?? 0) > (int)($run['scheduled_at'] ?? 0))) {
            $run = $candidate;
        }
    }
    $deadline = DateTimeImmutable::createFromFormat(
        'Y-m-d H:i',
        $occurrence . ' ' . ($definition['deadline_at'] ?? '23:59'),
        $timezone
    );
    $healthy = is_array($run) && ($run['status'] ?? '') === 'completed';
    if (!$healthy && $deadline instanceof DateTimeImmutable && $now < $deadline->getTimestamp()) {
        return ['healthy' => true, 'state' => 'pending'];
    }
    return ['healthy' => $healthy, 'state' => $healthy ? 'completed' : 'overdue'];
}

