<?php

load_library('access');

if (!access_by_feature('delete-.files')) {
    $GLOBALS['SYSTEM']['validation_errors']['_global'][] = '[#text Access denied#]';
}
