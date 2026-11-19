<?php

/**
 * [#published-wall#] on the first line of a page's index.tpl keeps the page
 * offline until an editor publishes it. Published is `published` in the
 * page's settings record (.config/<url-key>, the record the page settings
 * dialog edits), so each language publishes on its own.
 *
 * Until then visitors get the 404 page and users with edit-.config see the
 * page with a notice, a Published switch in the page actions and in the page
 * settings dialog. A menu item that leads to such a page is left out for
 * visitors (see published_wall_hides()).
 */

load_libraries(['data', 'get', 'set', 'url-key', 'access']);

function published_wall_sc($params) {
    $published = published_wall_is_published(data_read('.config', url_key_sc()));
    set_variable('published_wall', 'true');
    set_variable('page_published', $published ? 'true' : 'false');
    if ($published) {
        return '';
    }
    if (access_by_feature('edit-.config')) {
        load_libraries(['system-messages', 'text']);
        system_message(text_translate((string)get_variable('language', 'en'), "You're previewing an unpublished page. Only editors can see this."));
        return '';
    }
    run_uri('errors/404');
    exit();
}

function published_wall_is_published($settings): bool {
    return is_array($settings) && filter_var($settings['published'] ?? false, FILTER_VALIDATE_BOOLEAN);
}

/**
 * True when the code route at $path (`nl/opschalingstafel`) has a published
 * wall and is not published yet.
 */
function published_wall_hides(string $path): bool {
    static $walled = [];
    $path = trim(strtok($path, '#'), '/');
    if ($path === '') {
        return false;
    }
    if (!array_key_exists($path, $walled)) {
        $file = find_uri($path);
        $walled[$path] = $file !== false && str_contains((string)@file_get_contents($file), '[#published-wall#]');
    }
    return $walled[$path]
        && !published_wall_is_published(data_read('.config', preg_replace('/[^ \w]+/', '_', $path)));
}
