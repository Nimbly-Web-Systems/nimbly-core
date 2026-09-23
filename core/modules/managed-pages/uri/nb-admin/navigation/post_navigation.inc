<?php

load_libraries(['managed-pages', 'managed-navigation', 'set']);
$items = json_decode((string)post_get('items', '[]'), true);
$result = managed_navigation_save(
    (string)post_get('slot', ''),
    (string)post_get('language', ''),
    is_array($items) ? $items : [],
    (string)post_get('revision', '')
);
if ($result['ok']) {
    set_variable('navigation_editor_notice', '[#text Navigation saved.#]');
} else {
    $message = $result['error'] === 'stale'
        ? '[#text This navigation changed after you opened it. Reload and apply your changes again.#]'
        : '[#text Navigation could not be saved:#] ' . $result['error'];
    set_variable('navigation_editor_error', $message);
}
