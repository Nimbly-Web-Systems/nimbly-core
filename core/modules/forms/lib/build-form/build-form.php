<?php

/**
 * [#build-form#] — build a form from a definition.
 *
 * Two schema sources:
 *   1. {uri_path}/{name}.json — the original, unchanged create-only path.
 *   2. resource=/uuid= with no matching .json file — reads the schema via
 *      data_meta($resource, $uuid), which resolves either the resource's own
 *      external .meta or a record's embedded _fields/_languages.
 *
 * With uuid=, the form edits that existing record (prefill + PUT) instead of
 * creating a new one — reusing get_resource_meta_sc()/get_resource_record_sc(),
 * the same functions the admin resource-edit page relies on, so i18n
 * normalization, decryption, and translation-mode detection all behave
 * identically. Without uuid=, behavior is unchanged from before.
 */
function build_form_sc($params)
{
    if (empty($params)) {
        return;
    }
    $resource = get_param_value($params, 'resource', null);
    $uuid     = get_param_value($params, 'uuid', null);
    $name     = get_param_value($params, 'name', null);
    if ($name === null) {
        // Bare positional form name, e.g. [#build-form lobbyform#] — a
        // positional param's key equals its value (see run_single_sc()).
        foreach ($params as $key => $value) {
            if ($key === $value) {
                $name = $value;
                break;
            }
        }
    }

    $file = $name ? $GLOBALS['SYSTEM']['uri_path'] . '/' . $name . '.json' : null;
    if ($file && file_exists($file) && !is_dir($file)) {
        $form_def = json_decode(file_get_contents($file), true);
    } elseif ($resource) {
        load_library('data');
        $meta = data_meta($resource, $uuid);
        if (empty($meta['fields']) || !is_array($meta['fields'])) {
            return;
        }
        $form_def = [
            'name' => is_string($name) && $name !== '' ? $name : ($uuid ?: $resource),
            'resource' => $resource,
            'fields' => $meta['fields'],
            'form_class' => get_param_value($params, 'form-class', null),
            'actions_align' => get_param_value($params, 'actions-align', 'center'),
            'buttons' => [['type' => 'submit', 'title' => 'Save']],
        ];
    } else {
        return;
    }

    if (empty($form_def) || empty($form_def['name']) || empty($form_def['resource']) || empty($form_def['fields'])) {
        return;
    }
    build_form($form_def, $params, $uuid);
}

