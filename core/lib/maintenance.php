<?php

/** Invocation is mandatory. The CLI command implementation remains overridable. */
function maintenance_tasks(): array
{
    return [
        // First: it only starts the detached chat worker, so jobs:run can never delay a chat reply.
        ['id' => 'agent-chat', 'command' => 'agent:chat', 'every_minutes' => 1],
        ['id' => 'sessions-prune', 'command' => 'sessions:prune', 'every_minutes' => 30],
        ['id' => 'jobs-run', 'command' => 'jobs:run 10', 'every_minutes' => 1],
        ['id' => 'jobs-prune', 'command' => 'jobs:prune --days=30', 'every_minutes' => 1440],
        ['id' => 'history-prune', 'command' => 'history:prune --days=90', 'every_minutes' => 1440],
        ['id' => 'agent-prune', 'command' => 'agent:prune --days=30', 'every_minutes' => 1440],
        ['id' => 'stats-rollup', 'command' => 'stats:rollup', 'every_minutes' => 60],
    ];
}

function maintenance_health(array $state, ?int $now = null, string $state_dir = ''): array
{
    $now ??= time();
    $issues = [];
    foreach (maintenance_tasks() as $task) {
        if (!empty($state['host']) && in_array($task['id'], ['agent-chat', 'jobs-run'], true)) {
            if (maintenance_host_overdue($task['id'], $now, $state_dir)) {
                $issues[$task['id']] = 'overdue';
            }
            continue;
        }
        $last = $state['tasks'][$task['id']] ?? [];
        $success = (int)($last['last_success_at']
            ?? (($last['last_exit_code'] ?? null) === 0 ? ($last['last_finished_at'] ?? 0) : 0));
        if (isset($last['last_exit_code']) && $last['last_exit_code'] !== 0) {
            $issues[$task['id']] = 'failed';
        } elseif (!$success || $now > $success + $task['every_minutes'] * 120) {
            $issues[$task['id']] = 'overdue';
        }
    }
    return $issues;
}

/** The host scheduler starts these only when work waits, so judge the waiting work, not the last run. */
function maintenance_host_overdue(string $id, int $now, string $state_dir = ''): bool
{
    $state_dir = $state_dir ?: ($GLOBALS['SYSTEM']['file_base'] ?? (defined('BASE_DIR') ? BASE_DIR : '')) . 'ext/data/.state/';
    if ($id === 'jobs-run') {
        $due = @file_get_contents($state_dir . 'jobs-due');
        return $due !== false && (int)$due < $now - 120;
    }
    $lock_path = $state_dir . 'chat-worker.lock';
    $handle = @fopen($lock_path, 'r');
    if (!$handle) {
        return true;
    }
    $free = flock($handle, LOCK_SH | LOCK_NB);
    fclose($handle);
    return $free && (int)@filemtime($lock_path) < $now - 120;
}
