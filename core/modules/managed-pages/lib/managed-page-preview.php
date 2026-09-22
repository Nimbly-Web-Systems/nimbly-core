<?php

function managed_page_preview_sc($params = null): string
{
    load_libraries(['data', 'get', 'set']);
    $page_uuid = (string)get_variable('_bf_uuid', get_variable('uuid', ''));
    $page = $page_uuid === '' ? null : data_read('pages', $page_uuid);
    if (!is_array($page)) {
        return '';
    }

    $base_url = rtrim((string)($GLOBALS['SYSTEM']['uri_base'] ?? ''), '/');
    $languages = [];
    foreach (($page['path'] ?? []) as $language => $path) {
        $path = trim((string)$path, '/');
        if ($path === '' || managed_pages_normalize_path($path) !== $path) {
            continue;
        }
        $languages[] = [
            'code' => (string)$language,
            'label' => mb_strtoupper((string)$language),
            'preview_url' => $base_url . '/' . $path,
        ];
    }
    if ($languages === []) {
        return '';
    }

    set_variable('managed_page_preview_languages', $languages);
    set_variable('managed_page_preview_default_language', $languages[0]['code']);
    return '[#managed-page-preview-panel#]';
}
