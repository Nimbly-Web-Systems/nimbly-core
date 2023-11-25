<?php

load_library('lookup');

function render_sc($params) {
    $resource_set = explode('.', current($params));
    $offset = 0;
    if (count($resource_set) === 4 && empty($resource_set[0])) {
        $offset = 1;
    }

    $resource = $resource_set[$offset + 0];
    $uuid = $resource_set[$offset + 1];
    $field = $resource_set[$offset + 2];

    if ($offset === 1) {
        $resource = '.' . $resource;
    }

    if (!data_exists($resource)) {
        data_create_resource($resource, ['fields' => false]);
    }

    if (!data_exists($resource, $uuid)) {
        data_create($resource, $uuid, []);
        $default = get_param_value($params, 'default', '');
        echo $default;
        return;
    }

    $html = lookup_data($resource, $uuid, $field, get_param_value('default', ''));
    $result = strip_tags($html, "<h1><h2><h3><h4><h5><h6><b><strong><a><i><p><blockquote><ol><ul><li><br><img><iframe>");
    echo $result;
}