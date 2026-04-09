<?php

load_library('detect-language');

function get_resource_records_sc($params)
{
    load_library('fmt');
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

    if (empty($meta['fields']) || !is_array($meta['fields'])) {
        return;
    }

    $raw_records = data_read($resource);

    if (!empty($raw_records) && isset($meta['sort'])) {
        load_library('data-sort');
        $raw_records = data_sort_meta($raw_records, $meta['sort']);
    }

    $fields = _prep_fields($meta['fields']);

    $data_records = [];

    foreach ($raw_records as $ix => $record) {
        $data_records[$ix] = _prep_record($record, $fields);        
    }
    set_variable('data.fields', $fields);
    set_variable('data.records', $data_records);
}

/**
 * Prepare record for frontend display:
 * - format the value (web safe, shortened, proper reference values)
 */
function _prep_record($record, $fields)
{
    $result = [];
    foreach ($fields as $k => $v) {
        $val = $record[$k] ?? '';
        if (is_array($val)) {
            $val = implode(', ', $val);
        }
        $result[$k] = fmt_sc(['val' => $val, 'type' => $v['type'], 'max_length' => 32]);
    }
    return $result;
}

/**
 * Prepare fields for frontend display:
 * - remove any field that has admin_col set to false
 */
function _prep_fields($fields)
{
    $result = [];
    foreach ($fields as $k => $v) {
        if (isset($v['admin_col']) && $v['admin_col'] === false) {
            continue;
        }

        if (empty($v['type'])) {
            continue;
        }

        if (empty($v['name'])) {
            $v['name'] = $k;
        }

        $result[$k] = $v;
    }
    return $result;
}
