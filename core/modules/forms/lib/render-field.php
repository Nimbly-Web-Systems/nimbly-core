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
 * any custom attribute (resource, options, actions, media, ai_prompts, etc.)
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
    $fields = $def;
    $is_single_field = _field_is_single_definition($def);
    if (!$is_single_field && $field && isset($def[$field]) && is_array($def[$field])) {
        $def = $def[$field];
    }

    $type = $def['type'] ?? 'text';
    if ($type === 'slug' && !empty($def['source']) && empty($def['i18n'])) {
        foreach (explode(',', $def['source']) as $source_field) {
            $source_field = trim($source_field);
            if (!empty($fields[$source_field]['i18n'])) {
                $def['i18n'] = true;
                break;
            }
        }
    }

    // Spread entire definition into _f.* so templates have access to all
    // field attributes without this function needing to enumerate them.
    set_variable_dot('_f', $def);

    set_variable('_f.key',      $field);
    set_variable('_f.name',     $field);
    set_variable('_f.title',    $def['name'] ?? ucfirst(str_replace(['-', '_'], ' ', $field)));
    set_variable('_f.bg',       'bg-white');
    set_variable('_f.required', !empty($def['required']));
    $actions = _field_actions_normalize($def);

    set_variable('_f.ai',       !empty($def['ai_prompts']));
    set_variable('_f.actions',  $actions);
    set_variable('_f.has_actions', !empty($actions));
    if (isset($def['options']) && is_array($def['options'])) {
        // Re-shape into a sequential list so option keys can never collide with
        // a configured language code and get silently collapsed by get_sc()'s
        // automatic i18n resolution (e.g. a `lang` select field whose options
        // are themselves "nl"/"en").
        $safe_options = [];
        foreach ($def['options'] as $opt_key => $opt_label) {
            $safe_options[] = ['code' => (string)$opt_key, 'label' => $opt_label];
        }
        set_variable('_f.options', $safe_options);
    }
    set_variable('_f.wrapper_class', $def['wrapper_class'] ?? 'nb-field relative my-10');
    $field_value = $value ?? $def['default'] ?? '';
    $i18n_seed = null;

    // The edit form holds every language's value at once (tabs switch which
    // one is visible), so its fields bind to form_data.field['lang']. The add
    // form captures a single language at a time (chosen via the language
    // picker) and wraps the flat value into {lang: value} on submit — so its
    // i18n fields must stay flat scalars like any other field, not objects.
    // nb_form_edit is a template variable — [#set nb_form_edit=false#] stores
    // the literal string "false", which is truthy to PHP's empty(), so this
    // must compare the string value rather than testing emptiness.
    $is_edit_i18n = !empty($def['i18n']) && get_variable('nb_form_edit') === 'true';

    if ($model === null) {
        $model = "{$store}.{$field}";
        if ($is_edit_i18n) {
            $lang = get_variable('lang') ?? get_variable('record.lang') ?? '';
            $i18n_seed = is_array($field_value) ? $field_value : ($lang ? [$lang => $field_value] : []);
            if ($lang) {
                if (is_array($field_value)) {
                    $field_value = $field_value[$lang] ?? '';
                }
                $model .= "[lang]";
            }
        } elseif (!empty($def['i18n']) && is_array($field_value)) {
            $lang = get_variable('lang') ?? get_variable('record.lang') ?? '';
            $field_value = $field_value[$lang] ?? '';
        }
    }
    set_variable('_f.value', $field_value);
    set_variable('_f.model', $model);
    $x_init = '';
    if (!$is_edit_i18n) {
        $init_value = json_encode((string)$field_value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $x_init = 'x-init="' . htmlspecialchars("{$model}={$init_value}", ENT_QUOTES, 'UTF-8') . '"';
    }
    set_variable('_f.x_init', $x_init);

    // Edit-mode i18n fields: seed the full language map into the Alpine store
    // so editors can bind to form_data.field['lang'] without losing other
    // languages.
    if ($is_edit_i18n) {
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

function _field_is_single_definition(array $def): bool
{
    $definition_keys = [
        'type',
        'name',
        'required',
        'default',
        'help',
        'options',
        'resource',
        'actions',
        'ai_prompts',
        'i18n',
        'wrapper_class',
    ];

    foreach ($definition_keys as $key) {
        if (array_key_exists($key, $def) && !is_array($def[$key])) {
            return true;
        }
    }

    return false;
}

function _field_actions_normalize(array $def): array
{
    $actions = $def['actions'] ?? [];
    if (empty($actions)) {
        $actions = [];
    } else if (!is_array($actions)) {
        $actions = [];
    } else if (isset($actions['type'])) {
        $actions = [$actions];
    }

    if (!empty($def['ai_prompts'])) {
        $actions[] = [
            'type' => 'ai',
            'label' => 'Generate with AI',
            'icon' => 'sparkles',
        ];
    }

    return array_values(array_filter($actions, 'is_array'));
}
