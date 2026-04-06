<?php 

function get_resource_meta_sc($params) {
    $resource = get_param_value($params, 'resource', current($params));
    if (empty($resource)) {
        load_library('get');
        $resource = get_variable('resource-name', '(unkown)');
    }

    if (!data_exists($resource)) {
        return;
    }

    $meta = data_meta($resource);

    load_library("set");
    set_variable("data.resource", $resource);
    set_variable("data.fields", $meta['fields']);
    set_variables("data.field.", $meta['fields']);
}