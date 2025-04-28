<?php

function get_i18n_sc($params) {
    $var = get_param_value($params, 'var', current($params));
    $lang = get_param_value($params, 'lang', next($params));
    $v = get_variable($var);
    if (empty($v)) {
        return '';
    }
    if (isset($v[$lang])) {
        $v = $v[$lang];
    }
    $v = str_replace('"', "&quot;", $v);
    $v = str_replace("'", "&apos;", $v);
    return $v;
}