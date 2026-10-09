<?php

/**
 * The site as the Nimbly agent sees it: resources, records and admin pages, always through
 * the rights of the colleague who asked. Everything under the site's data is in scope; code
 * (templates, routes, libraries) is work for a developer.
 */

/** The person a chat turn answers, with the features their roles give them right now. */
function agent_site_asker(array $context): array
{
    load_libraries(['data', 'access', 'permissions']);
    $conversation = data_read('.agent_conversations', (string)($context['run']['event_context']['conversation'] ?? ''));
    foreach (array_reverse((array)($conversation['messages'] ?? [])) as $message) {
        if (in_array((string)$context['run_uuid'], (array)($message['runs'] ?? []), true)) {
            $username = (string)($message['asker'] ?? '');
            return ['username' => $username, 'features' => $username === '' ? [] : user_feature_map($username)];
        }
    }
    return ['username' => '', 'features' => []];
}

/** Deployed Git history only; it cannot establish that a reported problem is resolved. */
function agent_site_changes(array $asker, mixed $days = 7): array
{
    if (!agent_site_can($asker, 'chat-nimbly')) {
        return ['error' => 'This colleague may not consult site changes.'];
    }
    if (!is_int($days) || $days < 1 || $days > 90) {
        return ['error' => 'Choose a whole number of days from 1 to 90.'];
    }
    $base = rtrim(agent_base_dir(), '/');
    $result = ['days' => $days, 'note' => 'Commits describe deployed code, not proof of resolution. Verify the reported behavior.'];
    foreach (['core' => $base, 'ext' => $base . '/ext'] as $name => $path) {
        try {
            if (!file_exists($path . '/.git')) {
                throw new RuntimeException('Deployed Git metadata is unavailable.');
            }
            $head = agent_site_git($path, ['show', '-s', '--format=%H%x1f%cI%x1f%s', 'HEAD']);
            $log = agent_site_git($path, ['log', 'HEAD', '--since=' . gmdate('c', time() - $days * 86400),
                '-n', '81', '--format=%H%x1f%cI%x1f%s']);
            $parse = function (string $line): array {
                [$hash, $date, $subject] = explode("\x1f", $line, 3);
                return ['hash' => $hash, 'date' => $date, 'subject' => $subject];
            };
            $commits = $log === '' ? [] : array_map($parse, explode("\n", $log));
            $result[$name] = ['deployed' => $parse($head), 'commits' => array_slice($commits, 0, 80),
                'truncated' => count($commits) > 80];
        } catch (Throwable) {
            $result[$name] = ['error' => 'Deployed Git history is unavailable.'];
        }
    }
    return $result;
}

/** This site's own code under ext/, read-only: a file list, a search, or one file. */
function agent_site_code(array $asker, string $action, string $query): array
{
    if (!agent_site_can($asker, 'chat-nimbly')) {
        return ['error' => 'This colleague may not consult site code.'];
    }
    $ext = rtrim(agent_base_dir(), '/') . '/ext/';
    if ($action === 'read') {
        $file = agent_site_code_path($ext, $query);
        if ($file === null || !is_file($file) || !agent_site_code_readable($file)) {
            return ['error' => 'No such file. List a folder (uri, tpl, modules, lib) to find it.'];
        }
        $text = (string)file_get_contents($file);
        return mb_strlen($text) <= 14000 ? ['file' => $query, 'content' => $text]
            : ['file' => $query, 'content' => mb_substr($text, 0, 14000), 'truncated' => 'File is long; search for the part you need.'];
    }
    if ($action !== 'list' && $action !== 'search') {
        return ['error' => 'Choose list, search or read.'];
    }
    if ($action === 'search' && $query === '') {
        return ['error' => 'Give a search term.'];
    }
    $folder = $action === 'list' ? $query : '';
    $files = agent_site_code_files($ext, $folder);
    if ($files === null) {
        return ['error' => 'No such folder. Code lives in uri, tpl, modules and lib.'];
    }
    if ($action === 'list') {
        return ['files' => array_slice($files, 0, 300), 'total' => count($files)];
    }
    $matches = [];
    foreach ($files as $path) {
        foreach (file($ext . $path, FILE_IGNORE_NEW_LINES) ?: [] as $number => $line) {
            if (stripos($line, $query) !== false) {
                $matches[] = $path . ':' . ($number + 1) . ': ' . mb_substr(trim($line), 0, 200);
            }
        }
    }
    return $matches === [] ? ['matches' => [], 'hint' => 'No match. Try a shorter term or a shortcode, route or function name.']
        : ['matches' => array_slice($matches, 0, 40), 'total' => count($matches)];
}

