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
    $body .= dashboard_manage_section();
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
        [$data_ts, $data_label] = dashboard_data_freshness($resources);
        $items[] = dashboard_data_status_item($data_label ?? '', $data_ts);
    }

    if ($can_pull_core) {
        $items[] = dashboard_repo_status_item(
            'Core',
            dashboard_repo_last_update(['core/lib', 'core/modules', 'core/tpl', 'core/uri']),
            'core_updates',
            'pull_core',
            'cube',
            'bg-indigo-50 text-indigo-500'
        );
    }

    if ($can_pull_ext) {
        $items[] = dashboard_repo_status_item(
            'Ext',
            dashboard_repo_last_update(['ext/lib', 'ext/modules', 'ext/tpl', 'ext/uri']),
            'site_updates',
            'pull_site',
            'code-bracket',
            'bg-teal-50 text-teal-500'
        );
    }

    if (access_by_feature('view-debug')) {
        $items[] = dashboard_system_status_item();
    }

    if (empty($items)) {
        return '';
    }

    set_variable('_dash.status_items', implode('', $items));
    return run_buffered(dirname(__FILE__) . '/site-status.tpl');
}

function dashboard_system_status_item(): string
{
    load_library('sys-info');
    $mem = get_mem_info();
    load_library('disk-space-free');
    load_library('disk-space-total');
    $ram_free = (int)($mem['MemAvailable'] ?? 0);
    $disk_free = disk_space_free_sc();
    $disk_total = disk_space_total_sc();

    $ram_ok = $ram_free >= 1 * 1024 * 1024 * 1024;
    $disk_ok = $disk_free >= 500 * 1024 * 1024;
    $ok = $ram_ok && $disk_ok;

    $fact = fmt_bytes_short($ram_free) . ' RAM free'
        . ' · ' . fmt_bytes_short($disk_free) . ' disk free of ' . fmt_bytes_short($disk_total);

    load_library('base-url');
    $status_class = $ok ? 'text-neutral-500' : 'text-amber-500';
    $status_text = $ok ? 'OK' : 'Low resources';

    return '<li class="min-w-[140px]">'
        . dashboard_status_heading('System', 'server', 'bg-slate-100 text-slate-500')
        . '<div class="text-xl font-semibold ' . $status_class . '">' . $status_text . '</div>'
        . '<div class="text-xs text-neutral-500">' . htmlspecialchars($fact, ENT_QUOTES, 'UTF-8') . '</div>'
        . '<a href="' . base_url_sc() . '/nb-admin/debug" class="cursor-pointer text-xs font-medium text-neutral-600 underline decoration-neutral-300 hover:text-neutral-800 hover:decoration-neutral-500">View debug</a>'
        . '</li>';
}

function dashboard_data_section(): string
{
    if (empty(get_variable('data.user-resources'))) {
        return '';
    }
    return run_buffered(dirname(__FILE__) . '/data-band.tpl');
}

function dashboard_manage_section(): string
{
    $groups = [
        dashboard_manage_users_group(),
        dashboard_manage_media_group(),
        dashboard_manage_jobs_group(),
    ];
    $groups = array_filter($groups, fn($group) => $group !== '');

    if (empty($groups)) {
        return '';
    }

    set_variable('_dash.manage_groups', implode('', $groups));
    return run_buffered(dirname(__FILE__) . '/manage.tpl');
}

function dashboard_manage_users_group(): string
{
    $link_entries = [];
    $caption = null;

    if (access_by_feature('view-users')) {
        $link_entries[] = ['label' => 'Users (' . count(data_list('users')) . ')', 'url' => '/nb-admin/users'];
        load_library('get-sessions');
        get_sessions_sc();
        $active = count(get_variable('logged_in', []));
        $caption = $active . ' active ' . ($active === 1 ? 'session' : 'sessions');
    }
    if (access_by_feature('view-roles')) {
        $link_entries[] = ['label' => 'Roles (' . count(data_list('roles')) . ')', 'url' => '/nb-admin/roles'];
    }

    $pill_entries = [];
    if (access_by_feature('create-users')) {
        $pill_entries[] = ['label' => 'Add user', 'url' => '/nb-admin/users/add'];
    }

    $actions = [];
    if (access_by_feature('clear-cache')) {
        $actions[] = ['type' => 'post', 'label' => 'Clear all sessions', 'form_id' => 'ccache_sessions', 'action' => '/nb-admin'];
    }

    return dashboard_manage_group('Users & roles', 'user', 'bg-sky-50 text-sky-500', $pill_entries, $link_entries, $actions, $caption);
}

