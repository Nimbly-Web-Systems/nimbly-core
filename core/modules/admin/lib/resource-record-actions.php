<?php

function resource_record_actions_sc()
{
    load_libraries(['access', 'data', 'set', 'get']);
    $resource = get_variable('resource-id', '');
    if ($resource === '') {
        return;
    }
    $actions = data_meta($resource)['record_actions'] ?? [];
    if (!is_array($actions)) {
        return;
    }

    // Actions are add- or edit-only by nature (an import-from-document action
    // makes no sense once a record exists; a create-newsletter action makes
    // no sense before one does), so each entry opts into one via `scope`.
    // Missing scope defaults to 'edit' — the only context this panel was
    // rendered in before the add form gained one of its own.
    // Uses _bf_uuid, not nb_form_edit — build_form() resets nb_form_edit to
    // 'false' again right after its own fields loop, before this panel (a
    // sibling of the form, rendered after it) ever runs; _bf_uuid is set
    // once by build_form() and never reset.
    $current_scope = get_variable('_bf_uuid', '') !== '' ? 'edit' : 'add';

    $rendered = [];
    foreach ($actions as $action) {
        if (!is_array($action)) {
            continue;
        }
        $template = trim((string)($action['template'] ?? ''));
        $feature = trim((string)($action['feature'] ?? ''));
        $scope = trim((string)($action['scope'] ?? 'edit'));
        if ($template === '' || !preg_match('/^[a-z0-9][a-z0-9-]*$/', $template)) {
            continue;
        }
        if ($scope !== $current_scope) {
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