/** The real path of an ext/ code file or folder, or null when it lies outside uri, tpl, modules or lib. */
function agent_site_code_path(string $ext, string $path): ?string
{
    $real = realpath($ext . ltrim(preg_replace('#^/?ext/#', '', $path), '/'));
    if ($real === false) {
        return null;
    }
    foreach (['uri', 'tpl', 'modules', 'lib'] as $root) {
        $base = realpath($ext . $root);
        if ($base !== false && ($real === $base || str_starts_with($real, $base . '/'))) {
            return $real;
        }
    }
    return null;
}

function agent_site_code_readable(string $file): bool
{
    return !str_starts_with(basename($file), '.')
        && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['tpl', 'inc', 'php', 'js', 'css', 'md', 'json'], true);
}

/** Code files under one folder, or under all code folders, relative to ext/. */
function agent_site_code_files(string $ext, string $folder): ?array
{
    $ext_real = realpath($ext);
    $roots = $folder === '' ? array_map(fn($root) => $ext . $root, ['uri', 'tpl', 'modules', 'lib']) : [$ext . $folder];
    $files = [];
    foreach ($roots as $root) {
        $dir = agent_site_code_path($ext, substr($root, strlen($ext)));
        if ($dir === null || !is_dir($dir)) {
            if ($folder !== '') {
                return null;
            }
            continue;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && !str_contains($file->getPathname(), '/.') && agent_site_code_readable($file->getPathname())) {
                $files[] = substr($file->getPathname(), strlen($ext_real) + 1);
            }
        }
    }
    sort($files);
    return $files;
}

/** Fixed read-only Git arguments; no credentials, network or working-tree changes. */
function agent_site_git(string $path, array $arguments): string
{
    $process = proc_open(['git', '-C', $path, ...$arguments],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'a']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Git is unavailable.');
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Git lookup failed.');
    }
    return trim((string)$output);
}

function agent_site_can(array $asker, string $feature): bool
{
    load_library('permissions');
    return permission_features_have($asker['features'], $feature);
}

function agent_site_managed_pages(): bool
{
    return !empty(data_read('.config', 'managed_pages')['enabled']);
}

