<?php

/**
 * [#published-wall#] on the first line of a page's index.tpl keeps the page
 * offline until an editor publishes it. Published is `published` in the
 * page's settings record (.config/<url-key>, the record the page settings
 * dialog edits), so each language publishes on its own.
 *
 * [#published-wall resource=news var=record#] does the same for a page that
 * shows one record: it uses that record's own `published` field and the
 * edit-<resource> permission.
 *
 * Until published, visitors get the 404 page and editors see the page with a
 * notice and a Published switch in the page actions (and, for a page, in the
 * page settings dialog). A menu item that leads to an unpublished page is
 * left out for visitors (see published_wall_hides()).
 */

load_libraries(['data', 'get', 'set', 'url-key', 'access']);

function published_wall_sc($params) {
    $resource = (string)get_param_value($params, 'resource', '');
    if ($resource !== '') {
        $record = get_variable((string)get_param_value($params, 'var', 'record'));
        $uuid = is_array($record) ? (string)($record['uuid'] ?? '') : '';
        $published = published_wall_is_published($record);
        $feature = 'edit-' . $resource;
        $target = $resource . '/' . $uuid;
        set_variable('published_wall_source', 'record');
    } else {
        $published = published_wall_is_published(data_read('.config', url_key_sc()));
        $feature = 'edit-.config';
        $target = '.config/' . url_key_sc();
        set_variable('published_wall_source', 'settings');
    }
    set_variable('published_wall', 'true');
    set_variable('published_wall_target', $target);
    set_variable('page_published', $published ? 'true' : 'false');
    $can_edit = access_by_feature($feature);
    set_variable('published_wall_editable', $can_edit ? 'true' : 'false');
    if ($published) {
        return '';
    }
    if ($can_edit) {
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
