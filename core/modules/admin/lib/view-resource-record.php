<?php

load_library('get');
load_library('text');
load_library('util');

function view_resource_record_sc($params)
{
    $resource = get_param_value($params, 'resource', current($params)) ?? get_variable('data.resource');
    $uuid = get_param_value($params, 'uuid', end($params)) ?? get_variable('data.uuid');
    $record = get_variable('record') ?? [];
    $fields = get_variable('data.fields') ?? [];

    if (empty($resource) || empty($uuid) || !is_array($record) || !is_array($fields)) {
        return '';
    }

    $rows = '';
    foreach ($fields as $field_id => $field) {
        if (!is_array($field)) {
            continue;
        }
        $rows .= view_resource_record_row((string)$field_id, $field, $record[$field_id] ?? null);
    }

    $rows .= view_resource_record_row('uuid', ['name' => 'UUID', 'type' => 'text'], $uuid);

    return '<div class="overflow-hidden rounded-md border border-neutral-200 bg-white shadow-sm">'
        . '<dl class="divide-y divide-neutral-200">'
        . $rows
        . '</dl>'
        . '</div>';
}

function view_resource_record_row(string $field_id, array $field, $value): string
{
    $label = htmlspecialchars((string)($field['name'] ?? ucfirst(str_replace(['-', '_'], ' ', $field_id))), ENT_QUOTES, 'UTF-8');
    $body = view_resource_record_value((string)($field['type'] ?? 'text'), $value);

    return '<div class="grid gap-2 px-4 py-4 sm:grid-cols-[14rem_minmax(0,1fr)] sm:gap-6">'
        . '<dt class="text-sm font-semibold text-neutral-700">' . $label . '</dt>'
        . '<dd class="min-w-0 text-sm text-neutral-900">' . $body . '</dd>'
        . '</div>';
}

function view_resource_record_value(string $type, $value): string
{
    if (view_resource_record_empty($value)) {
        return '<span class="text-neutral-400">' . view_resource_record_text('Empty') . '</span>';
    }

    if (is_array($value)) {
        if (view_resource_record_is_list($value)) {
            if ($type === 'gallery') {
                return view_resource_record_gallery($value);
            }
            return view_resource_record_json($value);
        }
        return view_resource_record_map($value, $type);
    }

    $string = (string)$value;
    switch ($type) {
        case 'boolean':
            return !empty($value) ? view_resource_record_text('Yes') : view_resource_record_text('No');
        case 'html':
            return '<div class="prose max-w-none">' . $string . '</div>';
        case 'textarea':
            return '<div class="whitespace-pre-wrap">' . htmlspecialchars($string, ENT_QUOTES, 'UTF-8') . '</div>';
        case 'url':
            $url = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
            return '<a class="text-cnormal underline-offset-2 hover:underline" href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>';
        case 'email':
            $email = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
            return '<a class="text-cnormal underline-offset-2 hover:underline" href="mailto:' . $email . '">' . $email . '</a>';
        case 'image':
            return view_resource_record_image($string);
        case 'file':
        case 'upload':
            return view_resource_record_file($string);
        case 'date':
            $time = is_numeric($string) ? (int)$string : strtotime($string);
            if ($time !== false) {
                return htmlspecialchars(date('Y-m-d', $time), ENT_QUOTES, 'UTF-8');
            }
            return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
        default:
            return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

function view_resource_record_empty($value): bool
{
    return $value === null || $value === '' || $value === [];
}

function view_resource_record_is_list(array $value): bool
{
    return array_keys($value) === range(0, count($value) - 1);
}

function view_resource_record_map(array $value, string $type): string
{
    $html = '<div class="space-y-3">';
    foreach ($value as $key => $entry) {
        $html .= '<div class="rounded border border-neutral-200 bg-neutral-50 p-3">'
            . '<div class="mb-1 font-mono text-xs font-semibold uppercase tracking-wide text-neutral-500">'
            . htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8')
            . '</div>'
            . '<div>' . view_resource_record_value($type, $entry) . '</div>'
            . '</div>';
    }
    return $html . '</div>';
}

function view_resource_record_json(array $value): string
{
    return '<pre class="overflow-x-auto rounded bg-neutral-100 p-3 text-xs text-neutral-800">'
        . htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8')
        . '</pre>';
}

function view_resource_record_image(string $uuid): string
{
    $uuid = htmlspecialchars($uuid, ENT_QUOTES, 'UTF-8');
    return '<a href="' . view_resource_record_base_url() . '/img/' . $uuid . '" target="_blank" rel="noopener noreferrer" class="inline-block">'
        . '<img src="' . view_resource_record_base_url() . '/img/' . $uuid . '/240x240f" alt="" class="h-32 w-32 rounded border border-neutral-200 bg-neutral-100 object-cover">'
        . '</a>';
}

function view_resource_record_gallery(array $uuids): string
{
    $html = '<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">';
    foreach ($uuids as $uuid) {
        if (!is_scalar($uuid) || (string)$uuid === '') {
            continue;
        }
        $html .= view_resource_record_image((string)$uuid);
    }
    return $html . '</div>';
}

function view_resource_record_file(string $uuid): string
{
    $uuid = htmlspecialchars($uuid, ENT_QUOTES, 'UTF-8');
    return '<a class="text-cnormal underline-offset-2 hover:underline" href="' . view_resource_record_base_url() . '/download/' . $uuid . '" target="_blank" rel="noopener noreferrer">' . view_resource_record_text('Download file') . '</a>';
}

function view_resource_record_base_url(): string
{
    return htmlspecialchars((string)(get_variable('base-url') ?? ''), ENT_QUOTES, 'UTF-8');
}

function view_resource_record_text(string $key): string
{
    return htmlspecialchars(t($key), ENT_QUOTES, 'UTF-8');
}
