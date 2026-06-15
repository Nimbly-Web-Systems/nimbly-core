<?php

function build_form_sc($params)
{
    if (empty($params)) {
        return;
    }
    $file = $GLOBALS['SYSTEM']['uri_path'] . '/' . current($params) . '.json';
    if (!file_exists($file) || is_dir($file)) {
        return;
    }
    $contents = file_get_contents($file);
    $form_def = json_decode($contents, true);
    if (empty($form_def) || empty($form_def['name']) || empty($form_def['resource']) || empty($form_def['fields'])) {
        return;
    }
    build_form($form_def, $params);
}

function build_form($form_def, $params = [])
{
    set_variable('_bf_name', $form_def['name']);
    set_variable('_bf_resource', $form_def['resource']);
    set_variable('_bf_upload_field', $form_def['upload_field'] ?? false);
    set_variable('_bf_success_message', $form_def['success_message'] ?? '[#text Send#]');
    set_variable('_bf_status', $form_def['status'] ?? 'new');
    $class_name = _bf_class_suffix($form_def['class_name'] ?? $form_def['name']);
    $field_wrapper_class = _bf_field_wrapper_class($class_name, $params);
    set_variable('_bf_class_name', $class_name);
    set_variable('_bf_form_class', "nb-form nb-form-{$class_name}");
    set_variable('_bf_field_wrapper_class', $field_wrapper_class);
    echo '<script>' . run_buffered(dirname(__FILE__) . '/fscript.js') . '</script>';
    echo run_buffered(dirname(__FILE__) . '/fheader.tpl');
    echo run_buffered(dirname(__FILE__) . '/fbody.tpl');
    $fields = $form_def['fields'];
    set_variable('_fbg', $form_def['bg_color'] ?? 'bg-neutral-50');
    load_library('render-field');
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

        _bf_render_field($key, $def, null, null, $field_wrapper_class);
    }
    $buttons = $form_def['buttons'] ?? [];
    foreach ($buttons as $button) {
        set_variable("_ftitle", $button['title'] ?? 'Send');
        echo run_buffered(dirname(__FILE__) . '/fbutton-' . $button['type'] . '.tpl');
    }
    echo run_buffered(dirname(__FILE__) . '/ffooter.tpl');
}

function _bf_render_field($key, $def, $group = null, $ix = null, $field_wrapper_class = '')
{
    $type  = $def['type'] ?? 'text';
    $model = ($group && $ix) ? "form_data.{$group}[{$ix}].{$key}" : null;
    $def['wrapper_class'] = $field_wrapper_class ?: 'nb-field relative my-10';

    echo '<div>';
    render_field($def, $key, null, 'form_data', null, $model);
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
