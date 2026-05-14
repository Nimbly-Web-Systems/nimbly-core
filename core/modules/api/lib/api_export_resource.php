<?php

load_library("data");
load_library("json");

function api_export_resource_filename($resource, $format)
{
    $safe_resource = preg_replace('/[^a-zA-Z0-9_.-]+/', '-', $resource);
    $date = gmdate('Y-m-d');
    return sprintf('%s-%s.%s', trim($safe_resource, '-'), $date, $format);
}

function api_export_resource_headers($resource, $format)
{
    $filename = api_export_resource_filename($resource, $format);
    $types = [
        'json' => 'application/json; charset=UTF-8',
        'csv' => 'text/csv; charset=UTF-8',
        'tsv' => 'text/tab-separated-values; charset=UTF-8',
    ];

    header('Content-Type: ' . $types[$format]);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, no-store');
}

function api_export_resource_column_value($value)
{
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_scalar($value) || $value === null) {
        return (string)$value;
    }
    return json_encode($value, JSON_UNESCAPED_UNICODE);
}

function api_export_resource_columns($records)
{
    $columns = [];
    foreach ($records as $uuid => $record) {
        if (!isset($record['uuid'])) {
            $columns['uuid'] = true;
        }
        foreach ($record as $key => $value) {
            $columns[$key] = true;
        }
    }
    return array_keys($columns);
}

function api_export_resource_delimited($resource, $records, $format)
{
    $delimiter = $format === 'csv' ? ',' : "\t";
    api_export_resource_headers($resource, $format);

    $out = fopen('php://output', 'w');
    $columns = api_export_resource_columns($records);
    fputcsv($out, $columns, $delimiter);

    foreach ($records as $uuid => $record) {
        if (!isset($record['uuid'])) {
            $record = ['uuid' => $uuid] + $record;
        }
        $row = [];
        foreach ($columns as $column) {
            $row[] = api_export_resource_column_value($record[$column] ?? '');
        }
        fputcsv($out, $row, $delimiter);
    }
    fclose($out);
    exit();
}

function api_export_resource_json($resource, $records)
{
    api_export_resource_headers($resource, 'json');
    exit(json_encode([
        '_meta' => data_meta($resource),
        $resource => $records,
        'count' => count($records),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function api_export_resource($resource, $format = null)
{
    if ($format === null) {
        $format = filter_input(INPUT_GET, 'format', FILTER_SANITIZE_SPECIAL_CHARS);
    }
    if (empty($format)) {
        $format = 'json';
    }
    $format = strtolower($format);

    if (!in_array($format, ['json', 'csv', 'tsv'], true)) {
        return json_result(['message' => 'INVALID_FORMAT'], 400);
    }

    $records = data_read($resource);

    if ($format === 'json') {
        return api_export_resource_json($resource, $records);
    }
    return api_export_resource_delimited($resource, $records, $format);
}
