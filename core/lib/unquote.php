<?php

function unquote_sc($params) {
    load_library('get');
    $val = get_variable(current($params));
    if (is_string($val)) {
        $val = str_replace('"', "&quot;", $val);
        $val = str_replace("'", "&apos;", $val);
    } else if (is_object($val) || is_array($val)) {
        $val = '(object)';
    }
    return $val;
}