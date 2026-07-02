<?php

load_library('access');

if (!access_by_feature('clear-system-log')) {
    $GLOBALS['SYSTEM']['validation_errors']['_global'][] = '[#text Access denied#]';
}