/** Visitor statistics as the dashboard shows them, for those who may see them there. */
function agent_site_stats(array $asker, string $from, string $to): array
{
    if (!agent_site_can($asker, 'view-stats')) {
        return ['error' => 'This colleague may not see the visitor statistics.'];
    }
    load_library('stats');
    if (!stats_has_key()) {
        return ['error' => 'Visitor statistics are not set up on this site.'];
    }
    $date = fn($value) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? strtotime($value . 'T12:00:00Z') : false;
    $start = $date($from);
    $end = $date($to);
    if ($start === false || $end === false || $start > $end) {
        return ['error' => 'Give from and to as YYYY-MM-DD, from not after to.'];
    }
    $end = min($end, strtotime(gmdate('Y-m-d') . 'T12:00:00Z'));
    $count = (int)round(($end - $start) / 86400) + 1;
    if ($count > 400) {
        return ['error' => 'Ask for at most 400 days at a time.'];
    }
    $days = [];
    $total = [];
    foreach (stats_recent_days(max($count, 1), gmdate('Y-m-d', $end)) as $date => $day) {
        if (!is_array($day)) {
            continue;
        }
        $class = (array)($day['class'] ?? []);
        $days[$date] = ['pageviews' => (int)($day['pageviews'] ?? 0), 'visitors' => (int)($day['visitors'] ?? 0),
            'requests' => (int)($day['requests'] ?? 0), 'bots' => (int)($class['bot'] ?? 0) + (int)($class['tool'] ?? 0)];
        unset($day['hours'], $day['status'], $day['overflow']);
        stats_add_counts($total, stats_day_counts($day));
    }
    if ($days === []) {
        return ['days' => [], 'note' => 'No statistics were recorded in this period.'];
    }
    $views = array_filter(array_map(fn($path) => (int)($path['views'] ?? 0), (array)($total['paths'] ?? [])));
    arsort($views);
    $top = fn($counts, $limit = 15) => array_slice((function ($counts) { arsort($counts); return $counts; })((array)$counts), 0, $limit, true);
    return ['days' => $days, 'totals' => [
        'pageviews' => (int)($total['pageviews'] ?? 0), 'visitor_days' => (int)($total['visits'] ?? 0),
        'requests' => (int)($total['requests'] ?? 0), 'traffic' => (array)($total['class'] ?? []),
        'pages' => array_slice($views, 0, 30, true), 'referrers' => $top($total['referrers'] ?? []),
        'devices' => $top($total['device'] ?? []), 'browsers' => $top($total['browser'] ?? []),
        'languages' => $top($total['language'] ?? []), 'bots' => $top($total['bots'] ?? []),
    ], 'note' => 'Visitors are unique per day, so visitor_days over a period counts a returning visitor once per day.'];
}

/** Every resource under the site's data may be read and written, always through the asker's own rights. */
function agent_site_resource_in_scope(string $resource): bool
{
    return preg_match('/^\.?[a-z0-9][a-z0-9_-]*$/', $resource) === 1;
}

function agent_site_resources(array $asker): array
{
    $resources = array_keys(data_resources_list());
    foreach (@scandir((string)($GLOBALS['SYSTEM']['data_base'] ?? '')) ?: [] as $resource) {
        if ($resource[0] === '.' && is_dir(data_path($resource))) {
            $resources[] = $resource;
        }
    }
    return array_values(array_filter(array_unique($resources), fn($resource) => agent_site_resource_in_scope($resource)
        && data_exists($resource, '.meta') && agent_site_can($asker, 'view-' . $resource)));
}

/** Fields as the agent needs them to read and write records correctly. */
function agent_site_fields(array $fields): array
{
    $result = [];
    foreach ($fields as $name => $field) {
        if (!is_array($field)) {
            continue;
        }
        $result[$name] = array_filter([
            'label' => is_string($field['name'] ?? null) ? $field['name'] : $name,
            'type' => (string)($field['type'] ?? 'text'),
            'translated' => !empty($field['i18n']) ?: null,
            'required' => !empty($field['required']) ?: null,
            'options' => is_array($field['options'] ?? null) ? array_keys($field['options']) : null,
            'links_to' => $field['resource'] ?? null,
            'fields' => is_array($field['fields'] ?? null) ? agent_site_fields($field['fields']) : null,
        ], fn($value) => $value !== null);
    }
    return $result;
}

