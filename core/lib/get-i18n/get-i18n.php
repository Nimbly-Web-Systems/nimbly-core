<?php

load_library('get');

function get_i18n_sc($params) {
    $v = get_sc($params);

    if (empty($v)) {
        return '';
    }

    $lang = (string)get_param_value($params, 'lang', end($params));
    if (empty($lang) || strlen($lang) !== 2) {
        load_library('detect_language');
        $lang = detect_language_sc();
    }
    if (isset($v[$lang])) {
        $v = $v[$lang];
    }
    $v = str_replace('"', "&quot;", $v);
    $v = str_replace("'", "&apos;", $v);
    return $v;
}