<?php

/**
 * Get and prepare a resource record for editing.
 * Assumes .meta exists and defines fields properly.
 * Handles decryption and i18n preparation.
 */

function get_resource_record_sc($params)
{
    load_library('get');
    $resource = get_param_value($params, 'resource', current($params)) ?? get_variable('resource-name');
    $uuid = get_param_value($params, 'uuid', end($params)) ?? get_variable('uuid');

    if (empty($resource) || empty($uuid)) {
        return;
    }

    load_library('data');

    if (!data_exists($resource, $uuid)) {
        return;
    }

    $meta = data_meta($resource);
    $record = data_read($resource, $uuid);

    // decrypt 2-way encrypted fields
    if (!empty($meta['encrypt2way'])) {
        load_library('encrypt');
        foreach (explode(',', $meta['encrypt2way']) as $field) {
            if (!empty($record[$field])) {
                $record[$field] = decrypt_2way($record[$field], $record['salt'] ?? '');
            }
        }
    }

    // i18n setup
    // note: older implementations set 'translations' in meta
    // new way: setting meta[languages] and i18n info per field
    if (isset($meta['languages']) && !isset($meta['translations'])) {
        $meta['translations'] = ['languages' => $meta['languages']];
        $record['lang'] = !empty($record['lang']) ? $record['lang'] : current($meta['languages']);
        foreach ($meta['languages'] as $lang) {
            foreach ($meta['fields'] as $fk => $field) {
                if (empty($field['i18n'])) {
                    continue;
                }
                if (!is_array($record[$fk])) {
                    $record[$fk] = [
                        $lang => $record[$fk] ?? ''
                    ];
                    continue;
                }
                if (!isset($record[$fk][$lang])) {
                    $record[$fk][$lang] = '';
                } else {
                    $record['translations'][$lang] = true;
                }
            }
        }
        set_variable('translation_mode', 'field');
    } else {
        set_variable('translation_mode', 'record');
    }

    if (isset($meta['translations'])) {
        $languages = $meta['translations']['languages'];
        if (empty($record['lang']) || !in_array($record['lang'], $languages, true)) {
            $record['lang'] = current($languages);
        }
        $ix = array_search($record['lang'], $languages);
        if ($ix !== false) {
            unset($languages[$ix]);
        }
        set_variable('languages', $languages);
        set_variable('has_translations', true);
    }

    set_variable('uuid', $uuid);
    set_variable('resource-name', $resource);
    set_variable('record', $record);
    set_variable('_frecord', $record);
    set_variable('data.uuid', $uuid);
    set_variable('data.resource', $resource);
    set_variable('data.fields', $meta['fields']);
    set_variable_dot('data.field', $meta['fields']);
    set_variable_dot('record', $record);
    set_variable_dot('translations', $record['translations'] ?? []);
}
