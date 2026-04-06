<?php

load_library('text');

function resource_name_sc($params) {
    $r = trim(strtolower(current($params)), '. ');
    if (!isset($params['plural']) && substr($r, -1) === 's' && $r !== 'news') {
        $r = substr($r, 0, -1);
        if (strlen($r) > 2 && substr($r, -2) === 'ie') {
            $r = substr($r, 0, -2) . 'y';
        }
    }
    return t(ucfirst($r));
}
