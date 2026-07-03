<?php

load_library('access');

if (!access_by_feature('manage-.jobs')) {
    $GLOBALS['SYSTEM']['validation_errors']['_global'][] = '[#text Access denied#]';
}
