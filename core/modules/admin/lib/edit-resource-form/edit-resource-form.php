<?php

/**
 * [#edit-resource-form#] — the admin "edit an existing record" page.
 *
 * A thin, admin-specific caller of the shared build_form() engine
 * (core/modules/forms/lib/build-form/build-form.php): resolves the
 * resource/uuid's schema via data_meta() (external .meta or a record's own
 * embedded _fields/_languages), then hands it to build_form() in edit mode
 * with a Save + Delete button pair — the two things every admin resource
 * edit page needs that a public build-form caller never does.
 */
function edit_resource_form_sc($params)
{
    load_library('get');
    $resource = get_param_value($params, 'resource', current($params)) ?: get_variable('resource-id');
    $uuid     = get_param_value($params, 'uuid', end($params)) ?: get_variable('uuid');
    if (empty($resource) || empty($uuid)) {
        return;
    }

    load_library('data');
    if (!data_exists($resource, $uuid)) {
        return;
    }
    $meta = data_meta($resource, $uuid);
    if (empty($meta['fields']) || !is_array($meta['fields'])) {
        return;
    }

    $form_def = [
        'name'       => 'edit_resource_' . $resource,
        'resource'   => $resource,
        'fields'     => $meta['fields'],
        'form_class' => 'w-full max-w-3xl',
        'content_class' => 'pr-12',
        'redirect_on_success' => true,
        'buttons'    => [
            ['type' => 'submit', 'title' => 'Save'],
            ['type' => 'delete', 'title' => 'Delete'],
        ],
    ];

    load_library('build-form');
    echo run_buffered(dirname(__FILE__) . '/erf-header.tpl');
    build_form($form_def, [], $uuid);
    echo run_buffered(dirname(__FILE__) . '/erf-footer.tpl');
}
