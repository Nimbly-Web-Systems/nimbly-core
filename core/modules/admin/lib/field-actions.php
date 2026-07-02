<?php

function field_actions_sc($params) {
    $resource = get_param_value($params, "resource", null);
    $field = get_param_value($params, "field", null);

    if (empty($resource) || empty($field)
        || preg_match('/[^a-zA-Z0-9_.-]/', $resource)
        || preg_match('/[^a-zA-Z0-9_.-]/', $field)) {
        return;
    }

    $tpl = find_template($field, 'tpl/edit-resource-form/field-actions/' . $resource);
    if (empty($tpl)) {
        return;
    }

    add_sc_level('field-actions', $tpl);
    run($tpl);
    remove_sc_level();
}
