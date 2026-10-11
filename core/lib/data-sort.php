<?php

load_library("get");
load_library("set");
load_library("detect-language");


/**
 * @doc `[data-sort data.articles sort=title|string|asc]` sorts data.articles variable by field title, ascending
 */
function data_sort_sc($params) {
    if (count($params) < 2) {
        return;
    }
    $id = get_param_value($params, 'var', current($params));
    $sort_rules = get_param_value($params, 'sort', end($params));
    $data = get_variable($id);
    $data = data_sort_param($data, $sort_rules);
    set_variable($id, $data);
}

function data_sort_param($data, $sort_rules) {
    if (empty($data)) {
        return $data;
    }
    foreach (data_sort_rules($sort_rules) as $rule) {
        $data = data_sort_meta($data, $rule);
    }
    return $data;
}

/**
 * Reads the sort rules of a `sort=` parameter: `field|type|order` rules
 * separated by commas, or one rule written as `field,type,order`. A rule of
 * two parts that ends in `asc` or `desc` is a field and an order, compared
 * without a type.
 *
 * @return array A list of ['field' => , 'flags' => , 'order' => ].
 */
function data_sort_rules($sort_rules) {
    $sort_rules = (string)$sort_rules;
    $sep = strpos($sort_rules, '|') === false ? ',' : '|';
    $rules = [];
    foreach ($sep === ',' ? [$sort_rules] : explode(',', $sort_rules) as $rule) {
        $parts = explode($sep, $rule);
        if (count($parts) < 2) {
            continue;
        }
        if (count($parts) === 2 && in_array(trim(strtolower($parts[1])), ['asc', 'desc'], true)) {
            $parts = [$parts[0], 'default', $parts[1]];
        }
        $rules[] = ['field' => $parts[0], 'flags' => $parts[1], 'order' => $parts[2] ?? 'default'];
    }
    return $rules;
}

function data_sort_meta($data, $meta) {
    if (empty($data)) {
        return $data;
    }
    switch (trim(strtolower($meta['flags'] ?? 'text'))) {
        case 'num':
        case 'int':
        case 'numeric':
            $flags = SORT_NUMERIC;
            break;
        case 'text':
        case 'string':
            $flags = SORT_STRING;
            break;
        default:
            $flags = SORT_REGULAR;
    }
     switch (trim(strtolower($meta['order']))) {
        case 'desc':
            $order = SORT_DESC;
            break;
        default:
            $order = SORT_ASC;
    }
    return data_sort($data, $meta['field'], $flags, $order);
}

function data_sort($data, $key, $sort_flags = SORT_REGULAR, $sort_order = SORT_ASC) {
    if (empty($data)) {
        return $data;
    }
    switch ($sort_flags) {
        case SORT_REGULAR:
            return data_sort_regular($data, $key, $sort_order);
        case SORT_NUMERIC:
            return data_sort_numeric($data, $key, $sort_order);
        case SORT_STRING:
            return data_sort_string($data, $key, $sort_order);
        default:
            return $data;
    }
}

function data_sort_regular($data, $key, $sort_order = SORT_ASC) {
    uasort($data, $sort_order === SORT_ASC?
            function($a, $b) use ($key) { return (_get_key($a, $key) ?? '') <=> (_get_key($b, $key) ?? ''); }
        :   function($b, $a) use ($key) { return (_get_key($a, $key) ?? '') <=> (_get_key($b, $key) ?? ''); });
    return $data;
}

function data_sort_numeric($data, $key, $sort_order = SORT_ASC) {
    uasort($data,  $sort_order ===  SORT_ASC?
            function($a, $b) use ($key) { return floatval(_get_key($a, $key) ?? 0) - floatval(_get_key($b, $key) ?? 0); }
        :   function($b, $a) use ($key) { return floatval(_get_key($a, $key) ?? 0) - floatval(_get_key($b, $key) ?? 0); });
    return $data;
}

function data_sort_string($data, $key, $sort_order = SORT_ASC) {
    uasort($data, $sort_order ===  SORT_ASC?
            function($a, $b) use ($key) { return strcasecmp(_get_key($a, $key) ?? '', _get_key($b, $key) ?? ''); }
        :   function($b, $a) use ($key) { return strcasecmp(_get_key($a, $key) ?? '', _get_key($b, $key) ?? ''); });
    return $data;
}

function _get_key($record, $field) {
    static $cached_result = [];
    $v = $record[$field] ?? '-';

    if (is_array($v)) {
        $lang = detect_language_sc();
        if (isset($v[$lang]) && is_scalar($v[$lang])) {
            $v = $v[$lang];
        } else {
            $cur = current($v);
            $v = is_scalar($cur) ? (string)$cur : '';
        }
    }

    $record_id = is_array($record) && isset($record['uuid']) ? (string)$record['uuid'] : serialize($record);
    $hash = md5($record_id . $field . $v);
    if (isset($cached_result[$hash])) {
        return $cached_result[$hash];
    }
    $cached_result[$hash] = $v;
    return $v;
}
