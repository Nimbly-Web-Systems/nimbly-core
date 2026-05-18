<?php

/**
 * [#render-field#] — render a single form field from a field definition.
 *
 * Resolves the field definition from one of three sources (in order):
 *
 *   1. Inline JSON   def="{'type':'text','name':'Title'}"
 *   2. Local file    def="form_fields" name="title"   → {uri_path}/form_fields.json
 *   3. Resource meta def="articles.title"             → articles/.meta fields.title
 *
 * Sets all _f.* template variables and dispatches to [#field-{type}#].
 *
 * Parameters:
 *   def    Required. Field source: inline JSON, local filename, or resource.field
 *   name   Field key — used as HTML name and Alpine model segment
 *   val    Literal value to pre-populate the field
 *   var    Variable path to read value from (e.g. "record.email")
 *   store  Alpine data store prefix (default: "form_data")
 *   source Image/file base path (for image and gallery fields)
 */
function render_field_sc($params)
{
    $def = get_param_value($params, 'def', current($params));

    if (!is_string($def)) {
        return;
    }

    $field_val = get_param_value($params, 'val');
    if ($field_val === null) {
        $field_var = get_param_value($params, 'var');
        if ($field_var !== null) {
            $field_val = _get_field_value($field_var);
        }
    }

    $field_name = get_param_value($params, 'name') ?? '';
    $store      = get_param_value($params, 'store', 'form_data');
    $source     = get_param_value($params, 'source', null);

    $def = trim($def);

    // 1: inline JSON
    if ($def[0] === '{' || $def[0] === '[') {
        $json = str_replace("'", '"', $def);
        render_field(json_decode($json, true) ?: [], $field_name, $field_val, $store, $source);
        return;
    }

    // 2: local JSON file in current URI scope
    $file = $GLOBALS['SYSTEM']['uri_path'] . '/' . $def . '.json';
    if (file_exists($file)) {
        render_field(json_decode(file_get_contents($file), true) ?: [], $field_name, $field_val, $store, $source);
        return;
    }

    // 3: resource meta — supports "resource.field" and ".system-resource.field"
    $resource = $def;
    if (str_contains($def, '.')) {
        if ($def[0] === '.') {
            $parts    = explode('.', ltrim($def, '.'));
            $resource = '.' . array_shift($parts);
        } else {
            $parts    = explode('.', $def);
            $resource = array_shift($parts);
        }
        if (!$field_name) {
            $field_name = implode('.', $parts);
        }
    }

    if (!$resource || !$field_name) {
        return;
    }

    $meta = data_meta($resource);
    if (empty($meta['fields'][$field_name])) {
        return;
    }

    render_field($meta['fields'], $field_name, $field_val, $store, $source);
}

/**
 * Prepare and render a single form field.
 *
 * Sets all _f.* template variables then dispatches to [#field-{type}#].
 *
 * The entire field definition is spread into _f.* so templates can access
 * any custom attribute (resource, options, buttons, media, ai_prompts, etc.)
 * without this function needing to enumerate them.
 *
 * @param array       $def    Fields hash (keyed by name) or a single field definition
 * @param string      $field  Key within $def — omit when $def is already one field
 * @param mixed       $value  Pre-populated value; null falls back to $def['default']
 * @param string      $store  Alpine data store name (default: "form_data")
 * @param string|null $source Image/file base path (for image/gallery fields)
 * @param string|null $model  Override the computed Alpine x-model expression
 */
function render_field(array $def, string $field = '', $value = null, string $store = 'form_data', ?string $source = null, ?string $model = null): void
{
    if ($field && isset($def[$field])) {
        $def = $def[$field];
    }

    $type = $def['type'] ?? 'text';

    // Spread entire definition into _f.* so templates have access to all
    // field attributes without this function needing to enumerate them.
    set_variable_dot('_f', $def);

    set_variable('_f.key',      $field);
    set_variable('_f.name',     $field);
    set_variable('_f.title',    $def['name'] ?? ucfirst(str_replace(['-', '_'], ' ', $field)));
    set_variable('_f.bg',       'bg-white');
    set_variable('_f.required', !empty($def['required']));
    set_variable('_f.ai',       !empty($def['ai_prompts']));
    $field_value = $value ?? $def['default'] ?? '';
    $i18n_seed = null;

    if ($model === null) {
        $model = "{$store}.{$field}";
        if (!empty($def['i18n'])) {
            $lang = get_variable('lang') ?? get_variable('record.lang') ?? '';
            $i18n_seed = is_array($field_value) ? $field_value : ($lang ? [$lang => $field_value] : []);
            if ($lang) {
                if (is_array($field_value)) {
                    $field_value = $field_value[$lang] ?? '';
                }
                $model .= "[lang]";
            }
        }
    }
    set_variable('_f.value', $field_value);
    set_variable('_f.model', $model);

    // i18n fields: seed the full language map into the Alpine store so editors
    // can bind to form_data.field['lang'] without losing other languages.
    if (!empty($def['i18n'])) {
        $json = json_encode($i18n_seed ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $init = "if (!{$store}.{$field} || typeof {$store}.{$field} !== 'object') { {$store}.{$field} = {$json}; }";
        echo "<div x-init=\"" . htmlspecialchars($init, ENT_QUOTES, 'UTF-8') . "\"></div>\n";
    }

    if ($source !== null) {
        set_variable('_f.source', $source);
    }

    run_single_sc('field-' . $type);
}

function _get_field_value(string $var_name)
{
    load_library('get');
    return get_variable($var_name);
}
