<?php

load_library('access');
load_library('get');

if (!access_by_feature('delete-' . get_variable('resource-id', ''))) {
    $GLOBALS['SYSTEM']['validation_errors']['_global'][] = '[#text Access denied#]';
}
