<?php

function files_sc($params) {
    load_library("api", "api");
    api_method_switch("files", '.files');
}

function files_key($resource) {
    return $resource[0] === '.'? substr($resource, 1) : $resource;
}

function files_get($resource=".files") { // get all files (list)
    $files = data_read($resource . '_meta');
    return json_result([files_key($resource) => $files, 'count' => count($files)], 200);
}

function files_post($resource=".files") { // create a new file and it's meta data
    if (empty($_FILES)) {
        return json_result(array('message' => 'BAD_REQUEST'), 400);
    }
    $result = [];
    foreach ($_FILES as $key => $file_info) {
        $from = $file_info['tmp_name'];
        if (empty($from) || !empty($file_info['error'])) {
            return json_result(array('message' => 'BAD_REQUEST'), 400);
        }
        
        $uuid = hash_file('md5', $from); // use checksum as uuid
        if (data_exists($resource, $uuid)) {
            $meta = data_read($resource . '_meta', $uuid);
            $result[$key] = $meta;
            continue;
        }
        $dir = $GLOBALS['SYSTEM']['data_base'] . '/' . $resource . '/';
        if (!file_exists($dir)) {
            @mkdir($dir, 0750, true);
        }
        $meta = array(
            "name" => $file_info['name'],
            "uuid" => $uuid,
            "type" => $file_info['type'],
            "size" => $file_info['size']
        );
        if (exif_imagetype($from) === IMAGETYPE_JPEG) {
            load_library("exif", "media");
            $exif_data = exif_get($from);
            $meta = array_merge($meta, $exif_data);
        }
        if (exif_imagetype($from) !== false) {
            list($width, $height) = getimagesize($from);
            $meta['width'] = $width;
            $meta['height'] = $height;
            $meta['orientation'] = $width >= $height ? 'landscape' : 'portrait';
            $meta['aspect_ratio'] = $width / $height;
        }
        if (data_create($resource . '_meta', $uuid, $meta) && move_uploaded_file($from, $dir . $uuid) === true) {
            $result[$key] = $meta;
        }
    }
    if (empty($result)) {
        return json_result(['message' => 'RESOURCE_CREATE_FAILED'], 500);
    }
    return json_result([files_key($resource) => $result, 'count' => count($result), 'message' => 'RESOURCE_CREATED'], 201);
}

function files_delete($resource = '.files') {  // delete all files
    $delete_count = data_delete($resource . '_meta');
    if ($delete_count !== false) {
        data_delete($resource);
        return json_result(['message' => 'RESOURCE_DELETED', 'count' => (int)$delete_count]);
    }
    return json_result(['message' => 'RESOURCE_DELETE_FAILED'], 500);
}

/*
 *  Implementation on files item:
 */

function files_id_get($resource=".files", $uuid) { // read one
    return resource_id_get($resource . '_meta', $uuid);
}

function files_id_put($resource=".files", $uuid) { // update one
    return resource_id_put($resource . '_meta', $uuid);
}

function files_id_delete($resource=".files", $uuid) { // delete one
    if (data_delete($resource . '_meta', $uuid)) {
        data_delete($resource, $uuid);
        return json_result(array('message' => 'FILE RESOURCE_DELETED', 'count' => 1));
    }
    return json_result(array('message' => 'FILE RESOURCE_DELETE_FAILED'), 500);
}