function dashboard_manage_media_group(): string
{
    $pill_entries = [];
    $caption = null;

    if (access_by_feature('view-.files')) {
        $pill_entries[] = ['label' => 'Media Library (' . count(data_list('.files_meta')) . ')', 'url' => '/nb-admin/media'];
        load_library('last-update');
        $path = $GLOBALS['SYSTEM']['data_base'] . '/.files_meta';
        $last_update = is_dir($path) ? (int)find_latest_time($path) : 0;
        $caption = $last_update > 0 ? 'Updated ' . fmt_ago_short($last_update) : 'No files yet';
    }

    $actions = [];
    if (access_by_feature('clear-cache')) {
        load_library('disk-space-thumbs');
        $size = fmt_bytes_short(disk_space_thumbs_sc());
        $actions[] = ['type' => 'post', 'label' => "Clear thumbnail cache ($size)", 'form_id' => 'ccache_thumbs', 'action' => '/nb-admin'];

        $data_cache_size = fmt_bytes_short(dashboard_data_cache_size());
        $actions[] = ['type' => 'post', 'label' => "Clear data cache ($data_cache_size)", 'form_id' => 'ccache_data', 'action' => '/nb-admin'];
    }
    if (access_by_feature('delete-.files')) {
        $actions[] = ['type' => 'post', 'label' => 'Delete unused media', 'form_id' => 'delete_unusued_media', 'action' => '/nb-admin'];
    }

    return dashboard_manage_group('Media library', 'photo', 'bg-violet-50 text-violet-500', $pill_entries, [], $actions, $caption);
}

function dashboard_data_cache_size(): int
{
    load_library('util');
    $path = $GLOBALS['SYSTEM']['file_base'] . 'ext/data/.tmp/cache/_data';
    return is_dir($path) ? (int)dir_size($path) : 0;
}

function dashboard_manage_jobs_group(): string
{
    $pill_entries = [];
    $caption = null;

    if (access_by_feature('view-.jobs')) {
        $queued = 0;
        foreach (data_read('.jobs') as $uuid => $job) {
            if ($uuid === '.meta') {
                continue;
            }
            if (($job['status'] ?? '') === 'queued') {
                $queued++;
            }
        }
        $pill_entries[] = ['label' => "Jobs ($queued)", 'url' => '/nb-admin/jobs'];

        $schedule = data_read('.state', 'schedule');
        $last_run = 0;
        if (!empty($schedule['tasks']) && is_array($schedule['tasks'])) {
            foreach ($schedule['tasks'] as $task) {
                $last_run = max($last_run, (int)($task['last_run_at'] ?? 0));
            }
        }
        $caption = $last_run > 0 ? 'Scheduler last ran ' . fmt_ago_short($last_run) : 'Scheduler has not run yet';
    }

    $actions = [];
    if (access_by_feature('manage-.jobs')) {
        $actions[] = ['type' => 'post', 'label' => 'Run due jobs now', 'form_id' => 'run_jobs', 'action' => '/nb-admin/jobs'];
    }

    return dashboard_manage_group('Jobs', 'bolt', 'bg-emerald-50 text-emerald-500', $pill_entries, [], $actions, $caption);
}

function dashboard_manage_group(string $heading, string $icon, string $color, array $pill_entries, array $link_entries, array $actions, ?string $caption): string
{
    if (empty($pill_entries) && empty($link_entries) && empty($actions) && $caption === null) {
        return '';
    }

    load_library('base-url');

    $caption_html = $caption !== null
        ? '<div class="text-xs text-neutral-500">' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '</div>'
        : '';

    $pill_html = '';
    foreach ($pill_entries as $entry) {
        $pill_html .= '<a href="' . base_url_sc() . htmlspecialchars($entry['url'], ENT_QUOTES, 'UTF-8') . '"'
            . ' class="inline-flex items-center rounded-full border border-neutral-300 bg-neutral-100 px-3 py-1 text-sm font-medium text-neutral-800 hover:bg-neutral-200">'
            . htmlspecialchars($entry['label'], ENT_QUOTES, 'UTF-8') . '</a>';
    }

    $secondary_html = '';
    foreach ($link_entries as $entry) {
        $secondary_html .= '<a href="' . base_url_sc() . htmlspecialchars($entry['url'], ENT_QUOTES, 'UTF-8') . '"'
            . ' class="' . dashboard_secondary_link_class() . '">'
            . htmlspecialchars($entry['label'], ENT_QUOTES, 'UTF-8') . '</a>';
    }
    foreach ($actions as $action) {
        set_variable_dot('_action', $action);
        $secondary_html .= run_buffered(dirname(__FILE__) . '/quick-action-' . $action['type'] . '.tpl');
        clear_variable_dot('_action');
    }

    return '<div class="flex flex-col rounded-xl border border-neutral-200 p-3">'
        . dashboard_status_heading($heading, $icon, $color)
        . $caption_html
        . ($pill_html !== '' ? '<div class="mt-2 flex flex-wrap items-center gap-2">' . $pill_html . '</div>' : '')
        . ($secondary_html !== '' ? '<div class="mt-2 flex flex-wrap items-center gap-3">' . $secondary_html . '</div>' : '')
        . '</div>';
}

