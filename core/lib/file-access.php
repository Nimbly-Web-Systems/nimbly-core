<?php

load_library('data');
load_library('util');

/**
 * Files held in a file, image or gallery field of a resource with
 * "private_files": true in its .meta are served only to users who may view that resource. Every other
 * file stays public, as before.
 */
function file_access_allowed(string $uuid): bool
{
    $resources = file_private_resources($uuid);
    if (empty($resources)) {
        return true;
    }
    load_library('access');
    foreach ($resources as $resource) {
        if (access_by_feature('view-' . $resource) && file_owner_allowed($resource, $uuid)) {
            return true;
        }
    }
    return false;
}

/** In a resource with an owner, a private file is for the owner of a record that holds it. */
function file_owner_allowed(string $resource, string $uuid): bool
{
    $owner_field = (string)((data_meta($resource) ?: [])['owner_field'] ?? '');
    if ($owner_field === '') {
        return true;
    }
    load_library('session');
    $owner = session_resume() ? (string)($_SESSION['user_uuid'] ?? '') : '';
    foreach (data_read($resource) ?: [] as $record) {
        if ($owner !== '' && ($record[$owner_field] ?? '') === $owner) {
            foreach (file_private_fields()[$resource] ?? [] as $field) {
                $value = $record[$field] ?? '';
                if ($value === $uuid || (is_array($value) && in_array($uuid, $value, true))) {
                    return true;
                }
            }
        }
    }
    return false;
}

function file_is_private(string $uuid): bool
{
    return !empty(file_private_resources($uuid));
}

/**
 * The private resources that hold the file; an indexed file field is looked
 * up in its index, any other one by reading the records.
 */
function file_private_resources(string $uuid): array
{
    static $found = [];
    if (isset($found[$uuid])) {
        return $found[$uuid];
    }
    $result = [];
    foreach (file_private_fields() as $resource => $fields) {
        $index = data_meta($resource)['index'] ?? [];
        foreach ($fields as $field) {
            $holds = in_array($field, $index, true)
                ? !empty(data_read_index($resource, $field, md5_uuid($uuid)))
                : file_records_hold($resource, $field, $uuid);
            if ($holds) {
                $result[] = $resource;
                break;
            }
        }
    }
    return $found[$uuid] = $result;
}

/** resource => its file fields, for each resource with private files */
function file_private_fields(): array
{
    static $result = null;
    if ($result !== null) {
        return $result;
    }
    $result = [];
    foreach (@scandir($GLOBALS['SYSTEM']['data_base']) ?: [] as $resource) {
        if ($resource[0] === '.' || !is_file(data_path($resource) . '/.meta')) {
            continue;
        }
        $meta = data_meta($resource);
        if (empty($meta['private_files'])) {
            continue;
        }
        $fields = [];
        foreach ($meta['fields'] ?? [] as $field_id => $field) {
            // an image or gallery holds files too, such as the cover of a document
            if (in_array($field['type'] ?? '', ['file', 'image', 'gallery'], true)) {
                $fields[] = $field_id;
            }
        }
        $result[$resource] = $fields;
    }
    return $result;
}

function file_records_hold(string $resource, string $field, string $uuid): bool
{
    foreach (data_read($resource) ?: [] as $record) {
        $value = $record[$field] ?? '';
        if ($value === $uuid || (is_array($value) && in_array($uuid, $value, true))) {
            return true;
        }
    }
    return false;
}
