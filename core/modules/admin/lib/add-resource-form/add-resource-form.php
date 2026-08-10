<?php

/**
 * [#add-resource-form#] — the admin "add a new record" page.
 *
 * A thin, admin-specific caller of the shared build_form() engine
 * (core/modules/forms/lib/build-form/build-form.php): resolves the
 * resource's schema via data_meta(), then hands it to build_form() in
 * create mode (no uuid=) with a redirect back to the resource list on
 * success.
 *
 * By design, creation always stays single-language — no uuid= means no
 * i18n edit-mode tabs (render_field()'s edit-i18n branch only activates
 * with nb_form_edit=true, which build_form() only sets when a uuid is
 * given). A record is filled in in one language first; translation
 * happens afterward via edit, once real content exists to translate from.
 * The language picker just lets that first language be chosen.
 */
function add_resource_form_sc($params)
{
    load_library('get');
    $resource = get_param_value($params, 'resource', current($params)) ?: get_variable('resource-id');
    if (empty($resource)) {
        return;
    }

    load_library('data');
    $meta = data_meta($resource);
    if (empty($meta['fields']) || !is_array($meta['fields'])) {
        return;
    }

    $form_def = [
        'name'                => 'add_resource_' . $resource,
        'resource'            => $resource,
        'fields'              => $meta['fields'],
        'form_class'          => 'w-full max-w-3xl',
        'redirect_on_success' => true,
        // Authenticated admin page, not a public submission form.
        'honeypot'            => false,
        'buttons'             => [
            ['type' => 'submit', 'title' => 'Save'],
        ],
    ];

    load_library('build-form');
    echo run_buffered(dirname(__FILE__) . '/arf-header.tpl');
    build_form($form_def, []);
    echo run_buffered(dirname(__FILE__) . '/arf-footer.tpl');
}