function dashboard_secondary_link_class(): string
{
    return 'cursor-pointer text-sm font-medium text-neutral-600 underline decoration-neutral-300 hover:text-neutral-800 hover:decoration-neutral-500';
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

function dashboard_data_freshness(array $resources): array
{
    load_library('last-update');
    $latest = 0;
    $latest_label = null;
    foreach ($resources as $resource) {
        $path = $GLOBALS['SYSTEM']['data_base'] . '/' . $resource['key'];
        if (!is_dir($path)) {
            continue;
        }
        $ts = (int)find_latest_time($path);
        if ($ts > $latest) {
            $latest = $ts;
            $latest_label = $resource['name'];
        }
    }
    return [$latest, $latest_label];
}

function dashboard_data_status_item(string $resource_label, int $last_update): string
{
    $ago = $last_update > 0 ? fmt_ago_short($last_update) : 'never';
    $sub = $resource_label !== '' ? htmlspecialchars($resource_label, ENT_QUOTES, 'UTF-8') . ' updated' : 'No records yet';

    return '<li class="min-w-[150px]">'
        . dashboard_status_heading('Data', 'stack', 'bg-blue-50 text-blue-500')
        . '<div class="text-xl font-semibold text-neutral-500">' . htmlspecialchars($ago, ENT_QUOTES, 'UTF-8') . '</div>'
        . '<div class="text-xs text-neutral-500">' . $sub . '</div>'
        . '</li>';
}

function dashboard_repo_status_item(string $label, int $last_update, string $count_var, string $pull_fn, string $icon, string $color): string
{
    $ago = $last_update > 0 ? fmt_ago_short($last_update) : 'never';

    return '<li class="min-w-[160px]">'
        . dashboard_status_heading($label, $icon, $color)
        . ' <div class="text-xl font-semibold text-neutral-400" x-cloak x-show="' . $count_var . ' === null">Checking…</div>'
        . ' <div class="text-xl font-semibold" x-cloak x-show="' . $count_var . ' !== null"'
        . ' :class="' . $count_var . ' > 0 ? \'text-amber-500\' : \'text-neutral-500\'"'
        . ' x-text="' . $count_var . ' > 0 ? (' . $count_var . ' + (' . $count_var . ' === 1 ? \' update\' : \' updates\')) : \'Up to date\'"></div>'
        . '<div class="text-xs text-neutral-500">Updated ' . htmlspecialchars($ago, ENT_QUOTES, 'UTF-8') . '</div>'
        . ' <button type="button" class="cursor-pointer text-xs font-medium text-neutral-600 underline decoration-neutral-300 hover:text-neutral-800 hover:decoration-neutral-500 disabled:cursor-not-allowed disabled:opacity-50" x-cloak x-show="' . $count_var . ' > 0" @click="' . $pull_fn . '" :disabled="busy">Update now</button>'
        . '</li>';
}

function dashboard_status_heading(string $label, string $icon, string $color): string
{
    return '<div class="mb-1 flex items-center gap-2">'
        . dashboard_badge($icon, $color)
        . '<span class="text-xs font-semibold uppercase tracking-wide text-neutral-700">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
        . '</div>';
}

function dashboard_badge(string $icon, string $color): string
{
    return '<span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full ' . $color . '">'
        . dashboard_icon($icon)
        . '</span>';
}

function dashboard_icon(string $name): string
{
    $path = match ($name) {
        'stack' => 'M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125',
        'cube' => 'M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9',
        'code-bracket' => 'M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5',
        'server' => 'M21.75 17.25v-.228a4.5 4.5 0 0 0-.12-1.03l-2.268-9.64a3.375 3.375 0 0 0-3.285-2.602H7.923a3.375 3.375 0 0 0-3.285 2.602l-2.268 9.64a4.5 4.5 0 0 0-.12 1.03v.228m19.5 0a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3m19.5 0a3 3 0 0 0-3-3H5.25a3 3 0 0 0-3 3m16.5 0h.008v.008h-.008v-.008Zm-3 0h.008v.008h-.008v-.008Z',
        'user' => 'M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
        'photo' => 'M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z',
        'bolt' => 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z',
        default => '',
    };
    return '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-3.5 w-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="' . $path . '" /></svg>';
}
