<?php

load_library("get");
load_library("set");

/**
 * @doc `[data-join a b]` joins data variables data.a and data.b into new array variable named data.join
 */
function data_join_sc($params) {
    if (count($params) < 2) {
        return;
    }
    $join = [];
    foreach ($params as $p) {
        $d = get_variable("data.{$p}");
        if (!is_array($d)) {
            return;
        }
        array_walk($d, 'data_join_set_resource_type', $p);
        $join = array_merge($join, $d);
    }
    set_variable('data.join', $join);
}

function data_join_set_resource_type(&$item, $key, $type) {
    $item['resource_type'] = $type;
}
