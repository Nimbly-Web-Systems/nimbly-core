<?php

load_library('text');

function resource_name_sc($params) {
    $resource = current($params);
    $plural = isset($params['plural']);
    $meta = data_meta($resource);
    $configured_name = $meta['name'][$plural ? 'plural' : 'singular'] ?? null;
    if (is_string($configured_name) && $configured_name !== '') {
        return t($configured_name);
    }

    $r = trim(strtolower($resource), '. ');
    if (!$plural && substr($r, -1) === 's' && $r !== 'news') {
        $r = substr($r, 0, -1);
        if (strlen($r) > 2 && substr($r, -2) === 'ie') {
            $r = substr($r, 0, -2) . 'y';
        }
    }
    return t(ucfirst($r));
}
