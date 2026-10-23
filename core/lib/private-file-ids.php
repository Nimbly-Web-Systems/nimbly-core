<?php

load_library('file-access');

/** The files of private resources as a JSON list, kept out of the media library. */
function private_file_ids_sc($params)
{
    $ids = [];
    foreach (file_private_fields() as $resource => $fields) {
        foreach (data_read($resource) ?: [] as $record) {
            foreach ($fields as $field) {
                foreach ((array)($record[$field] ?? []) as $uuid) {
                    if (is_string($uuid) && $uuid !== '') {
                        $ids[$uuid] = true;
                    }
                }
            }
        }
    }
    return json_encode(array_keys($ids));
}
