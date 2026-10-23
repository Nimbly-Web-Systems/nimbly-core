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
    $types = array_map(fn($definition) => $definition['type'] ?? 'text', $meta['fields'] ?? []);
    if (array_intersect($types, $extractable_types) === []) {
        return;
    }
    set_variable('import_document_resource', $resource);
    // The busy texts only name the steps this resource's fields call for.
    set_variable('import_document_has_location', in_array('location-picker', $types, true) ? '1' : '');
    set_variable('import_document_has_options', in_array('select', $types, true) ? '1' : '');
    // PDF only where the server can read it (pdftotext, see openai_import_document_text()).
    set_variable('import_document_accept', is_executable('/usr/bin/pdftotext') ? '.docx,.pdf,.md,.txt' : '.docx,.md,.txt');
}
