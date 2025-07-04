<?php

/**
 *
 * Shortcode implementation for [#render-field ...#] for rendering a dynamic form field based 
 * on field definitions
 *
 * @doc **Parameters:**
 * @doc - `def`: Required.The field definition or reference (JSON string, resource.field, or field name)
 * @doc - `name`: Optional.The field name (used for lookup in local files or meta).
 *
 * @doc **Resolution order:**
 * @doc 1. **User-provided JSON:** If the `def` param is a JSON string, it is parsed and used directly.
 * @doc 2. **Local file:** If a file named `{def}.json` exists in the current directory (`uri_path`), it is loaded and used.
 * @doc 3. **Resource meta:** If no local file exists, the resource's meta (e.g., `proposals/.meta`) is loaded and the field definition is taken from there using `name`.
 *
 * @doc **Usage examples:**
 * @doc - `[\#render-field def="{ 'key':'title','name':'Title','type':'text' }" name="title" \#]`   // User JSON
 * @doc - `[\#render-field def="form_fields" name="title"\#]`   // Local file form_fields.json or resource form_fields(/.meta)
 
 */
function render_field_sc($params) {
    $def = get_param_value($params, 'def', current($params));
    
    if (!is_string($def)) {
        return;
    }

    // Get field value (if any)
    $field_val = get_param_value($params, 'val');
    if ($field_val === null) {
        $field_var = get_param_value($params, 'var');
        if ($field_var !== null) {
            $field_val = _get_field_value($field_var);
            unset($params['var']);
        }
    } else {
        unset($params['val']);
    }
    
    $field_name = get_param_value($params, 'name', count($params) > 1? next($params) : '') ?? '';
    

    // 1: JSON field definition
    $def = trim($def);
    if (strpos($def, '{') === 0 || strpos(trim($def), '[') === 0) {
        $json = str_replace("'", '"', $def);
        $def = json_decode($json, true) ?: [];
        return render_field($def, $field_name, $field_val);

    }
    
    // 2: read from (local scope) json file
    $file = $GLOBALS['SYSTEM']['uri_path'] . '/' . $def . '.json';
    if (file_exists($file)) {
        $contents = file_get_contents($file);
        $def = json_decode($contents, true);
        return render_field($def, $field_name, $field_val);
    }

    // 3: get field def from resource meta
    $resource = $def;
    if (strpos($def, '.') !== false) {
        if (substr($def, 0, 1) === '.') {
            // system resource (leading dot)
            $parts = explode('.', ltrim($def, '.'));
            $resource = '.' . array_shift($parts);
        } else {
            $parts = explode('.', $def);
            $resource = array_shift($parts);
        }
        
        $field_name = empty($field_name)? implode('.', $parts) : $field_name;
    }

    if (empty($resource) || empty($field_name)) {
        return;
    }

    $meta = data_meta($resource);
    
    if (empty($meta['fields'][$field_name])) {
        return;
    }

    return render_field($meta['fields'], $field_name, $field_val);    
}

/**
 * Render a single form field using the forms module templates
 *
 * @param array  $def Field definition (from meta or JSON)
 * @param string $field Optional Field name used for element names/IDs
 * @param string $value Optional ...
 * @return string HTML output
 */
function render_field($def, $field = '', $value = null) {
    if (!empty($field) && isset($def[$field])) {
        $def = $def[$field];
    }
    set_variable_dot('_f', $def);
    set_variable('_f.name', $field);
    set_variable('_f.title', $def['name'] ?? ucfirst($field));
    set_variable('_f.key', $field);
    set_variable('_f.value', $value === null? $def['default'] ?? '' : $value);
    set_variable('_f.model',     "form_data.{$field}");
    set_variable('_f.required',  !empty($def['required']));
    run_single_sc('field-' . $def['type']);
}

function _get_field_Value($var_name) {
    return "todo " . $var_name;
}