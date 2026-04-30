<?php

load_library('get');
load_library('util');

function get_i18n_sc($params)
{
    $v = get_sc($params);

    if (empty($v)) {
        return '';
    }

    if (!is_array($v) && strlen($v) === 2) {
        return '';
    }

    $lang = (string)get_param_value($params, 'lang', end($params));

    if (empty($lang) || strlen($lang) !== 2) {
        $lang = "auto";
    }

    $v = resolve_i18n($v, $lang);
    $v = str_replace('"', "&quot;", $v);
    $v = str_replace("'", "&apos;", $v);
    return $v;
}