function agent_site_map(array $asker): array
{
    load_library('get');
    $site = data_read('.config', 'site') ?: [];
    $resources = [];
    foreach (agent_site_resources($asker) as $resource) {
        $meta = data_meta($resource) ?: [];
        $resources[$resource] = [
            'records' => count(data_list($resource) ?: []),
            'admin_page' => '/nb-admin/' . $resource,
            'you_may' => array_values(array_filter(['view', 'create', 'edit', 'delete'],
                fn($operation) => agent_site_can($asker, $operation . '-' . $resource))),
            'fields' => agent_site_fields((array)($meta['fields'] ?? [])),
        ];
    }
    $admin = ['/nb-admin' => 'Dashboard'];
    foreach (['edit-.config' => ['/nb-admin/settings' => 'Settings'], 'view-users' => ['/nb-admin/users' => 'Users'],
        'view-roles' => ['/nb-admin/roles' => 'Roles and permissions']] as $feature => $page) {
        if (agent_site_can($asker, $feature)) {
            $admin += $page;
        }
    }
    if (agent_site_managed_pages() && agent_site_can($asker, 'edit-.navigation')) {
        $admin['/nb-admin/navigation'] = 'Navigation';
    }
    return [
        'site' => ['name' => is_array($site['name'] ?? null) ? get_i18n_resolve($site['name'], 'auto') : (string)($site['name'] ?? 'Nimbly'),
            'languages' => (array)($site['languages'] ?? ['en'])],
        'custom_pages_enabled' => agent_site_managed_pages(),
        'admin_pages' => $admin,
        'resources' => $resources,
        'record_pages' => 'Open a record at /nb-admin/<resource>/<uuid> (edit) or add ?view=1 to only view it; add a new one at /nb-admin/<resource>/add.',
    ];
}

/** One record, or a short list of records (optionally matching a search, with chosen fields and sort). */
function agent_site_records(array $asker, string $resource, string $uuid = '', string $search = '', array $fields = [], string $sort = ''): array
{
    if (!agent_site_resource_in_scope($resource) || !agent_site_can($asker, 'view-' . $resource) || !data_exists($resource, '.meta')) {
        return ['error' => 'You cannot see this resource, or it does not exist.'];
    }
    if ($uuid !== '') {
        $record = data_read($resource, $uuid);
        return is_array($record)
            ? ['record' => agent_site_trim(agent_site_hide_secrets($resource, $record)), 'admin_page' => '/nb-admin/' . $resource . '/' . $uuid]
            : ['error' => 'No such record.'];
    }
    $records = array_map(fn($record) => agent_site_hide_secrets($resource, (array)$record), data_read($resource) ?: []);
    if ($search !== '') {
        $records = array_filter($records, fn($record) => stripos(json_encode($record, JSON_UNESCAPED_UNICODE) ?: '', $search) !== false);
    }
    $sort_field = ltrim($sort, '-');
    if ($sort_field !== '') {
        uasort($records, fn($a, $b) => ($sort[0] === '-' ? -1 : 1) * (($a[$sort_field] ?? '') <=> ($b[$sort_field] ?? '')));
    }
    $list = [];
    $limit = $fields ? 200 : 40;
    foreach (array_slice($records, 0, $limit, true) as $key => $record) {
        $summary = ['uuid' => (string)($record['uuid'] ?? $key)];
        foreach ($fields ?: array_keys($record) as $field) {
            $value = $record[$field] ?? null;
            if ($field !== 'uuid' && ($fields || ($field[0] !== '_' && count($summary) < 4)) && (is_scalar($value) || is_array($value))) {
                $summary[$field] = mb_substr(is_array($value) ? (string)json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value, 0, 120);
            }
        }
        $list[] = $summary;
    }
    return array_filter(['total' => count($records), 'records' => $list,
        'note' => count($records) > $limit ? 'Showing the first ' . $limit . '; narrow with search or sort.' : null], fn($value) => $value !== null);
}

/** Password hashes and their salts never reach the model. */
function agent_site_hide_secrets(string $resource, array $record): array
{
    $meta = data_meta($resource) ?: [];
    foreach (['encrypt', 'encrypt2way'] as $key) {
        foreach (array_filter(explode(',', (string)($meta[$key] ?? ''))) as $field) {
            unset($record[trim($field)]);
        }
    }
    if (!empty($meta['encrypt'])) {
        unset($record['salt']);
    }
    return $record;
}

function agent_site_trim(array $record): array
{
    $json = (string)json_encode($record, JSON_UNESCAPED_UNICODE);
    return strlen($json) <= 30000 ? $record : ['uuid' => $record['uuid'] ?? '', 'note' => 'Record too large to show whole.',
        'excerpt' => mb_substr($json, 0, 30000)];
}

