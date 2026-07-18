<?php

function language_switch_data_sc()
{
    load_libraries(['data', 'detect-language', 'i18n-url', 'set']);
    $languages = data_lookup('.config', 'site', 'languages', ['en']);
    $active_language = detect_language_sc();
    $items = [];

    foreach ($languages as $language) {
        $result = i18n_url_resolve($language);
        if ($result['availability'] === 'hidden') {
            continue;
        }
        $items[] = [
            'code' => $language,
            'label' => strtoupper($language),
            'url' => $result['url'],
            'active' => $language === $active_language ? 'true' : 'false',
            'availability' => $result['availability'],
            'separator' => empty($items) ? 'false' : 'true',
        ];
    }

    set_variable('language_switch_items', $items);
}
