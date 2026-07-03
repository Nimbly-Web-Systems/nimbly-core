<?php

load_library('data');
load_library('access');
load_library('set');

function dashboard_sc($params)
{
    $failed_jobs = 0;
    if (access_by_feature('view-.jobs')) {
        foreach (data_read('.jobs') as $uuid => $job) {
            if ($uuid === '.meta') {
                continue;
            }
            if (($job['status'] ?? '') === 'failed') {
                $failed_jobs++;
            }
        }
    }

    $has_recent_error = false;
    if (access_by_feature('view-system-log')) {
        load_library('get-system-log');
        get_system_log_sc(['last-fatal']);
        $last_fatal_time = (int)(get_variable('last_fatal')['time'] ?? 0);
        $has_recent_error = $last_fatal_time > 0 && $last_fatal_time > (time() - 86400);
    }

    $low_disk = false;
    if (access_by_feature('view-debug,view-system-log')) {
        load_library('disk-space-free');
        load_library('disk-space-total');
        $total = disk_space_total_sc();
        $free = disk_space_free_sc();
        $low_disk = $total > 0 && ($free / $total) < 0.1;
    }

    $can_pull_ext = access_by_feature('pull-ext-updates');
    $can_pull_core = access_by_feature('pull-core-updates');

    set_variable('_dash.failed_jobs', $failed_jobs);
    set_variable('_dash.has_recent_error', $has_recent_error ? 'true' : 'false');
    set_variable('_dash.low_disk', $low_disk ? 'true' : 'false');
    set_variable('_dash.can_pull_ext', $can_pull_ext ? 'true' : 'false');
    set_variable('_dash.can_pull_core', $can_pull_core ? 'true' : 'false');

    $body = dashboard_attention_section();
    $body .= dashboard_data_section();
    $body .= dashboard_quick_actions_section();
    $body .= dashboard_system_section($can_pull_ext, $can_pull_core);
    set_variable('_dash.body', $body);

    return run_buffered(dirname(__FILE__) . '/dashboard.tpl');
}

function dashboard_attention_section(): string
{
    return run_buffered(dirname(__FILE__) . '/attention.tpl');
}

function dashboard_data_section(): string
{
    load_library('get-user-resources');
    get_user_resources_sc([]);
    if (empty(get_variable('data.user-resources'))) {
        return '';
    }
    return run_buffered(dirname(__FILE__) . '/data-band.tpl');
}

function dashboard_quick_actions_section(): string
{
    $actions = [];

    if (access_by_feature('create-users')) {
        $actions[] = ['type' => 'link', 'label' => 'Add user', 'url' => '/nb-admin/users/add'];
    }
    if (access_by_feature('create-roles')) {
        $actions[] = ['type' => 'link', 'label' => 'Add role', 'url' => '/nb-admin/roles/add'];
    }
    if (access_by_feature('edit-.config')) {
        $actions[] = ['type' => 'link', 'label' => 'Site settings', 'url' => '/nb-admin/settings'];
    }
    if (access_by_feature('clear-cache')) {
        load_library('disk-space-thumbs');
        $size = fmt_bytes_short(disk_space_thumbs_sc());
        $actions[] = ['type' => 'post', 'label' => "Clear media cache ($size)", 'form_id' => 'ccache_thumbs'];
        $actions[] = ['type' => 'post', 'label' => 'Clear all sessions', 'form_id' => 'ccache_sessions'];
    }
    if (access_by_feature('delete-.files')) {
        $actions[] = ['type' => 'post', 'label' => 'Delete unused media', 'form_id' => 'delete_unusued_media'];
    }

    if (empty($actions)) {
        return '';
    }

    $items_html = '';
    foreach ($actions as $action) {
        set_variable_dot('_action', $action);
        $items_html .= run_buffered(dirname(__FILE__) . '/quick-action-' . $action['type'] . '.tpl');
        clear_variable_dot('_action');
    }
    set_variable('_dash.actions', $items_html);
    return run_buffered(dirname(__FILE__) . '/quick-actions.tpl');
}

