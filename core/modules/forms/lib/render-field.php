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
 *   uuid   Record UUID — only used to resolve def="resource.field" against a
 *          record's own embedded schema when the resource has no external .meta
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
    $uuid       = get_param_value($params, 'uuid', null);

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

    $meta = data_meta($resource, $uuid);
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

    // Each render gets a fresh context. Definitions often omit optional keys,
    // so retaining the previous field's _f.* values can change how the next
    // field behaves (for example a slug's source leaking into an image field).
    clear_variable_dot('_f');

    // Spread entire definition into _f.* so templates have access to all
    // field attributes without this function needing to enumerate them.
    set_variable_dot('_f', $def);

    set_variable('_f.key',      $field);
    set_variable('_f.name',     $field);
    set_variable('_f.title',    $def['name'] ?? ucfirst(str_replace(['-', '_'], ' ', $field)));
    set_variable('_f.bg',       'bg-white');
    // A `required` HTML attribute on a field whose wrapper is CSS-hidden
    // (the `wrapper_class: "hidden"` convention for companion fields the
    // user never sees, e.g. location-picker's paired longitude input) is a
    // browser trap: an invalid-but-unfocusable control blocks the whole
    // form's submit with no visible error. The field can still be required
    // for data integrity — just not as a native HTML constraint here.
    $wrapper_classes = preg_split('/\s+/', trim((string)($def['wrapper_class'] ?? '')));
    $is_hidden_wrapper = in_array('hidden', $wrapper_classes, true);
    set_variable('_f.required', !empty($def['required']) && !$is_hidden_wrapper);
    // nb_form_edit is a template variable — [#set nb_form_edit=false#] stores
    // the literal string "false", which is truthy to PHP's empty(), so this
    // must compare the string value rather than testing emptiness.
    $is_edit_mode = get_variable('nb_form_edit') === 'true';
    // Some fields only make sense once a record exists — a decision the
    // editor shouldn't be prompted for while still drafting a brand-new one.
    if (!empty($def['hide_on_add']) && !$is_edit_mode) {
        return;
    }
    $actions = _field_actions_normalize($def, $is_edit_mode);

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
    if ($type === 'date' && $field_value === 'today') {
        $field_value = date('Y-m-d');
    }
    if ($type === 'date' && is_string($field_value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $field_value)) {
        $field_value = substr($field_value, 0, 10);
    }
    $i18n_seed = null;

    // The edit form holds every language's value at once (tabs switch which
    // one is visible), so its fields bind to form_data.field['lang']. The add
    // form captures a single language at a time (chosen via the language
    // picker) and wraps the flat value into {lang: value} on submit — so its
    // i18n fields must stay flat scalars like any other field, not objects.
    $is_edit_i18n = !empty($def['i18n']) && $is_edit_mode;

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
        if (!empty($def['multi'])) {
            if (is_string($field_value)) {
                $field_value = trim($field_value);
                $field_value = $field_value === ''
                    || $field_value === '(empty)'
                    || strcasecmp($field_value, 'Array') === 0
                    ? []
                    : array_values(array_filter(array_map('trim', explode(',', $field_value))));
            } elseif (!is_array($field_value)) {
                $field_value = [];
            }
        }
        $init_value = json_encode(
            !empty($def['multi'])
                ? array_values($field_value)
                : (is_array($field_value) ? $field_value : (string)$field_value),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
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

    $template_type = $type === 'file' && !empty($def['multi']) ? 'file-multi' : $type;
    run_single_sc('field-' . $template_type);
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

function _field_actions_normalize(array $def, bool $is_edit_mode): array
{
    $actions = $def['actions'] ?? [];
    if (empty($actions)) {
        $actions = [];
    } else if (!is_array($actions)) {
        $actions = [];
    } else if (isset($actions['type'])) {
        $actions = [$actions];
    }

    // AI-assist actions read/write the edit form's per-language value map
    // (form_data.field.lang) and the Alpine state that backs it, neither of
    // which exist on the add form — a new record has one language, entered
    // via the language picker, and translations are only added afterwards
    // in edit mode. Offering the action before then is not just unsupported
    // UI, it's a guaranteed Alpine ReferenceError on page load.
    if ($is_edit_mode && !empty($def['ai_prompts'])) {
        $actions[] = [
            'type' => 'ai',
            'label' => $def['ai_label'] ?? 'Generate with AI',
            'icon' => 'sparkles',
        ];
    }

    $actions = array_values(array_filter($actions, 'is_array'));
    if (!$is_edit_mode) {
        $actions = array_values(array_filter($actions, fn($action) => ($action['type'] ?? 'link') !== 'ai'));
    }

    return $actions;
}
