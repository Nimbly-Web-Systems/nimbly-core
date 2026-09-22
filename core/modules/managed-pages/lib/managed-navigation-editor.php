<?php

function managed_navigation_editor_sc($params = null): string
{
    load_libraries(['data', 'get', 'set', 'managed-pages', 'managed-navigation']);
    $slots = managed_navigation_slots();
    $languages = data_lookup('.config', 'site', 'languages', ['en']);
    $slot = (string)($_POST['slot'] ?? $_GET['slot'] ?? array_key_first($slots));
    $language = (string)($_POST['language'] ?? $_GET['language'] ?? ($languages[0] ?? 'en'));
    if (!isset($slots[$slot])) {
        $slot = (string)array_key_first($slots);
    }
    if (!in_array($language, $languages, true)) {
        $language = (string)($languages[0] ?? 'en');
    }
    $document = data_read('.navigation', managed_navigation_document_id($slot, $language));
    $slot_options = [];
    foreach ($slots as $slot_id => $definition) {
        $definition = is_array($definition) ? $definition : [];
        $slot_options[] = ['value' => (string)$slot_id, 'label' => (string)($definition['name'] ?? $slot_id)];
    }
    $language_options = [];
    foreach ($languages as $language_code) {
        $language_options[] = ['value' => (string)$language_code, 'label' => strtoupper((string)$language_code)];
    }
    $json_flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    set_variable('navigation_editor_slots_json', json_encode($slot_options, $json_flags));
    set_variable('navigation_editor_languages_json', json_encode($language_options, $json_flags));
    set_variable('navigation_editor_slot', $slot);
    set_variable('navigation_editor_language', $language);
    set_variable('navigation_editor_depth', max(1, (int)($slots[$slot]['depth'] ?? 1)));
    set_variable('navigation_editor_items', is_array($document['items'] ?? null) ? $document['items'] : []);
    set_variable('navigation_editor_revision', managed_navigation_revision(is_array($document) ? $document : null));
    set_variable('navigation_editor_pages', managed_navigation_editor_pages($language));
    set_variable('navigation_editor_items_json', json_encode(get_variable('navigation_editor_items', []), $json_flags));
    set_variable('navigation_editor_pages_json', json_encode(get_variable('navigation_editor_pages', []), $json_flags));
    return '';
}

function managed_navigation_editor_pages(string $language): array
{
    $result = [];
    foreach (data_read('pages') as $uuid => $record) {
        $title = managed_pages_localized_value($record, 'title', $language);
        if ($title !== null) {
            $result[$uuid] = $title;
        }
    }
    return $result;
}
