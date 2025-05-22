<?php

load_library("json", "api");
load_library("data");
load_library("access", "user");
load_library("get");
load_library('uuid');


function map_header($key, $first = false)
{
    /*
    todo: get import mapping from resource .meta file
    for now, just assume the header names are equal to the field names

    static $map = array(
        'name' => 'name',
        'url' => 'link',
        'opportunities' => 'opportunities',
        'fields_of_work' => 'fields_of_work',
        'types_of_work' => 'types_of_work'
    );
    $mapkey = strtolower(str_replace([' ', '-', '&', '.', '\t'], '_', trim($key)));
    return $map[$mapkey] ?? false;*/
    return strtolower(str_replace([' ', '-', '&', '.', '\t'], '_', trim($key)));
}

function sanitize_headers($raw_headers)
{
    $result = [];
    foreach ($raw_headers as $ix => $raw_header) {
        $h = map_header($raw_header);
        if ($h === false) {
            continue;
        }
        $result[$ix] = $h;
    }
    return $result;
}

function map_field($key)
{
    static $map = array();
    return $map[$key] ?? 'string';
}

function parse_value($val, $type)
{
    if ($type === 'string') {
        return trim($val);
    }
    return '';
}

function get_pk_field($record)
{
    static $pk_field = null;

    if ($pk_field !== null) {
        return $pk_field;
    }

    //todo: get pk fields from .meta

    foreach (['uuid', 'id', 'name', 'title'] as $f) {
        if (!empty($record[$f])) {
            $pk_field = $f;
            break;
        }
    }

    return $pk_field;
}

function record_exists_by_pk($resource, $record)
{
    static $all_records = false;
    static $fields_to_try = ['uuid', 'id', 'name', 'title']; // TODO: get from .meta
    $pk_field = get_pk_field($record);

    if ($all_records === false) {
        $all_records = data_read($resource);
    }

    if (empty($pk_field) || empty($record[$pk_field])) {
        return false;
    }

    $pk_value = mb_strtolower(trim($record[$pk_field]), 'UTF-8');

    foreach ($all_records as $uuid => $r) {
        if (empty($r[$pk_field])) {
            continue;
        }
        if ($pk_value === mb_strtolower(trim($r[$pk_field]), 'UTF-8')) {
            return $uuid;
        }
    }

    return false;
}



function import_row($resource, $headers, $data, &$import_results, &$seen_pk_values)
{
    static $required_fields = null;

    if ($required_fields === null) {
        $meta = data_meta($resource);
        $required_fields = [];

        foreach ($meta['fields'] ?? [] as $field => $def) {
            if (!empty($def['required'])) {
                $required_fields[] = $field;
            }
        }
    }

    $record = [];
    foreach ($headers as $k => $h) {
        $value = parse_value($data[$k], map_field($h));
        $record[$h] = $value;
    }

    foreach ($required_fields as $req) {
        if (empty($record[$req])) {
            $import_results['errors']++;
            return;
        }
    }

    $import_results['imported']++;

    $pk_field = get_pk_field($record);
    $pk_value = $pk_field && !empty($record[$pk_field])
        ? mb_strtolower(trim($record[$pk_field]), 'UTF-8')
        : null;

    if (isset($record['uuid']) && data_exists($resource, $record['uuid'])) {
        data_update($resource, $record['uuid'], $record);
        $import_results['updated']++;
    } else if (!empty($pk_value) && !empty($seen_pk_values[md5($pk_value)])) {
        //duplicate row
        data_update($resource, $seen_pk_values[md5($pk_value)], $record);
         $import_results['updated']++;
    } else {
        $uuid = record_exists_by_pk($resource, $record);
        if ($uuid === false) {
            $uuid = uuid_sc();
            data_create($resource, $uuid, $record);
            $import_results['created']++;
            if (!empty($pk_value)) {
                $seen_pk_values[md5($pk_value)] = $uuid;
            }
        } else {
            data_update($resource, $uuid, $record);
            $import_results['updated']++;
        }
    }
}

function api_import_resource($resource)
{
    if (empty($_FILES)) {
        return json_result(['message' => 'INVALID_DATA'], 400);
    }

    $file = reset($_FILES);

    if (!is_uploaded_file($file['tmp_name'])) {
        return json_result(['message' => 'INVALID_DATA'], 400);
    }

    $tmp_path = $file['tmp_name'];
    $mime = mime_content_type($tmp_path);
    $allowed_mimes = ['application/vnd.ms-excel', 'text/plain', 'text/csv', 'text/tsv'];
    if (!in_array($mime, $allowed_mimes)) {
        return json_result(['message' => 'INVALID_DATA'], 400);
    }

    $fh = fopen($tmp_path, 'r');
    if (!$fh) {
        return json_result(['message' => 'INVALID_DATA'], 400);
    }

    $raw_headers = fgetcsv($fh, 0, "\t");
    $headers = sanitize_headers($raw_headers);

    if (count($headers) < 1) {
        fclose($fh);
        return json_result([
            'error' => 'Unexpected file format',
            'recognized_columns' => count($headers),
            'total_columns' => count($raw_headers)
        ], 400);
    }

    $import_results = [
        'imported' => 0,
        'errors' => 0,
        'count' => 0,
        'created' => 0,
        'updated' => 0,
    ];

    $seen_pk_values = []; // key => uuid

    set_time_limit(0);

    while (($line = fgetcsv($fh, 0, "\t")) !== false) {
        $import_results['count']++;
        if (count($line) !== count($raw_headers)) {
            $import_results['errors']++;
            continue;
        }
        import_row($resource, $headers, $line, $import_results, $seen_pk_values);
    }

    fclose($fh);

    _data_clear_cache('_data_read_all', $resource);

    return json_result([
        'stats' => [
            'imported' => $import_results['imported'],
            'errors' => $import_results['errors'],
            'created' => $import_results['created'],
            'updated' => $import_results['updated'],
            'total' => $import_results['count']
        ]
    ], 200);
}
