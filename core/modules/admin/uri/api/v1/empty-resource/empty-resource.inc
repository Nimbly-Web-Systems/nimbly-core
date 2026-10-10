<?php

load_libraries(['get', 'data', 'api']);

function empty_resource_sc()
{
    $response['error'] = true;
    $response['count'] = 0;
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        $response['msg'] = "use POST";
        return json_result($response);
    }
    $resource = get_variable('resource', null, 'url');
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
