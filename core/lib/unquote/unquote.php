<?php

function unquote_sc($params) {
    load_library('get');
    $text = get_variable(current($params));
    $text = str_replace('"', "&quot;", $text);
    return $text;
}