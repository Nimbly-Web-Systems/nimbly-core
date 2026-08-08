<?php 

function get_resource_meta_sc($params) {
    $resource = get_param_value($params, 'resource', current($params));
    if (empty($resource)) {
        load_library('get');
        $resource = get_variable('resource-name', '(unkown)');
    }
    $uuid = get_param_value($params, 'uuid', null);

    if (!data_exists($resource)) {
        return;
    }

    $meta = data_meta($resource, $uuid);

    load_library("set");
    set_variable("data.resource", $resource);
    set_variable("data.fields", $meta['fields']);
    set_variables("data.field.", $meta['fields']);
    $ai_record_action = $meta['ai_record_action'] ?? ($meta['ai_translate_record'] ?? false);
    set_variable('data.ai_record_action', !empty($ai_record_action));
    set_variable(
        'data.ai_record_action_title',
        is_array($ai_record_action) ? ($ai_record_action['title'] ?? 'Translate') : 'Translate'
    );

    // Exposed for the add-form language picker and i18n submit wrapping —
    // see language_picker_sc() and build-form/fscript.js's post_entry().
    $languages = $meta['languages'] ?? [];
    set_variable('languages', $languages);
    set_variable('data.translation_languages', array_values($languages));
    if (!empty($languages)) {
        set_variable('record.lang', current($languages));
    }

    $i18n_fields = [];
    $ai_record_action_fields = [];
    foreach ($meta['fields'] as $key => $field) {
        $is_i18n = !empty($field['i18n']);
        // A slug field with no explicit i18n inherits it from its source
        // field(s) — mirrors the same inference in render-field.php.
        if (!$is_i18n && ($field['type'] ?? '') === 'slug' && !empty($field['source'])) {
            foreach (explode(',', $field['source']) as $source_field) {
                if (!empty($meta['fields'][trim($source_field)]['i18n'])) {
                    $is_i18n = true;
                    break;
                }
            }
        }
        if ($is_i18n) {
            $i18n_fields[] = $key;
            if (!empty($field['ai_prompts'])) {
                $ai_record_action_fields[] = $key;
            }
        }
    }
    set_variable('data.i18n_fields', $i18n_fields);
    set_variable('data.ai_record_action_fields', $ai_record_action_fields);
}