function dashboard_system_section(bool $can_pull_ext, bool $can_pull_core): string
{
    $lines = [];

    if ($can_pull_core) {
        $lines[] = dashboard_repo_update_line(
            'Core',
            dashboard_repo_last_update(['core/lib', 'core/modules', 'core/tpl', 'core/uri']),
            'core_updates',
            'pull_core'
        );
    }

    if ($can_pull_ext) {
        $lines[] = dashboard_repo_update_line(
            'Ext',
            dashboard_repo_last_update(['ext/lib', 'ext/modules', 'ext/tpl', 'ext/uri']),
            'site_updates',
            'pull_site'
        );
    }

    if (access_by_feature('view-users')) {
        load_library('get-sessions');
        get_sessions_sc();
        $accounts = count(data_list('users'));
        $active = count(get_variable('logged_in', []));
        $lines[] = $accounts . ' ' . ($accounts === 1 ? 'account' : 'accounts')
            . ' &middot; ' . $active . ' active ' . ($active === 1 ? 'session' : 'sessions');
    }

    if (access_by_feature('view-debug')) {
        load_library('sys-info');
        $mem = get_mem_info();
        load_library('disk-space-free');
        load_library('disk-space-total');
        $lines[] = fmt_bytes_short((int)($mem['MemAvailable'] ?? 0)) . ' RAM free'
            . ' &middot; ' . fmt_bytes_short(disk_space_free_sc()) . ' disk free of ' . fmt_bytes_short(disk_space_total_sc());
    }

    if (access_by_feature('view-.jobs')) {
        $schedule = data_read('.state', 'schedule');
        $last_run = 0;
        if (!empty($schedule['tasks']) && is_array($schedule['tasks'])) {
            foreach ($schedule['tasks'] as $task) {
                $last_run = max($last_run, (int)($task['last_run_at'] ?? 0));
            }
        }
        $lines[] = $last_run > 0
            ? 'Scheduler last ran ' . fmt_ago_short($last_run)
            : 'Scheduler has not run yet';
    }

    if (empty($lines)) {
        return '';
    }

    $items_html = '';
    foreach ($lines as $line) {
        $items_html .= '<li>' . $line . '</li>';
    }
    set_variable('_dash.system_lines', $items_html);
    return run_buffered(dirname(__FILE__) . '/system-summary.tpl');
}

function fmt_bytes_short(int $bytes): string
{
    load_library('fmt');
    return fmt_bytes($bytes, 1);
}

function fmt_ago_short(int $timestamp): string
{
    load_library('fmt');
    return ago($timestamp);
}

function dashboard_repo_last_update(array $dirs): int
{
    load_library('last-update');
    load_library('base-path');
    $bdir = base_path_sc();
    $latest = 0;
    foreach ($dirs as $dir) {
        $path = $bdir . $dir;
        if (is_dir($path)) {
            $latest = max($latest, (int)find_latest_time($path));
        }
    }
    return $latest;
}

function dashboard_repo_update_line(string $label, int $last_update, string $count_var, string $pull_fn): string
{
    $ago = $last_update > 0 ? fmt_ago_short($last_update) : 'never';
    return htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ' updated ' . htmlspecialchars($ago, ENT_QUOTES, 'UTF-8')
        . ' <span class="font-medium text-neutral-700" x-cloak x-show="' . $count_var . ' > 0" x-text="\'· \' + ' . $count_var . ' + (' . $count_var . ' === 1 ? \' update\' : \' updates\') + \' available\'"></span>'
        . ' <button type="button" class="font-medium underline text-neutral-700 disabled:opacity-50" x-cloak x-show="' . $count_var . ' > 0" @click="' . $pull_fn . '" :disabled="busy">Update now</button>';
}
