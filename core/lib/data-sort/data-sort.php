<?php

load_library("get");
load_library("set");
load_library("detect_language");


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
    if (strpos($sort_rules, '|') === false) {
        return data_sort_param_separator($data, $sort_rules);
    }
    $sorts = explode(",", $sort_rules);
    foreach ($sorts as $sort) {
        $data = data_sort_param_separator($data, $sort, '|');
    }
    return $data;
}

function data_sort_param_separator($data, $sort_csv, $sep = ',') {
    $parts = explode($sep, $sort_csv);
    $len = count($parts);
    if ($len < 2) {
        return $data;
    }
    $meta = array(
        "field" => $parts[0],
        "flags" => $len > 1? $parts[1] : 'default',
        "order" => $len > 2? $parts[2] : 'default'
    );
    return data_sort_meta($data, $meta);
}

function data_sort_meta($data, $meta) {
    if (empty($data)) {
        return $data;
    }
    switch (trim(strtolower($meta['flags']))) {
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
            function($a, $b) use ($key) { return _get_key($a, $key) ?? '' - _get_key($b, $key) ?? ''; }
        :   function($b, $a) use ($key) { return _get_key($a, $key) ?? '' - _get_key($b, $key) ?? ''; });
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
    static $result = false;
    if ($result) {
        return $result;
    }
    $v = $record[$field];
    if (is_array($v)) {
        $lang = detect_language_sc();
        if (isset($v[$lang]) && is_scalar($v[$lang])) {
            $v = $v[$lang];
        } else {
            $v = (string)current($v);
        }
    } 
    $result = $v;
    return $result;
}
