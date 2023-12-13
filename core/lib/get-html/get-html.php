<?php

load_library('lookup');
load_library('util');
function get_html_sc($params)
{
    $resource_set = dot2rs(current($params));
    if (!$resource_set) {
        return;
    }
    $resource = $resource_set[0];
    $uuid = $resource_set[1];
    $field = $resource_set[2];

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
    $result = strip_tags($html, "<h1><h2><h3><h4><h5><h6><b><strong><a><i><p><blockquote><ol><ul><li><br><img><iframe><figure><video><source>");
    echo $result;
}
