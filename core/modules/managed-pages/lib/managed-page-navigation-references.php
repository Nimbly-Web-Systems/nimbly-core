<?php

function managed_page_navigation_references_sc($params = null): string
{
    load_libraries(['data', 'get', 'set']);
    $page_uuid = (string)get_variable('_bf_uuid', '');
    if ($page_uuid === '') {
        return '';
    }
    $references = [];
    foreach (data_read('.navigation') as $document) {
        managed_page_navigation_collect_references(
            is_array($document['items'] ?? null) ? $document['items'] : [],
            $page_uuid,
            (string)($document['slot'] ?? ''),
            (string)($document['language'] ?? ''),
            $references
        );
    }
    if ($references === []) {
        return '';
    }
    set_variable('managed_page_navigation_references', $references);
    return '[#managed-page-navigation-references-panel#]';
}

function managed_page_navigation_collect_references(array $items, string $page_uuid, string $slot, string $language, array &$references): void
{
    foreach ($items as $item) {
        if (($item['target']['kind'] ?? '') === 'page'
            && ($item['target']['value'] ?? $item['target']['id'] ?? '') === $page_uuid) {
            $references[] = [
                'slot' => $slot,
                'language' => $language,
                'label' => (string)($item['label'] ?? ''),
            ];
        }
        if (is_array($item['children'] ?? null)) {
            managed_page_navigation_collect_references($item['children'], $page_uuid, $slot, $language, $references);
        }
    }
}