/** Why the asker may not make this change, or null when they may. */
function agent_site_write_refusal(array $asker, string $action, string $resource, string $uuid): ?string
{
    $feature = ['create' => 'create-', 'update' => 'edit-', 'delete' => 'delete-'][$action] ?? null;
    if ($feature === null || !agent_site_resource_in_scope($resource) || !data_exists($resource, '.meta')) {
        return 'That is not something I can change here. Structure is developer work.';
    }
    $meta = data_meta($resource) ?: [];
    if (isset($meta['encrypt']) || isset($meta['encrypt2way'])) {
        return 'This resource holds protected values; change it in the admin yourself.';
    }
    if (!agent_site_can($asker, $feature . $resource)) {
        return 'You do not have the right to ' . $action . ' ' . $resource . ' records.';
    }
    if ($action !== 'create' && ($uuid === '' || !data_exists($resource, $uuid))) {
        return 'No such record.';
    }
    if ($action === 'create' && $uuid !== '' && data_exists($resource, $uuid)) {
        return 'A record with that uuid already exists.';
    }
    return null;
}

/**
 * Create, update or delete one record the way the admin and API do (validation, HTML sanitizing).
 * Translated fields merge per language, so sending {"nl": "..."} adds a translation.
 */
function agent_site_write(array $asker, string $action, string $resource, string $uuid, array $fields, string $run_uuid = ''): array
{
    // The change is the asker's, made in this run: both go into the record and its history.
    $GLOBALS['SYSTEM']['data_actor'] = ['username' => $asker['username'], 'run' => $run_uuid];
    try {
        return agent_site_write_record($asker, $action, $resource, $uuid, $fields);
    } finally {
        unset($GLOBALS['SYSTEM']['data_actor']);
    }
}

function agent_site_write_record(array $asker, string $action, string $resource, string $uuid, array $fields): array
{
    $refusal = agent_site_write_refusal($asker, $action, $resource, $uuid);
    if ($refusal !== null) {
        return ['status' => 'blocked', 'reason' => $refusal];
    }
    load_libraries(['util', 'html-sanitize']);
    if ($action === 'delete') {
        return data_delete($resource, $uuid) ? ['status' => 'done', 'deleted' => $uuid] : ['status' => 'failed'];
    }
    $meta = data_meta($resource) ?: [];
    $fields = array_filter($fields, fn($field) => is_string($field) && $field !== 'uuid' && $field[0] !== '_', ARRAY_FILTER_USE_KEY);
    $current = $action === 'update' ? (data_read($resource, $uuid) ?: []) : [];
    if ($resource === '.navigation' && !isset($fields['revision'])) {
        // The agent sends the whole menu it just read; the editor's revision check needs that version.
        $fields['revision'] = (string)($current['_revision'] ?? '');
    }
    foreach ($fields as $name => $value) {
        if (!empty($meta['fields'][$name]['i18n']) && is_array($value) && is_array($current[$name] ?? null)) {
            $fields[$name] = array_merge($current[$name], $value);
        }
    }
    $fields = sanitize_html_fields($meta, $fields);
    if ($action === 'create') {
        $uuid = $uuid !== '' ? $uuid : md5(generate_uuid());
        $saved = data_create($resource, $uuid, $fields + ['uuid' => $uuid, '_created_by' => md5_uuid($asker['username'])]);
    } else {
        $saved = data_update($resource, $uuid, $fields);
    }
    if (!$saved) {
        $error = (string)data_error_get();
        return ['status' => 'failed', 'reason' => $error === 'VALIDATION_FAILED'
            ? 'The values did not pass validation: ' . preg_replace('/[^a-zA-Z0-9_.:-]+/', '', (string)data_error_detail_get())
            : 'The record could not be saved.'];
    }
    return ['status' => 'done', 'uuid' => $uuid, 'admin_page' => '/nb-admin/' . $resource . '/' . $uuid];
}
