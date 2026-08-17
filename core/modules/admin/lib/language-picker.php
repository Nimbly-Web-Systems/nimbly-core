<?php

/**
 * [#language-picker#] — renders a control for choosing which configured
 * language the current form is authoring, bound to the Alpine `lang`
 * property. Picks a widget by how many languages are configured, since a
 * toggle stops being usable well before a plain select does, and a plain
 * select stops being scannable long before a search box is needed:
 *
 *   - fewer than 2 languages: nothing to pick, renders nothing
 *   - 2-3 languages: segmented toggle buttons
 *   - 4-8 languages: a plain select
 *   - 9+ languages: a searchable select
 */
function language_picker_sc($params)
{
    load_library('get');
    $languages = get_variable('languages');
    if (!is_array($languages) || count($languages) < 2) {
        return;
    }

    $count = count($languages);
    if ($count <= 3) {
        $variant = 'toggle';
    } elseif ($count <= 8) {
        $variant = 'select';
    } else {
        $variant = 'search';
    }

    // This picks the single language a brand-new record is being authored
    // in — not a switch between saved translations (that's the separate
    // edit-mode form-translation-tabs component). Easy to mistake for one,
    // so it's boxed like field-location-picker's own explanatory box
    // rather than left to look like an unrelated stray label.
    load_library('set');
    ob_start();
    run_single_sc('language-picker-' . $variant);
    set_variable('language-picker-control', ob_get_clean());

    $wrapper = find_template('language-picker-box');
    echo $wrapper ? run_buffered($wrapper) : get_variable('language-picker-control');
}
