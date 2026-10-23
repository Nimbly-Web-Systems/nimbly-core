<?php

load_library('data');
load_library('util');

/**
 * PUT /api/v1/<resource>/options: saves the options of a select field in the
 * resource's .meta, in the order given, for a field with "editable_options": true. Body: {"field": "...", "options":
 * [{"value": "...", "label": "..."}]}; a new option has no value yet. An option
 * a record still uses cannot be left out.
 */
function select_options_put($resource)
{
    $input = json_decode((string)file_get_contents('php://input'), true);
    $field_id = is_array($input) ? (string)($input['field'] ?? '') : '';
    $meta = data_meta($resource);
    $field = $meta['fields'][$field_id] ?? null;
    if (!is_array($field) || ($field['type'] ?? '') !== 'select' || !empty($field['resource']) || !is_array($input['options'] ?? null)) {
        return json_result(['message' => 'INVALID_DATA'], 400);
    }
    if (empty($field['editable_options'])) {
        return json_result(['message' => 'ACCESS_DENIED'], 403);
    }

    // translated labels would be flattened to one language here
    foreach ($field['options'] ?? [] as $label) {
        if (!is_scalar($label)) {
            return json_result(['message' => 'INVALID_DATA'], 400);
        }
    }
    $options = select_options_from_input($input['options'], array_map('strval', $field['options'] ?? []));
    if ($options === null) {
        return json_result(['message' => 'INVALID_DATA'], 400);
    }
    foreach (array_keys($field['options'] ?? []) as $value) {
        if (!array_key_exists((string)$value, $options) && select_option_in_use($resource, $field_id, (string)$value)) {
            return json_result(['message' => 'OPTION_IN_USE', 'value' => (string)$value], 409);
        }
    }

    $meta['fields'][$field_id]['options'] = $options;
    if (!data_meta_save($resource, $meta)) {
        return json_result(['message' => 'WRITE_FAILED'], 500);
    }
    // a list: option values that look like numbers would lose their order in a JSON object
    $list = [];
    foreach ($options as $value => $label) {
        $list[] = ['value' => (string)$value, 'label' => $label];
    }
    return json_result(['options' => $list, 'message' => 'RESOURCE_UPDATED'], 200);
}

/** value => label in the given order, or null when an option is not acceptable. */
function select_options_from_input(array $input, array $current): ?array
{
    $result = [];
    foreach ($input as $option) {
        $label = is_array($option) && is_string($option['label'] ?? null) ? trim($option['label']) : '';
        // a label is plain text that templates print as it is
        if ($label === '' || mb_strlen($label) > 80 || preg_match('/\[#|#\]|[<>]/', $label)) {
            return null;
        }
        $value = (string)($option['value'] ?? '');
        if ($value !== '' && !array_key_exists($value, $current)) {
            return null;
        }
        if ($value === '') {
            $base = str_replace('-', '_', make_slug($label)) ?: 'option';
            $value = $base;
            for ($ix = 2; array_key_exists($value, $current) || array_key_exists($value, $result); $ix++) {
                $value = $base . '_' . $ix;
            }
        }
        if (array_key_exists($value, $result)) {
            return null;
        }
        $result[$value] = $label;
    }
    return $result;
}

function select_option_in_use(string $resource, string $field_id, string $value): bool
{
    if (in_array($field_id, data_meta($resource)['index'] ?? [], true)) {
        return !empty(data_read_index($resource, $field_id, md5_uuid($value)));
    }
    foreach (data_read($resource) ?: [] as $record) {
        if ((string)($record[$field_id] ?? '') === $value) {
            return true;
        }
    }
    return false;
}
