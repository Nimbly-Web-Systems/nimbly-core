<?php

/**
 * Label for the page actions' admin link: "Edit article" when page-settings-link
 * points at an admin record (/nb-admin/<resource>/...), else "Open in admin".
 * A page can set page-settings-label to override it.
 */
function page_actions_label_sc($params = null) {
    load_libraries(['text', 'data', 'resource-name']);
    $label = trim((string)get_variable('page-settings-label'));
    if ($label !== '') {
        return $label;
    }
    $path = (string)parse_url((string)get_variable('page-settings-link'), PHP_URL_PATH);
    if (preg_match('~/nb-admin/([^/]+)/~', $path, $m) && data_exists($m[1])) {
        return t('Edit') . ' ' . mb_strtolower(resource_name_sc([$m[1]]));
    }
    return t('Open in admin');
}
