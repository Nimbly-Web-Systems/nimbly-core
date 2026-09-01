<?php

load_library('detect-language');

function get_resource_records_sc($params)
{
    load_library('fmt');
    load_library('text');
    $resource = get_param_value($params, 'resource', current($params));
    $role = get_param_value($params, 'role', end($params));
    if (empty($resource)) {
        load_library('get');
        $resource = get_variable('resource-name', '(unknown)');
    }

    if (!data_exists($resource)) {
        return;
    }

    $meta = data_meta($resource);

    // Legacy/special resources (e.g. `roles`) may define no field schema at
    // all (`fields: false`). Fall back to system columns only instead of
    // showing zero records, which otherwise contradicts counts shown
    // elsewhere (e.g. the dashboard's own resource counts).
    if (!is_array($meta['fields'] ?? null)) {
        $meta['fields'] = [];
    }

    $raw_records = data_read($resource);

    if (!empty($raw_records) && isset($meta['sort'])) {
        load_library('data-sort');
        $raw_records = data_sort_meta($raw_records, $meta['sort']);
    }

    $fields = _prep_fields($meta);
    $filter_fields = _prep_filter_fields($meta);
    $resource_maps = _build_resource_maps(array_merge($fields, $filter_fields));
    $filters = _prep_filters($filter_fields, $resource_maps);

    $data_records = [];

    foreach ($raw_records as $ix => $record) {
        $data_records[$ix] = _prep_record($record, $fields, $resource_maps);
        $data_records[$ix]['_filter_values'] = _prep_filter_values($record, $filter_fields);
        if (!empty($meta['actions']['url'])) {
            $data_records[$ix]['_action_url'] = _prep_action_url($meta['actions']['url'], $record, $ix);
        }
        if (!empty($meta['actions']['view_url'])) {
            $data_records[$ix]['_view_url'] = _prep_action_url($meta['actions']['view_url'], $record, $ix);
        }
    }
    set_variable('data.fields', $fields);
    set_variable('data.filters', $filters);
    set_variable('data.records', $data_records);
    set_variable('data.sort', $meta['sort'] ?? []);
}

function _prep_filter_fields($meta)
{
    $result = [];
    foreach (($meta['fields'] ?? []) as $field_id => $field) {
        if (empty($field['type']) || !in_array($field['type'], ['select', 'boolean'], true)) {
            continue;
        }
        if (!array_key_exists('admin_filter', $field) || $field['admin_filter'] === false) {
            continue;
        }
        if (empty($field['name'])) {
            $field['name'] = $field_id;
        }
        $result[$field_id] = $field;
    }
    return $result;
}

function _prep_filters($fields, $resource_maps = [])
{
    $result = [];
    foreach ($fields as $field_id => $field) {
        if (($field['type'] ?? '') === 'boolean') {
            $options = ['1' => t('Yes'), '0' => t('No')];
        } elseif (!empty($field['resource'])) {
            $options = $resource_maps[$field['resource']] ?? [];
        } else {
            $options = $field['options'] ?? [];
        }

        $filter = [
            'name' => $field['name'],
            'options' => $options,
        ];
        if (is_array($field['admin_filter']) && array_key_exists('default', $field['admin_filter'])) {
            $filter['default'] = _admin_filter_value($field['admin_filter']['default'], $field['type']);
        }
        $result[$field_id] = $filter;
    }
    return $result;
}

function _prep_filter_values($record, $fields)
{
    $result = [];
    foreach ($fields as $field_id => $field) {
        $value = $record[$field_id] ?? '';
        if (is_array($value)) {
            $result[$field_id] = array_map(
                fn($item) => _admin_filter_value($item, $field['type']),
                $value
            );
            continue;
        }
        $result[$field_id] = _admin_filter_value($value, $field['type']);
    }
    return $result;
}

function _admin_filter_value($value, $type)
{
    if ($type === 'boolean') {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
    }
    if (is_scalar($value)) {
        return (string)$value;
    }
    return '';
}

function _prep_action_url($url, $record, $uuid)
{
    if (!is_array($record)) {
        $record = [];
    }
    $record['uuid'] = $record['uuid'] ?? $uuid;

    return preg_replace_callback('/\[#record\.([a-zA-Z0-9_-]+)#\]/', function ($matches) use ($record) {
        return $record[$matches[1]] ?? '';
    }, $url);
}

