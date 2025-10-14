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
    build_form($form_def);
}

function build_form($form_def)
{
    set_variable('_bf_name', $form_def['name']);
    set_variable('_bf_resource', $form_def['resource']);
    set_variable('_bf_upload_field', $form_def['upload_field'] ?? false);
    set_variable('_bf_success_message', $form_def['success_message'] ?? '[#text Send#]');
    echo '<script>' . run_buffered(dirname(__FILE__) . '/fscript.js') . '</script>';
    echo run_buffered(dirname(__FILE__) . '/fheader.tpl');
    echo run_buffered(dirname(__FILE__) . '/fbody.tpl');
    $fields = $form_def['fields'];
    set_variable('_fbg', $form_def['bg_color'] ?? 'bg-neutral-50');
    load_module('admin');
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

        _bf_render_field($key, $def);
    }
    $buttons = $form_def['buttons'] ?? [];
    foreach ($buttons as $button) {
        set_variable("_ftitle", $button['title'] ?? 'Send');
        echo run_buffered(dirname(__FILE__) . '/fbutton-' . $button['type'] . '.tpl');
    }
    echo run_buffered(dirname(__FILE__) . '/ffooter.tpl');
}

function _bf_render_field($key, $def, $group = null, $ix = null)
{
    echo '<div>';
    $initial_multi = get_variable('item.multi');
    $type = $def['type'] ?? 'text';
    if ($type === 'select') {
        echo '<div class="p-1"></div>';
        set_variable('item.multi', $def['multi'] || false);
        set_variable('item.key', $key);
        set_variable('item.name', $def['name']);
        set_variable('_foptions', '');
        foreach ($def['options'] as $k => $v) {
            set_variable('_fkey', $k);
            set_variable('_fval', $v);
            set_variable('_foptions', run_buffered(dirname(__FILE__) . '/fselect.option.tpl'), '\n ');
        }
    } else if ($type === 'upload') {
        set_variable('_faccept', $def['accept'] ?? '');
    }

    
    $model_prefix = ($group && $ix)? $group . '[' . $ix . '].' : '';

    set_variable('_ftitle', $def['name']);
    set_variable('_ftype', $type);
    set_variable('_fname', $key);
    set_variable('_fmodel', "form_data." . $model_prefix . $key);
    set_variable('_fid', str_replace(['.', '[', ']'], '_', get_variable('_fmodel')));
    set_variable('_frequired', $def['required'] ?? false);
    set_variable('_fattr', $def['attr'] ?? '');
    run_single_sc($type . '-field');
    if ($type === 'select') {
        echo '<div class="p-2"></div>';
    }
    if (isset($def['help'])) {
        set_variable('_fhelp', $def['help']);
        echo run_buffered(dirname(__FILE__) . '/fhelp.tpl');
    }
    if (!empty($def['required'])) {
        echo run_buffered(dirname(__FILE__) . '/frequired.tpl');
    }
    set_variable('item.multi', $initial_multi);
    echo '</div>';
}
