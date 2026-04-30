<?php

function api_allow_sc($params) {
    if (count($params) < 2) {
        return;
    }
    $method = get_param_value($params, 'method', current($params));
    $resource = get_param_value($params, 'resource', next($params));
    
    if (empty($method) || empty($resource)) {
        return;
    }
    $uuid = get_param_value($params, 'uuid', next($params));
    load_library('set');
    set_variable('api.allow', sprintf('api_%s_%s', $method, $resource), ',');
    load_library('api');
    api_method_switch('resource', $resource, $uuid);
}