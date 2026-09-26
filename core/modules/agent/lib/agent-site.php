<?php

/**
 * The site as the Nimbly agent sees it: resources, records and admin pages, always through
 * the rights of the colleague who asked. Structure (resource definitions, templates, routes,
 * users and roles) is out of scope; that is work for a developer.
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

function agent_site_can(array $asker, string $feature): bool
{
    load_library('permissions');
    return permission_features_have($asker['features'], $feature);
}

function agent_site_managed_pages(): bool
{
    return !empty(data_read('.config', 'managed_pages')['enabled']);
}

/** Resources the agent may read and write for anyone: the site's records, never its structure. */
function agent_site_resource_in_scope(string $resource): bool
{
    if (in_array($resource, ['users', 'roles'], true) || preg_match('/^\.?[a-z0-9][a-z0-9_-]*$/', $resource) !== 1) {
        return false;
    }
    if (in_array($resource, ['pages', '.navigation'], true)) {
        return agent_site_managed_pages();
    }
    return $resource === '.content' || $resource[0] !== '.';
}

function agent_site_resources(array $asker): array
{
    $resources = array_keys(data_resources_list());
    if (agent_site_managed_pages()) {
        $resources[] = '.navigation';
    }
    $resources[] = '.content';
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

/** One record, or a short list of records (optionally matching a search). */
function agent_site_records(array $asker, string $resource, string $uuid = '', string $search = ''): array
{
    if (!agent_site_resource_in_scope($resource) || !agent_site_can($asker, 'view-' . $resource) || !data_exists($resource, '.meta')) {
        return ['error' => 'You cannot see this resource, or it does not exist.'];
    }
    if ($uuid !== '') {
        $record = data_read($resource, $uuid);
        return is_array($record)
            ? ['record' => agent_site_trim($record), 'admin_page' => '/nb-admin/' . $resource . '/' . $uuid]
            : ['error' => 'No such record.'];
    }
    $records = data_read($resource) ?: [];
    if ($search !== '') {
        $records = array_filter($records, fn($record) => stripos(json_encode($record, JSON_UNESCAPED_UNICODE) ?: '', $search) !== false);
    }
    $list = [];
    foreach (array_slice($records, 0, 40, true) as $key => $record) {
        $summary = ['uuid' => (string)($record['uuid'] ?? $key)];
        foreach ($record as $field => $value) {
            if ($field !== 'uuid' && $field[0] !== '_' && count($summary) < 4 && (is_scalar($value) || is_array($value))) {
                $summary[$field] = mb_substr(is_array($value) ? (string)json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value, 0, 120);
            }
        }
        $list[] = $summary;
    }
    return ['total' => count($records), 'records' => $list];
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
function agent_site_write(array $asker, string $action, string $resource, string $uuid, array $fields): array
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
