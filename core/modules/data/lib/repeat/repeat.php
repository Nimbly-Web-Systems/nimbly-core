<?php

load_library('get');
load_library('set');

function repeat_sc($params) {
    $data_id = get_param_value($params, "data", current($params));
    $tpl_id = get_param_value($params, "tpl", $data_id);
    $tpl_dir = get_param_value($params, "tpldir");
    $tpl = find_template($tpl_id, $tpl_dir);
    if (empty($tpl)) {
        return;
    }
    
    $data = get_variable($data_id);
    
    if (get_param_value($params, "num", false) !== false) {
        $max = intval(get_param_value($params, "num", 0));
        $start = intval(get_param_value($params, "start", 1));
        $data = [];
        for ($i = $start; $i <= $max; $i++) {
            $data[$i] = $i;
        }
    } else if (!is_array($data) && is_string($data)) {
        if (get_param_value($params, "csv", false) !== false) {
            $data = array_map('trim', explode(',', $data));
        } else {
            $data = array($data);
        }
    } else if (!empty($data) && is_array($data)) {
        $filter = get_param_value($params, "filter");
        if (!empty($filter)) {
            $data = data_filter($data, $filter);
        }
    } else if (empty($data) && !is_array($data) && get_variable($data_id . '.0', false)) {
        $max = intval(get_param_value($params, "num", 100));
        $start = intval(get_param_value($params, "start", 0));
        $data = [];
        for ($i = $start; $i <= $max; $i++) {
            $k = $data_id . '.' . $i;
            $v = get_variable($k, false);
            if (empty($v)) {
                break;
            }
            $data[$i] = $v;
        }
    } 

    if (empty($data) || !is_array($data)) {
        $empty = get_param_value($params, "empty", "empty");
        $empty_tpl = find_template($empty, $tpl_dir);
        if (!empty($empty_tpl)) {
            run($empty_tpl);
        }
        return;
    }

    $exclude = get_param_value($params, "exclude");
    $exclude_ls = array();
    if (!empty($exclude)) {
        $exclude_ls = explode(",", $exclude);
    }

    $var_id = get_param_value($params, "var", "item");
    $limit = get_param_value($params, "limit", 0);
    $iterations = 0;
    $nodot = get_param_value($params, "nodot", false);
    foreach ($data as $k => $item) {
        $excluded = @in_array($item, $exclude_ls) || @in_array(key($item), $exclude_ls);
        if ($excluded) {
            continue;
        } else if (is_string($item)) {
            set_variable($var_id . '.key', $item);
        } else if (is_int($item)) {
            set_variable($var_id . '.key', $item);
        } else if ($nodot) {
            set_variable($var_id . '.key', $k);
            foreach ($item as $key => $value) {
                set_variable($var_id . '.' . $key, $value);
            }
        } else {
            set_variable($var_id . '.key', $k);
            foreach ($item as $key => $value) {
                set_variable_dot($var_id . '.' . $key, $value);
            }
        }

        run($tpl);
        clear_variable_dot($var_id);
        $iterations++;
        if (!empty($limit) && $iterations >= $limit) {
            set_variable('repeat.limit', 'yes');
            break;
        }
    }
}
