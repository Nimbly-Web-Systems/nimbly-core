<?php 

load_library('util');

function cfield_sc($params) {
    $rs = dot2rs(current($params));
    return $rs? implode('.', $rs) : '(empty)';
}