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

    load_library('get-user-resources');
    get_user_resources_sc([]);

    $body = dashboard_attention_section();
    $body .= dashboard_site_status_section($can_pull_ext, $can_pull_core);
    $body .= dashboard_data_section();
    $body .= dashboard_quick_actions_section();
    $body .= dashboard_system_section();
    set_variable('_dash.body', $body);

    return run_buffered(dirname(__FILE__) . '/dashboard.tpl');
}

function dashboard_attention_section(): string
{
    return run_buffered(dirname(__FILE__) . '/attention.tpl');
}

function dashboard_site_status_section(bool $can_pull_ext, bool $can_pull_core): string
{
    $items = [];

    $resources = get_variable('data.user-resources', []);
    if (!empty($resources)) {
        $items[] = dashboard_site_status_item('Data', dashboard_data_last_update($resources), null, null);
    }

    if ($can_pull_core) {
        $items[] = dashboard_site_status_item(
            'Core',
            dashboard_repo_last_update(['core/lib', 'core/modules', 'core/tpl', 'core/uri']),
            'core_updates',
            'pull_core'
        );
    }

    if ($can_pull_ext) {
        $items[] = dashboard_site_status_item(
            'Ext',
            dashboard_repo_last_update(['ext/lib', 'ext/modules', 'ext/tpl', 'ext/uri']),
            'site_updates',
            'pull_site'
        );
    }

    if (empty($items)) {
        return '';
    }

    set_variable('_dash.status_items', implode('', $items));
    return run_buffered(dirname(__FILE__) . '/site-status.tpl');
}

function dashboard_data_section(): string
{
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
    if (access_by_feature('view-users')) {
        $count = count(data_list('users'));
        $actions[] = ['type' => 'link', 'label' => "Users ($count)", 'url' => '/nb-admin/users'];
    }
    if (access_by_feature('view-roles')) {
        $actions[] = ['type' => 'link', 'label' => 'Roles', 'url' => '/nb-admin/roles'];
    }
    if (access_by_feature('view-.files')) {
        $actions[] = ['type' => 'link', 'label' => 'Media Library', 'url' => '/nb-admin/media'];
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

function dashboard_system_section(): string
{
    $lines = [];

    if (access_by_feature('view-users')) {
        load_library('get-sessions');
        get_sessions_sc();
        $active = count(get_variable('logged_in', []));
        $lines[] = $active . ' active ' . ($active === 1 ? 'session' : 'sessions');
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

function dashboard_data_last_update(array $resources): int
{
    load_library('last-update');
    $latest = 0;
    foreach ($resources as $resource) {
        $path = $GLOBALS['SYSTEM']['data_base'] . '/' . $resource['key'];
        if (is_dir($path)) {
            $latest = max($latest, (int)find_latest_time($path));
        }
    }
    return $latest;
}

function dashboard_site_status_item(string $label, int $last_update, ?string $count_var, ?string $pull_fn): string
{
    $ago = $last_update > 0 ? fmt_ago_short($last_update) : 'never';
    $action = '';
    if ($count_var !== null && $pull_fn !== null) {
        $action = ' <span class="block font-medium text-neutral-700" x-cloak x-show="' . $count_var . ' > 0" x-text="' . $count_var . ' + (' . $count_var . ' === 1 ? \' update\' : \' updates\') + \' available\'"></span>'
            . ' <button type="button" class="text-xs font-medium underline text-neutral-700 disabled:opacity-50" x-cloak x-show="' . $count_var . ' > 0" @click="' . $pull_fn . '" :disabled="busy">Update now</button>';
    }
    return '<li class="min-w-[140px]">'
        . '<div class="text-sm font-medium text-neutral-700">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</div>'
        . '<div class="text-xs text-neutral-500">Updated ' . htmlspecialchars($ago, ENT_QUOTES, 'UTF-8') . '</div>'
        . $action
        . '</li>';
}
