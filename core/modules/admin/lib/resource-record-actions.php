<?php

function resource_record_actions_sc()
{
    load_libraries(['access', 'data', 'set']);
    $resource = get_variable('resource-id', '');
    if ($resource === '') {
        return;
    }
    $actions = data_meta($resource)['record_actions'] ?? [];
    if (!is_array($actions)) {
        return;
    }

    $rendered = [];
    foreach ($actions as $action) {
        if (!is_array($action)) {
            continue;
        }
        $template = trim((string)($action['template'] ?? ''));
        $feature = trim((string)($action['feature'] ?? ''));
        if ($template === '' || !preg_match('/^[a-z0-9][a-z0-9-]*$/', $template)) {
            continue;
        }
        if ($feature !== '' && !access_by_feature($feature)) {
            continue;
        }
        if (!find_template($template)) {
            continue;
        }
        set_variable_dot('record_action', $action);
        ob_start();
        run_single_sc($template);
        $content = trim((string)ob_get_clean());
        clear_variable_dot('record_action');
        if ($content !== '') {
            $rendered[] = $content;
        }
    }
    if (empty($rendered)) {
        return;
    }
    set_variable('record-actions-content', implode("\n", $rendered));
    $panel = find_template('record-actions-panel');
    return $panel ? run_buffered($panel) : null;
}
