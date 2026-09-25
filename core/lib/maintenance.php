<?php

/** Invocation is mandatory. The CLI command implementation remains overridable. */
function maintenance_tasks(): array
{
    return [
        ['id' => 'sessions-prune', 'command' => 'sessions:prune', 'every_minutes' => 30],
        ['id' => 'jobs-run', 'command' => 'jobs:run 10', 'every_minutes' => 1],
        ['id' => 'jobs-prune', 'command' => 'jobs:prune --days=30', 'every_minutes' => 1440],
        ['id' => 'stats-rollup', 'command' => 'stats:rollup', 'every_minutes' => 60],
    ];
}

function maintenance_health(array $state, ?int $now = null): array
{
    $now ??= time();
    $issues = [];
    foreach (maintenance_tasks() as $task) {
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
