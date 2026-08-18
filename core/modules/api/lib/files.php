<?php

function files_sc($params)
{
    load_library("api");
    api_method_switch("files");
}

function files_get()
{ // get all files (list)
    $files = data_read(".files_meta");
    return json_result(array('files' => $files, 'count' => count($files)), 200);
}

/**
 * `.files_meta` starts out with no declared schema (`fields: false`,
 * auto-created lazily by data_meta()). This ensures it has a real, i18n-
 * capable `title`/`description` so per-language captions can be set from
 * the media side panel. Idempotent: no-ops once `title` is declared.
 * Existing scalar title/description values on individual records are left
 * as-is — they read through fine (get_i18n_resolve() no-ops on scalars)
 * and upgrade to the i18n shape lazily, the next time that record is saved.
 */
function files_meta_ensure_schema()
{
    $meta = data_meta('.files_meta');
    if (!empty($meta['fields']['title'])) {
        return;
    }
    data_update('.files_meta', '.meta', [
        'fields' => [
            'title' => ['type' => 'text', 'name' => 'Title', 'i18n' => true],
            'description' => ['type' => 'textarea', 'name' => 'Description', 'i18n' => true],
        ],
        'languages' => data_lookup('.config', 'site', 'languages', ['en']),
    ]);
    data_meta_invalidate('.files_meta');
}

function files_post()
{ // create a new file and it's meta data
    if (empty($_FILES) || empty($_FILES['file']['tmp_name'])) {
        load_library('util');
        if (isset($_SERVER['CONTENT_LENGTH']) &&
            (int) $_SERVER['CONTENT_LENGTH'] > max_upload_size()) {
            return json_result(['message' => 'UPLOAD_TOO_LARGE'], 413);
        }
        return json_result(['message' => 'BAD_REQUEST'], 400);
    }
    files_meta_ensure_schema();
    $from = $_FILES['file']['tmp_name'];
    $uuid = hash_file('md5', $from); // use checksum as uuid
    if (data_exists('.files', $uuid)) {
        $meta = data_read('.files_meta', $uuid);
        return json_result(array("files" => $meta, 'count' => 1, 'message' => 'RESOURCE_CREATED'), 201);
        //return json_result(array("files" => data_read(".files_meta", $uuid), 'count' => 1, 'message' => 'RESOURCE_EXISTS'), 409);
    }
    $dir = $GLOBALS['SYSTEM']['data_base'] . '/.files/';
    if (!file_exists($dir)) {
        @mkdir($dir, 0750, true);
    }
    $now = time();
    $meta = array(
        "name" => $_FILES['file']['name'],
        "uuid" => $uuid,
        "type" => $_FILES['file']['type'],
        "size" => $_FILES['file']['size'],
        "_created" => $now,
        "_modified" => $now,
        "_uploaded" => $now
    );
    if (exif_imagetype($from) === IMAGETYPE_JPEG) {
        load_library("exif");
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
    if (data_create('.files_meta', $uuid, $meta) && move_uploaded_file($from, $dir . $uuid) === true) {
        return json_result(array("files" => $meta, 'count' => 1, 'message' => 'RESOURCE_CREATED'), 201);
    }
    return json_result(array('message' => 'RESOURCE_CREATE_FAILED'), 500);
}

function files_delete()
{  // delete all files
    $delete_count = data_delete('.files_meta');
    if ($delete_count !== false) {
        data_delete('.files');
        load_library('util');
        _rmdirr($GLOBALS['SYSTEM']['file_base'] . 'ext/static/_thumb_/img');
        return json_result(array('message' => 'RESOURCE_DELETED', 'count' => (int)$delete_count));
    }
    return json_result(array('message' => 'RESOURCE_DELETE_FAILED'), 500);
}

/*
 *  Implementation on files item:
 */

function files_id_get($resource = ".files_meta", $uuid)
{ // read one
    return resource_id_get($resource, $uuid);
}

function files_id_put($resource = ".files_meta", $uuid)
{ // update one
    files_meta_ensure_schema();
    return resource_id_put($resource, $uuid);
}

function files_id_delete($resource = ".files_meta", $uuid)
{ // delete one
    if (data_delete($resource, $uuid)) {
        data_delete(".files", $uuid);
        load_library('util');
        _rmdirr($GLOBALS['SYSTEM']['file_base'] . 'ext/static/_thumb_/img/' . $uuid);
        return json_result(array('message' => 'FILE RESOURCE_DELETED', 'count' => 1));
    }
    return json_result(array('message' => 'FILE RESOURCE_DELETE_FAILED'), 500);
}
