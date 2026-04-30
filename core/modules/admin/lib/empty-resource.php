<?php

load_libraries(['get', 'data', 'api']);

// @doc delete all records of a resource
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
        if ($resource === '.files') {
            data_empty('.files_meta');
        }
    }
    
    return json_result($response);
}
