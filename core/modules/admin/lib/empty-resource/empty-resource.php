<?php

load_libraries(['get', 'data', 'api']);

// @doc pulls version from git repository
function empty_resource_sc()
{

    $response['error'] = true;
    $response['count'] = 0;
    $resource = get_variable('resource');
    if (empty($resource) || !data_exists($resource)) {
        $response['msg'] = "unknown resource";
    } else {
        $response['error'] = false;
        $response['msg'] = "deleted all records for: " . $resource;
        $response['count'] = data_empty($resource);
    }
    
    return json_result($response);
}
