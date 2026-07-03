<?php

load_library('data');
load_library('set');
load_library('fmt');

function jobs_panel_sc($params)
{
    $jobs = data_read('.jobs');
    unset($jobs['.meta']);

    $counts = ['queued' => 0, 'running' => 0, 'done' => 0, 'failed' => 0];
    foreach ($jobs as $job) {
        $status = $job['status'] ?? '';
        if (isset($counts[$status])) {
            $counts[$status]++;
        }
    }

    set_variable('_jp.counts', "{$counts['queued']} queued &middot; {$counts['running']} running &middot; {$counts['done']} done &middot; {$counts['failed']} failed");

    if (empty($jobs)) {
        set_variable('_jp.body', '<p class="text-neutral-500">' . '[#text No jobs recorded yet.#]' . '</p>');
        return run_buffered(dirname(__FILE__) . '/panel.tpl');
    }

    uasort($jobs, fn($a, $b) => ($b['_modified'] ?? 0) <=> ($a['_modified'] ?? 0));

    $rows_html = '';
    foreach ($jobs as $job) {
        set_variable_dot('_row', [
            'status' => htmlspecialchars((string)($job['status'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'status_class' => jobs_panel_status_class((string)($job['status'] ?? '')),
            'type' => htmlspecialchars((string)($job['type'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'attempts' => (int)($job['attempts'] ?? 0),
            'last_error' => htmlspecialchars((string)($job['last_error'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'updated' => htmlspecialchars(ago((int)($job['_modified'] ?? 0)), ENT_QUOTES, 'UTF-8'),
        ]);
        $rows_html .= run_buffered(dirname(__FILE__) . '/row.tpl');
        clear_variable_dot('_row');
    }

    set_variable('_jp.rows', $rows_html);
    set_variable('_jp.body', run_buffered(dirname(__FILE__) . '/table.tpl'));
    return run_buffered(dirname(__FILE__) . '/panel.tpl');
}

function jobs_panel_status_class(string $status): string
{
    return match ($status) {
        'failed' => 'text-red-600 font-semibold',
        'running' => 'text-cnormal font-semibold',
        'queued' => 'text-amber-600 font-semibold',
        default => 'text-neutral-600',
    };
}
