<?php

load_library('data');
load_library('history');
load_library('set');
load_library('get');
load_library('fmt');

/** The changes of one record, or the deleted records of a resource, each with a restore button. */
function record_history_sc($params)
{
    $resource = (string)get_variable('resource-id', '');
    $uuid = (string)get_variable('history-record', '');
    if ($resource === '' || !history_enabled($resource)) {
        return '';
    }
    $fields = data_meta($resource)['fields'] ?? [];
    $rows = '';

    if ($uuid === '') {
        foreach (history_deleted($resource) as $deleted_uuid => $change) {
            record_history_row_set($resource, $deleted_uuid, $change, $fields);
            $rows .= run_buffered(dirname(__FILE__) . '/deleted-row.tpl');
            clear_variable_dot('_row');
        }
        set_variable('_rh.rows', $rows);
        return run_buffered(dirname(__FILE__) . ($rows === '' ? '/deleted-empty.tpl' : '/deleted.tpl'));
    }

    foreach (history_list($resource, $uuid) as $change) {
        record_history_row_set($resource, $uuid, $change, $fields);
        $rows .= run_buffered(dirname(__FILE__) . '/row.tpl');
        clear_variable_dot('_row');
    }
    set_variable('_rh.back', run_buffered(dirname(__FILE__) . (data_exists($resource, $uuid) ? '/record-link.tpl' : '/deleted-link.tpl')));
    set_variable('_rh.rows', $rows);
    return run_buffered(dirname(__FILE__) . '/record.tpl');
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
        'title' => $escape(record_history_title($resource, $uuid, $change['record'] ?? null)),
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
