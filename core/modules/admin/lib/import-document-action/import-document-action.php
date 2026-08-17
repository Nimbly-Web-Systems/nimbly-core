<?php

/**
 * [#import-document-action#] — "prepare" shortcode for the
 * action-import-document record action. Only sets `import_document_resource`
 * (and so only lets the panel render) when the resource has at least one
 * free-text field the import endpoint could actually fill in.
 */
function import_document_action_sc()
{
    load_libraries(['get', 'data']);
    $resource = get_variable('resource-id', '');
    if ($resource === '') {
        return;
    }

    $extractable_types = ['text', 'textarea', 'html'];
    $meta = data_meta($resource);
    foreach ($meta['fields'] ?? [] as $definition) {
        if (in_array($definition['type'] ?? 'text', $extractable_types, true)) {
            set_variable('import_document_resource', $resource);
            return;
        }
    }
}
