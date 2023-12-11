<?php

load_library('data');

/**
 * @doc `[data-count resource]` counts amount of records in a resource
 */
function data_count_sc($params) {
    if (count($params) < 1) {
        return;
    }
    echo count(data_list(current($params)));
}