/**
 * Load each related resource referenced by a select+resource field once,
 * keyed by UUID to display name.
 */
function _build_resource_maps($fields)
{
    $maps = [];
    foreach ($fields as $v) {
        if (($v['type'] ?? '') !== 'select' || empty($v['resource'])) {
            continue;
        }
        $resource = $v['resource'];
        if (isset($maps[$resource])) {
            continue;
        }
        if (!data_exists($resource)) {
            $maps[$resource] = [];
            continue;
        }
        $display = $v['display_field'] ?? 'name';
        $map = [];
        foreach (data_read($resource) as $uuid => $rec) {
            $map[$uuid] = $rec[$display] ?? $rec['name'] ?? $rec['title'] ?? $uuid;
        }
        $maps[$resource] = $map;
    }
    return $maps;
}

/**
 * Prepare record for frontend display:
 * - format the value (web safe, shortened, proper reference values)
 * - resolve select values to their configured display labels
 */
function _prep_record($record, $fields, $resource_maps = [])
{
    $result = [];
    foreach ($fields as $k => $v) {
        $val = $record[$k] ?? '';
        if (($v['type'] ?? '') === 'select') {
            $map = !empty($v['resource'])
                ? ($resource_maps[$v['resource']] ?? [])
                : ($v['options'] ?? []);
            if (is_array($val) && empty($v['i18n'])) {
                $val = array_map(function($item) use ($map) {
                    if (is_string($item) && $item !== '') {
                        return $map[$item] ?? $item;
                    }
                    return $item;
                }, $val);
            } elseif (is_string($val) && $val !== '') {
                $val = $map[$val] ?? $val;
            }
        }
        if (is_array($val) && empty($v['i18n'])) {
            $val = implode(', ', array_filter(array_map(function($item) {
                if (is_array($item)) {
                    return $item['date'] ?? $item['name'] ?? $item['title'] ?? '';
                }
                return (string)$item;
            }, $val), fn($v) => $v !== ''));
        }
        $fmt_params = [
            'val' => $val,
            'type' => $v['type'],
            'max_length' => $v['max_length'] ?? 32,
            'empty' => '',
        ];
        if (($v['type'] ?? '') === 'boolean') {
            $fmt_params['boolean'] = t('Yes') . '|' . t('No');
            $fmt_params['style'] = 'badge';
        }
        if (!empty($v['fmt'])) {
            $fmt_params['fmt'] = $v['fmt'];
        }
        $result[$k] = fmt_sc($fmt_params);
    }
    return $result;
}

/**
 * Prepare fields for frontend display:
 * - remove any field that has admin_col set to false
 */
function _prep_fields($meta)
{
    $fields = $meta['fields'] ?? [];
    $result = [];
    $visible_fields = [];

    foreach ($fields as $k => $v) {
        if ((isset($v['admin_col']) && $v['admin_col'] === false) || empty($v['type'])) {
            continue;
        }
        if (empty($v['name'])) {
            $v['name'] = $k;
        }
        $visible_fields[$k] = $v;
    }

    $system_fields = _admin_system_fields();
    $admin_columns = $meta['admin_columns'] ?? null;

    if (is_array($admin_columns) && !empty($admin_columns)) {
        foreach ($admin_columns as $field_id) {
            if (isset($visible_fields[$field_id])) {
                $result[$field_id] = $visible_fields[$field_id];
                continue;
            }
            if (isset($system_fields[$field_id])) {
                $result[$field_id] = $system_fields[$field_id];
            }
        }
        return $result;
    }

    $result = $visible_fields;
    foreach (['_modified', '_created'] as $field_id) {
        $result[$field_id] = $system_fields[$field_id];
    }
    return $result;
}

function _admin_system_fields()
{
    return [
        '_modified' => [
            'name' => t('Modified'),
            'type' => 'date',
            'fmt' => 'Y-m-d H:i',
        ],
        '_created' => [
            'name' => t('Created'),
            'type' => 'date',
            'fmt' => 'Y-m-d H:i',
        ],
    ];
}
