<?php

load_library('get');
load_library('set');

function field_actions_sc($params) {
    $actions = get_variable('_f.actions', []);
    if (empty($actions) || !is_array($actions)) {
        return;
    }

    $field = get_variable('_f.key', '');
    $buttons = '';
    foreach ($actions as $action) {
        if (!is_array($action) || !_field_action_allowed($action)) {
            continue;
        }
        $buttons .= _field_action_render($action, $field);
    }

    if ($buttons === '') {
        return;
    }

    $align = get_param_value($params, 'align', 'center');
    set_variable('_fa.top_class', $align === 'top' ? 'top-3' : 'top-1/2 -translate-y-1/2');
    set_variable('_fa.buttons', $buttons);

    return run_buffered(dirname(__FILE__) . '/panel.tpl');
}

function _field_action_render(array $action, string $field): string
{
    $type = (string)($action['type'] ?? 'link');
    $action['label']    = (string)($action['label'] ?? ($type === 'ai' ? 'Generate with AI' : 'Open'));
    $action['icon']     = (string)($action['icon']  ?? ($type === 'ai' ? 'sparkles' : 'external-link'));
    $action['icon_svg'] = run_buffered(dirname(__FILE__) . "/icon-{$action['icon']}.tpl");

    if ($type === 'link') {
        $target = _field_action_template((string)($action['target'] ?? ''));
        if ($target === '' || strpos($target, '[#') !== false) {
            return '';
        }
        $action['target'] = $target;
        return _field_action_item($action, 'action-link');
    }

    if ($type === 'ai') {
        $action['field_arg'] = htmlspecialchars(json_encode($field, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
        return _field_action_item($action, 'action-ai');
    }

    return '';
}

function _field_action_item(array $action, string $tpl): string
{
    set_variable_dot('item', $action);
    $html = run_buffered(dirname(__FILE__) . "/{$tpl}.tpl");
    clear_variable_dot('item');
    return $html;
}

function _field_action_template(string $template): string
{
    if ($template === '') {
        return '';
    }

    ob_start();
    run_template($template);
    return trim((string)ob_get_clean());
}

function _field_action_allowed(array $action): bool
{
    $feature = trim((string)($action['feature'] ?? ''));
    if ($feature === '') {
        return true;
    }

    load_libraries(['session', 'permissions']);
    return session_resume() && isset($_SESSION['features']) && permission_session_has($feature);
}
