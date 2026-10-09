<?php

load_library('data');
load_library('history');
load_library('set');
load_library('get');
load_library('fmt');
load_library('access');

/**
 * On a record's history page the changes of that record, each with a restore
 * button; with `resource` the latest changes of the whole resource.
 */
function record_history_sc($params)
{
    $resource = (string)get_variable('resource-id', '');
    if ($resource === '' || !history_enabled($resource)) {
        return '';
    }
    $fields = data_meta($resource)['fields'] ?? [];
    $rows = '';
    // Removing for good is a delete: only for who may delete records of this resource
    $may_forget = access_by_feature('delete-' . $resource);
    $here = dirname(__FILE__);

    if (in_array('resource', (array)$params, true)) {
        set_variable('_rh.action', '[#base-url#]/nb-admin/' . $resource . '/history');
        foreach (history_resource_list($resource) as $change) {
            record_history_row_set($resource, $change['uuid'], $change, $fields);
            set_variable('_row.deleted', $change['deleted'] ? '1' : '');
            set_variable('_row.search', htmlspecialchars(mb_strtolower(implode(' ', [
                get_variable('_row.title', ''), get_variable('_row.who', ''), get_variable('_row.changed', ''), get_variable('_row.label', ''),
            ])), ENT_QUOTES, 'UTF-8', false));
            set_variable('_row.button', is_array($change['record'] ?? null) ? run_buffered($here . '/resource-restore-button.tpl') : '');
            set_variable('_row.forget', $may_forget && $change['deleted'] ? run_buffered($here . '/forget-button.tpl') : '');
            $rows .= run_buffered($here . '/resource-row.tpl');
            clear_variable_dot('_row');
        }
        set_variable('_rh.rows', $rows);
        set_variable('_rh.empty', $rows === '' ? run_buffered($here . '/empty-row.tpl') : '');
        set_variable('_rh.forget', $may_forget ? run_buffered($here . '/forget-form.tpl') : '');
        set_variable('_rh.clear', $may_forget ? run_buffered($here . '/empty-button.tpl') : '');
        return run_buffered($here . '/resource.tpl');
    }

    $uuid = (string)get_variable('uuid', '');
    foreach (history_list($resource, $uuid) as $change) {
        record_history_row_set($resource, $uuid, $change, $fields);
        $rows .= run_buffered(dirname(__FILE__) . '/row.tpl');
        clear_variable_dot('_row');
    }
    set_variable('_rh.back', run_buffered(dirname(__FILE__) . (data_exists($resource, $uuid) ? '/record-link.tpl' : '/deleted-link.tpl')));
    set_variable('_rh.rows', $rows);
    set_variable('_rh.action', '[#base-url#]/nb-admin/' . $resource . '/' . htmlspecialchars($uuid, ENT_QUOTES, 'UTF-8') . '/history');
    set_variable('_rh.forget', $may_forget && $rows !== '' ? run_buffered($here . '/forget-form.tpl') : '');
    set_variable('_rh.clear', $may_forget && $rows !== '' ? run_buffered($here . '/clear-button.tpl') : '');
    set_variable('_rh.empty', $rows === '' ? run_buffered($here . '/record-empty-row.tpl') : '');
    return run_buffered($here . '/record.tpl');
}

/** The history block of the record action panel: the latest changes and a link to all of them. */
function record_history_panel($resource, $uuid)
{
    if ($resource === '' || $uuid === '' || !history_enabled($resource) || !access_by_feature('edit-' . $resource)) {
        return '';
    }
    $fields = data_meta($resource)['fields'] ?? [];
    $rows = '';
    foreach (array_slice(history_list($resource, $uuid), 0, 5) as $change) {
        record_history_row_set($resource, $uuid, $change, $fields);
        $rows .= run_buffered(dirname(__FILE__) . '/panel-row.tpl');
        clear_variable_dot('_row');
    }
    set_variable('_rh.rows', $rows);
    set_variable('_rh.uuid', htmlspecialchars($uuid, ENT_QUOTES, 'UTF-8'));
    return run_buffered(dirname(__FILE__) . ($rows === '' ? '/panel-empty.tpl' : '/panel.tpl'));
}

function record_history_row_set($resource, $uuid, array $change, $fields)
{
    $escape = fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    $labels = [];
    foreach ((array)($change['changed'] ?? []) as $field) {
        $labels[] = (string)($fields[$field]['label'] ?? $fields[$field]['name'] ?? $field);
    }
    $at = (int)($change['at'] ?? 0);
    set_variable_dot('_row', [
        'id' => $escape($change['id'] ?? ''),
        'uuid' => $escape($uuid),
        'when' => $escape(ago($at)),
        'date' => $escape(date('Y-m-d H:i', $at)),
        'who' => $escape(record_history_who((string)($change['by'] ?? ''))),
        'changed' => $escape(implode(', ', $labels)),
        'title' => $escape(record_history_title($resource, $uuid, $change['record'] ?? (data_exists($resource, $uuid) ? data_read($resource, $uuid) : null))),
        'label' => ['create' => 'Created', 'update' => 'Changed', 'delete' => 'Deleted'][$change['action'] ?? ''] ?? '',
    ]);
    set_variable('_row.badge', empty($change['run']) ? '' : run_buffered(dirname(__FILE__) . '/agent-badge.tpl'));
    set_variable('_row.button', is_array($change['record'] ?? null) ? run_buffered(dirname(__FILE__) . '/restore-button.tpl') : '');
}

function record_history_who($username)
{
    if ($username === '' || $username === 'anonymous') {
        return '';
    }
    load_library('util');
    $user = data_exists('users', md5_uuid($username)) ? data_read('users', md5_uuid($username)) : null;
    return (string)($user['name'] ?? '') !== '' ? (string)$user['name'] : $username;
}

/** A name for a version of a record: its first text field, else its uuid. */
function record_history_title($resource, $uuid, $record)
{
    foreach (['title', 'name', 'subject'] as $field) {
        $value = is_array($record) ? ($record[$field] ?? '') : '';
        $value = is_array($value) ? reset($value) : $value;
        if (is_string($value) && trim($value) !== '') {
            return trim(strip_tags($value));
        }
    }
    return $uuid;
}
