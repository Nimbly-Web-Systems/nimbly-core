<?php

load_library("json");
load_library("data");
load_library("access");
load_library("get");
load_library('util');


function map_header($key, $first = false)
{
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

    $pk_field = get_pk_field($record);
    $pk_value = $pk_field && !empty($record[$pk_field])
        ? mb_strtolower(trim($record[$pk_field]), 'UTF-8')
        : null;

    if (!empty($record['uuid']) && data_exists($resource, $record['uuid'])) {
        if (data_update($resource, $record['uuid'], $record) !== false) {
            $import_results['imported']++;
            $import_results['updated']++;
        } else {
            $import_results['errors']++;
        }
    } else if (!empty($record['uuid'])) {
        if (data_create($resource, $record['uuid'], $record) !== false) {
            $import_results['imported']++;
            $import_results['created']++;
        } else {
            $import_results['errors']++;
        }
        if (!empty($pk_value) && data_exists($resource, $record['uuid'])) {
            $seen_pk_values[md5($pk_value)] = $record['uuid'];
        }
    } else if (!empty($pk_value) && !empty($seen_pk_values[md5($pk_value)])) {
        //duplicate row
        if (data_update($resource, $seen_pk_values[md5($pk_value)], $record) !== false) {
            $import_results['imported']++;
            $import_results['updated']++;
        } else {
            $import_results['errors']++;
        }
    } else {
        $uuid = record_exists_by_pk($resource, $record);
        if ($uuid === false) {
            $uuid = generate_uuid();
            if (data_create($resource, $uuid, $record) !== false) {
                $import_results['imported']++;
                $import_results['created']++;
            } else {
                $import_results['errors']++;
            }
            if (!empty($pk_value) && data_exists($resource, $uuid)) {
                $seen_pk_values[md5($pk_value)] = $uuid;
            }
        } else {
            if (data_update($resource, $uuid, $record) !== false) {
                $import_results['imported']++;
                $import_results['updated']++;
            } else {
                $import_results['errors']++;
            }
        }
    }
}

function api_import_resource_stats()
{
    return [
        'imported' => 0,
        'errors' => 0,
        'count' => 0,
        'created' => 0,
        'updated' => 0,
    ];
}

function api_import_resource_result($import_results)
{
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

function api_import_resource_array_is_list($data)
{
    $index = 0;
    foreach (array_keys($data) as $key) {
        if ($key !== $index) {
            return false;
        }
        $index++;
    }
    return true;
}

function api_import_resource_str_ends_with($haystack, $needle)
{
    if ($needle === '') {
        return true;
    }
    return substr($haystack, -strlen($needle)) === $needle;
}

function api_import_resource_json_records($resource, $data)
{
    if (isset($data[$resource]) && is_array($data[$resource])) {
        return $data[$resource];
    }
    if (isset($data['records']) && is_array($data['records'])) {
        return $data['records'];
    }
    if (api_import_resource_array_is_list($data)) {
        return $data;
    }

    $records = [];
    foreach ($data as $key => $value) {
        if (!is_array($value)) {
            return false;
        }
        $records[$key] = $value;
    }
    return $records;
}

function api_import_resource_json($resource, $tmp_path)
{
    $data = json_decode(file_get_contents($tmp_path), true);
    if (!is_array($data)) {
        return json_result(['message' => 'INVALID_DATA'], 400);
    }

    if (!data_exists($resource)) {
        $meta = isset($data['_meta']) && is_array($data['_meta']) ? $data['_meta'] : ['fields' => false];
        if (data_create_resource($resource, $meta) !== true) {
            return json_result(['message' => 'RESOURCE_CREATE_FAILED'], 500);
        }
    }

    $records = api_import_resource_json_records($resource, $data);
    if (!is_array($records)) {
        return json_result(['message' => 'INVALID_DATA'], 400);
    }

    $import_results = api_import_resource_stats();

    foreach ($records as $key => $record) {
        $import_results['count']++;
        if (!is_array($record)) {
            $import_results['errors']++;
            continue;
        }

        $uuid = $record['uuid'] ?? (!is_int($key) ? $key : null);
        if (empty($uuid)) {
            load_library('util');
            $uuid = generate_uuid();
        }
        $record['uuid'] = $uuid;

        $exists = data_exists($resource, $uuid);
        $result = $exists
            ? data_update($resource, $uuid, $record)
            : data_create($resource, $uuid, $record);

        if ($result === false) {
            $import_results['errors']++;
            continue;
        }

        $import_results['imported']++;
        if ($exists) {
            $import_results['updated']++;
        } else {
            $import_results['created']++;
        }
    }

    _data_clear_cache('_data_read_all', $resource);
    return api_import_resource_result($import_results);
}

function api_import_resource_is_json($file, $tmp_path, $mime)
{
    $name = strtolower($file['name'] ?? '');
    if (api_import_resource_str_ends_with($name, '.json') || $mime === 'application/json') {
        return true;
    }

    $fh = fopen($tmp_path, 'r');
    if (!$fh) {
        return false;
    }
    $first = '';
    while (($char = fgetc($fh)) !== false) {
        if (trim($char) === '') {
            continue;
        }
        $first = $char;
        break;
    }
    fclose($fh);
    return $first === '{' || $first === '[';
}

function api_import_resource_delimiter($file, $mime)
{
    $name = strtolower($file['name'] ?? '');
    if (api_import_resource_str_ends_with($name, '.csv') || $mime === 'text/csv') {
        return ',';
    }
    return "\t";
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
    $allowed_mimes = ['application/json', 'application/vnd.ms-excel', 'text/plain', 'text/csv', 'text/tsv'];
    if (!in_array($mime, $allowed_mimes)) {
        return json_result(['message' => 'INVALID_DATA'], 400);
    }

    if (api_import_resource_is_json($file, $tmp_path, $mime)) {
        return api_import_resource_json($resource, $tmp_path);
    }

    $delimiter = api_import_resource_delimiter($file, $mime);
    $fh = fopen($tmp_path, 'r');
    if (!$fh) {
        return json_result(['message' => 'INVALID_DATA'], 400);
    }

    $raw_headers = fgetcsv($fh, 0, $delimiter);
    $headers = sanitize_headers($raw_headers);

    if (count($headers) < 1) {
        fclose($fh);
        return json_result([
            'error' => 'Unexpected file format',
            'recognized_columns' => count($headers),
            'total_columns' => count($raw_headers)
        ], 400);
    }

    $import_results = api_import_resource_stats();

    $seen_pk_values = []; // key => uuid

    set_time_limit(0);

    while (($line = fgetcsv($fh, 0, $delimiter)) !== false) {
        $import_results['count']++;
        if (count($line) !== count($raw_headers)) {
            $import_results['errors']++;
            continue;
        }
        import_row($resource, $headers, $line, $import_results, $seen_pk_values);
    }

    fclose($fh);

    _data_clear_cache('_data_read_all', $resource);

    return api_import_resource_result($import_results);
}
