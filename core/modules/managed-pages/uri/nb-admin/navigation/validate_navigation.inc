<?php

load_libraries(['managed-pages', 'managed-navigation']);
$items = json_decode((string)post_get('items', ''), true);
if (!is_array($items)) {
    $GLOBALS['SYSTEM']['validation_errors']['_global'][] = '[#text Invalid navigation data.#]';
}
