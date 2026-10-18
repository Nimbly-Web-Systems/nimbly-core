<?php

/**
 * A description typed in the page settings is the page's description,
 * unless the route set its own; it is escaped for the meta tags.
 */
function page_settings_description_sc(): string
{
    $description = trim((string)($GLOBALS['SYSTEM']['variables']['page_settings']['page_description'] ?? ''));
    if ($description !== '') {
        load_library('set');
        set_variable('page-description', htmlspecialchars($description, ENT_QUOTES, 'UTF-8'), false);
    }
    return '';
}