function build_form($form_def, $params = [], $uuid = null)
{
    $resource = $form_def['resource'];
    set_variable('_bf_name', $form_def['name']);
    set_variable('_bf_js_name', _bf_js_identifier($form_def['name']));
    set_variable('_bf_resource', $resource);
    set_variable('_bf_uuid', $uuid ?? '');
    set_variable('_bf_upload_field', $form_def['upload_field'] ?? false);
    set_variable('_bf_success_message', $form_def['success_message'] ?? '[#text Send#]');
    set_variable('_bf_status', $form_def['status'] ?? 'new');
    $class_name = _bf_class_suffix($form_def['class_name'] ?? $form_def['name']);
    $field_wrapper_class = _bf_field_wrapper_class($class_name, $params);
    set_variable('_bf_class_name', $class_name);
    set_variable('_bf_form_class', "nb-form nb-form-{$class_name}");
    set_variable('_bf_form_visual_class', $form_def['form_class'] ?? 'mt-4 p-2 rounded-md');
    set_variable('_bf_field_wrapper_class', $field_wrapper_class);
    set_variable('_bf_actions_class', _bf_actions_class($form_def['actions_align'] ?? 'center'));
    // The honeypot is an anti-bot measure for public, anonymous submission
    // forms — it makes no sense (and actively blocks fast automated saves,
    // since it stays `required` for several seconds) on an authenticated
    // edit of an existing record, so it defaults off whenever uuid= is used
    // and can still be forced either way via $form_def['honeypot'].
    set_variable('_bf_honeypot', ($form_def['honeypot'] ?? empty($uuid)) ? 'true' : '');
    // Redirects to _resource_url on success instead of resetting the form in
    // place — off by default (public forms stay put and show a thank-you),
    // on for admin add/edit flows that return to the resource list.
    set_variable('_bf_redirect_on_success', ($form_def['redirect_on_success'] ?? false) ? 'true' : '');

    load_library('get-resource-meta');
    get_resource_meta_sc(['resource' => $resource, 'uuid' => $uuid]);
    if ($uuid) {
        load_library('get-resource-record');
        get_resource_record_sc(['resource' => $resource, 'uuid' => $uuid]);
    }
    set_variable('_bf_initial_lang', get_variable('record.lang', 'en'));
    set_variable('_bf_resource_url', get_variable('_resource_url', ''));

    if ($uuid) {
        echo '<script>' . file_get_contents(dirname(__FILE__) . '/edit-form-state.js') . '</script>';
    }
    echo '<script>' . run_buffered(dirname(__FILE__) . '/fscript.js') . '</script>';
    echo run_buffered(dirname(__FILE__) . '/fheader.tpl');
    if (!empty($form_def['content_class'])) {
        echo '<div class="' . htmlspecialchars($form_def['content_class'], ENT_QUOTES, 'UTF-8') . '">';
    }
    echo run_buffered(dirname(__FILE__) . '/fbody.tpl');
    $fields = $form_def['fields'];
    set_variable('_fbg', $form_def['bg_color'] ?? 'bg-neutral-50');
    load_library('render-field');
    if ($uuid) {
        set_variable('nb_form_edit', 'true');
    }
    foreach ($fields as $key => $def) {
        $type = $def['type'] ?? 'text';
        if ($type === 'group_end') {
            echo run_buffered(dirname(__FILE__) . '/fgroup.end.tpl');
            continue;
        }
        set_variable('_ftitle', $def['name']);
        if ($type === 'group_start') {
            echo run_buffered(dirname(__FILE__) . '/fgroup.start.tpl');
            continue;
        }

        _bf_render_field($key, $def, $uuid, null, null, $field_wrapper_class, $fields);
    }
    if ($uuid) {
        set_variable('nb_form_edit', 'false');
    }
    $buttons = $form_def['buttons'] ?? [];
    if (!empty($buttons)) {
        echo run_buffered(dirname(__FILE__) . '/fbuttons-header.tpl');
        foreach ($buttons as $button) {
            set_variable("_ftitle", $button['title'] ?? 'Send');
            echo run_buffered(dirname(__FILE__) . '/fbutton-' . $button['type'] . '.tpl');
        }
        echo run_buffered(dirname(__FILE__) . '/fbuttons-footer.tpl');
    }
    if (!empty($form_def['content_class'])) {
        echo '</div>';
    }
    echo run_buffered(dirname(__FILE__) . '/ffooter.tpl');
}

function _bf_render_field($key, $def, $uuid = null, $group = null, $ix = null, $field_wrapper_class = '', $fields = [])
{
    $type  = $def['type'] ?? 'text';
    $model = ($group && $ix) ? "form_data.{$group}[{$ix}].{$key}" : null;
    $def['wrapper_class'] = $def['wrapper_class'] ?? ($field_wrapper_class ?: 'nb-field relative my-10');
    $value = $uuid ? get_variable("record.{$key}") : null;

    echo '<div>';
    // Keep the complete schema available while rendering. Slug fields inherit
    // i18n from their source fields, which cannot be determined from the slug
    // definition in isolation.
    $render_fields = $fields ?: [$key => $def];
    $render_fields[$key] = $def;
    render_field($render_fields, $key, $value, 'form_data', null, $model);
    if (isset($def['help'])) {
        echo run_buffered(dirname(__FILE__) . '/fhelp.tpl');
    }
    echo '</div>';
}

function _bf_class_suffix($raw_class_name)
{
    load_library('sanitize');
    $class_name = sanitize_id((string)$raw_class_name);
    return $class_name ?: 'form';
}

/**
 * Sanitizes a form name into a safe bare JS identifier — used only where the
 * name is embedded directly into a JS expression (the x-data attribute and
 * the matching Alpine.data() registration key), unlike _bf_class_suffix()
 * which allows hyphens because CSS classes don't need to be valid identifiers.
 */
function _bf_js_identifier($raw_name)
{
    $result = preg_replace('/[^A-Za-z0-9_$]/', '_', (string)$raw_name);
    if ($result === '' || preg_match('/^[0-9]/', $result)) {
        $result = '_' . $result;
    }
    return $result;
}

function _bf_field_wrapper_class($class_name, $params)
{
    load_library('get');
    $template_class = get_variable('form-field-wrapper-class');
    $param_class = get_param_value($params, 'field-wrapper-class');
    $style_class = $param_class ?: ($template_class ?: 'relative my-10');

    $classes = [
        'nb-field',
        "nb-form-field-{$class_name}",
        $style_class,
    ];

    return trim(preg_replace('/\s+/', ' ', implode(' ', $classes)));
}

function _bf_actions_class($alignment)
{
    return match ($alignment) {
        'start', 'left' => 'justify-start',
        'end', 'right' => 'justify-end',
        default => 'justify-center',
    };
}
