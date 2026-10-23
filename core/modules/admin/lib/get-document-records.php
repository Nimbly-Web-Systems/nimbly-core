<?php

load_library('data');
load_library('set');
load_library('access');
load_library('resource-title');

/**
 * Data for the document explorer, the overview of a resource with
 * "admin_view": "documents" in its .meta: the first file field is the
 * document, the first filterable select field the category, and a numeric
 * sort field the manual order.
 */
function get_document_records_sc($params)
{
    $resource = get_param_value($params, 'resource', current($params));
    if (empty($resource) || !data_exists($resource)) {
        return;
    }

    $meta = data_meta($resource);
    $fields = document_explorer_fields($resource, $meta);
    if ($fields['file'] === null) {
        return;
    }

    $records = data_read($resource) ?: [];
    if (!empty($records) && isset($meta['sort'])) {
        load_library('data-sort');
        $records = data_sort_meta($records, $meta['sort']);
    }

    $documents = [];
    foreach ($records as $uuid => $record) {
        $documents[] = document_explorer_document($resource, (string)$uuid, $record, $fields);
    }

    set_variable('data.documents', [
        'resource' => $resource,
        'fields' => $fields,
        'categories' => document_explorer_categories($meta, $fields['category']),
        'documents' => $documents,
        // a new document is an upload to .files first, see api_method_switch()
        'can_create' => access_by_feature('create-' . $resource) && access_by_feature('create-files,manage-files'),
        'can_edit' => access_by_feature('edit-' . $resource),
        'can_delete' => access_by_feature('delete-' . $resource),
        // PDF thumbnails come from pdftoppm, see thumbnail_open_pdf()
        'pdf_preview' => is_executable('/usr/bin/pdftoppm'),
    ]);
}

function document_explorer_fields(string $resource, array $meta): array
{
    $result = ['file' => null, 'category' => null, 'position' => null, 'title' => null];
    $fields = is_array($meta['fields'] ?? null) ? $meta['fields'] : [];
    foreach ($fields as $field_id => $field) {
        $type = $field['type'] ?? '';
        if ($result['file'] === null && $type === 'file') {
            $result['file'] = $field_id;
        }
        if ($result['category'] === null && $type === 'select' && !empty($field['admin_filter']) && empty($field['multiple'])) {
            $result['category'] = $field_id;
        }
    }
    $sort_field = $meta['sort']['field'] ?? '';
    if ($sort_field !== '' && ($fields[$sort_field]['type'] ?? '') === 'number') {
        $result['position'] = $sort_field;
    }
    $title_field = resource_title_field($resource);
    if ($title_field !== null && ($fields[$title_field]['type'] ?? '') === 'text' && empty($fields[$title_field]['i18n'])) {
        $result['title'] = $title_field;
    }
    return $result;
}

function document_explorer_categories(array $meta, ?string $field_id): array
{
    if ($field_id === null) {
        return [];
    }
    $field = $meta['fields'][$field_id];
    if (empty($field['resource'])) {
        return array_map('strval', $field['options'] ?? []);
    }
    if (!data_exists($field['resource'])) {
        return [];
    }
    $categories = [];
    foreach (data_read($field['resource']) as $uuid => $record) {
        $categories[$uuid] = resource_title($field['resource'], $record + ['uuid' => $uuid]);
    }
    return $categories;
}

function document_explorer_document(string $resource, string $uuid, array $record, array $fields): array
{
    $file_uuid = (string)($record[$fields['file']] ?? '');
    $file = $file_uuid !== '' && data_exists('.files_meta', $file_uuid) ? data_read('.files_meta', $file_uuid) : [];
    return [
        'uuid' => $uuid,
        'title' => resource_title($resource, $record + ['uuid' => '']),
        'file' => $file_uuid,
        'name' => (string)($file['name'] ?? ''),
        'type' => (string)($file['type'] ?? ''),
        'size' => (int)($file['size'] ?? 0),
        'category' => $fields['category'] !== null ? (string)($record[$fields['category']] ?? '') : '',
        'position' => $fields['position'] !== null ? (float)($record[$fields['position']] ?? 0) : 0,
        'modified' => (int)($record['_modified'] ?? $record['_created'] ?? 0),
    ];
}
